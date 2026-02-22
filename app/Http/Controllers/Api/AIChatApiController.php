<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\GuestUserService;
use App\Services\IntentClassifierService;
use App\Services\PresentationService;
use App\Services\Tools\DiagramMicroserviceClient;
use App\Services\Tools\PptMicroserviceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AIChatApiController extends Controller
{
    private const INTENT_CONFIDENCE_THRESHOLD = 0.6;

    public function __construct(
        protected GuestUserService $guestUserService,
        protected IntentClassifierService $intentClassifier
    ) {}

    /**
     * POST /api/v1/{region}/chat - Send message and get AI reply.
     */
    public function handleChat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:32000',
            'session_id' => 'nullable|string|uuid',
            'attachment_url' => 'nullable|string|url',
        ]);

        $user = $request->user();
        if (! $user && $request->bearerToken()) {
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Throwable $e) {
                // Bearer was sent but invalid/expired: do not fall back to guest (would create guest session for a logged-in user).
                Log::warning('[Chat] JWT invalid or expired on POST /chat', ['message' => $e->getMessage()]);
                return response()->json(['error' => 'Unauthorized', 'message' => 'Token invalid or expired'], 401);
            }
        }
        $message = trim($request->input('message'));
        $sessionId = $request->input('session_id');
        $attachmentUrl = $request->input('attachment_url');

        // Resolve session: user or guest (pass first message to title new sessions)
        $session = $this->resolveOrCreateSession($request, $user, $sessionId, $message);
        if (!$session) {
            return response()->json(['error' => 'Could not resolve or create chat session.'], 400);
        }

        // Save user message
        $this->saveMessage($session, $user, 'user', $message, (bool) $attachmentUrl);

        // Classify intent (brain): route to the right microservice or text reply
        $intent = $this->classifyIntent($message, $attachmentUrl);
        $intentType = strtolower($intent['intent'] ?? 'general');
        $confidence = (float) ($intent['confidence'] ?? 0);

        // Route by classified intent when confidence is high enough
        if ($intentType === 'presentation' && $confidence >= self::INTENT_CONFIDENCE_THRESHOLD) {
            $presentationResult = $this->tryPresentationReply($message, $session, $user, true);
            if ($presentationResult !== null) {
                return $this->sendToolReply($session, $user, $presentationResult);
            }
        } elseif ($intentType === 'diagram' && $confidence >= self::INTENT_CONFIDENCE_THRESHOLD) {
            $diagramResult = $this->tryDiagramReply($message, true);
            if ($diagramResult !== null) {
                return $this->sendToolReply($session, $user, $diagramResult);
            }
        }

        // Fallback: keyword-based detection (when classifier unavailable or returned general)
        $presentationResult = $this->tryPresentationReply($message, $session, $user, false);
        if ($presentationResult !== null) {
            return $this->sendToolReply($session, $user, $presentationResult);
        }
        $diagramResult = $this->tryDiagramReply($message, false);
        if ($diagramResult !== null) {
            return $this->sendToolReply($session, $user, $diagramResult);
        }

        // Text reply: AI manager (or OpenAI fallback)
        $reply = $this->callAi($message, $session, $attachmentUrl);
        if ($reply === null) {
            $reply = 'I could not generate a response right now. Please try again.';
        }

        $this->saveMessage($session, $user, 'assistant', $reply, false);

        return response()->json([
            'reply' => $reply,
            'session_id' => $session->id,
            'usage' => ['total_tokens' => 0],
        ]);
    }

    private function sendToolReply(ChatSession $session, $user, array $toolResult): \Illuminate\Http\JsonResponse
    {
        $reply = $toolResult['reply'];
        $metadata = isset($toolResult['reply_type']) ? [
            'reply_type' => $toolResult['reply_type'],
            'job_id' => $toolResult['job_id'] ?? null,
            'payload' => $toolResult['payload'] ?? null,
        ] : null;
        $this->saveMessage($session, $user, 'assistant', $reply, false, $metadata);
        $response = [
            'reply' => $reply,
            'session_id' => $session->id,
            'usage' => ['total_tokens' => 0],
        ];
        if (isset($toolResult['reply_type'])) {
            $response['reply_type'] = $toolResult['reply_type'];
            if (! empty($toolResult['job_id'])) {
                $response['job_id'] = $toolResult['job_id'];
            }
            if (! empty($toolResult['payload'])) {
                $response['payload'] = $toolResult['payload'];
            }
        }
        return response()->json($response);
    }

    /**
     * POST /api/v1/{region}/chat/upload - Upload attachment.
     */
    public function uploadAttachment(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB
        ]);

        $file = $request->file('file');
        $path = $file->store('chat-attachments', 'public');
        $url = $path ? asset('storage/' . $path) : null;

        if (!$url) {
            return response()->json(['error' => 'Upload failed.'], 500);
        }

        return response()->json(['url' => $url]);
    }

    private function resolveOrCreateSession(Request $request, $user, ?string $sessionId, string $firstMessage = ''): ?ChatSession
    {
        $titleForNew = $this->titleFromFirstMessage($firstMessage);

        if ($user) {
            if ($sessionId) {
                $session = ChatSession::where('id', $sessionId)->where('user_id', $user->id)->first();
                if ($session) {
                    return $session;
                }
                // Continue existing chat: session may exist but lookup missed (e.g. type). Re-find by id and check ownership.
                $existing = ChatSession::find($sessionId);
                if ($existing && (int) $existing->user_id === (int) $user->id) {
                    return $existing;
                }
            }
            $useClientId = $sessionId && Str::isUuid($sessionId) && ! ChatSession::where('id', $sessionId)->exists();
            $attrs = [
                'user_id' => $user->id,
                'title' => $titleForNew,
            ];
            if ($useClientId) {
                $attrs['id'] = $sessionId;
            }
            return ChatSession::create($attrs);
        }

        $guestSession = $this->guestUserService->getOrCreateGuestSession($request);
        if ($sessionId) {
            $session = ChatSession::where('id', $sessionId)
                ->where('guest_session_id', $guestSession->id)
                ->first();
            if ($session) {
                return $session;
            }
            // Continue existing chat: session may exist but guest_session_id lookup missed. Re-find by id and check guest ownership.
            $existing = ChatSession::find($sessionId);
            if ($existing && $existing->guest_session_id !== null) {
                if ((int) $existing->guest_session_id === (int) $guestSession->id) {
                    return $existing;
                }
                // Same device, different guest_session_id (e.g. cookie cleared, guest session recreated). Reassign so user stays on same chat.
                $currentFingerprint = $this->guestUserService->generateDeviceFingerprint($request);
                $oldGuest = $existing->guestSession;
                if ($oldGuest && $oldGuest->device_fingerprint === $currentFingerprint) {
                    $existing->update(['guest_session_id' => $guestSession->id]);
                    return $existing->fresh();
                }
            }
        }
        $useClientId = $sessionId && Str::isUuid($sessionId) && ! ChatSession::where('id', $sessionId)->exists();
        $attrs = [
            'guest_session_id' => $guestSession->id,
            'is_guest' => true,
            'title' => $titleForNew,
        ];
        if ($useClientId) {
            $attrs['id'] = $sessionId;
        }
        return ChatSession::create($attrs);
    }

    /**
     * Derive a session title from the user's first message (max 255 chars for DB).
     */
    private function titleFromFirstMessage(string $message): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $message));
        if ($trimmed === '') {
            return 'Chat';
        }
        $max = 255;
        return mb_strlen($trimmed) > $max ? mb_substr($trimmed, 0, $max - 3) . '...' : $trimmed;
    }

    private function saveMessage(ChatSession $session, $user, string $role, string $content, bool $isAttachment, ?array $metadata = null): void
    {
        $attrs = [
            'chat_session_id' => $session->id,
            'role' => $role,
            'content' => $content,
            'is_attachment' => $isAttachment,
        ];
        if ($user) {
            $attrs['user_id'] = $user->id;
        } elseif ($session->guest_session_id) {
            $attrs['guest_session_id'] = $session->guest_session_id;
        }
        if ($metadata !== null) {
            $attrs['metadata'] = $metadata;
        }
        ChatMessage::create($attrs);
        $session->touch();
    }

    private function classifyIntent(string $message, ?string $attachmentUrl): array
    {
        return $this->intentClassifier->classify($message, $attachmentUrl);
    }

    /**
     * If message looks like a presentation request, submit to tools/ppt async API and return job_id.
     * When $trustIntent is true (from classifier), skip keyword check.
     * Returns ['reply' => string, 'reply_type' => 'presentation_outline', 'job_id' => string] or ['reply' => string] or null.
     */
    private function tryPresentationReply(string $message, ChatSession $session, $user, bool $trustIntent = false): ?array
    {
        if (! $trustIntent) {
            $lower = strtolower($message);
            $isPpt = preg_match('/\b(ppt|presentation|slides?)\b/i', $lower)
            || Str::contains($lower, 'generate a presentation')
            || Str::contains($lower, 'make a ppt')
            || Str::contains($lower, 'create a presentation');

            if (!$isPpt) {
                return null;
            }
        }

        [$topic, $numSlides] = PresentationService::parseTopicAndSlides($message);
        if (trim($topic) === '') {
            return ['reply' => 'Please specify a topic for the presentation (e.g. "presidents of the United States"). You can add "with 10 slides" or "15 slides" to set the number of slides (default is 10).'];
        }

        $client = app(PptMicroserviceClient::class);
        $content = $topic . (\strlen($topic) > 0 ? ' ' : '') . "Generate an outline with {$numSlides} slides.";
        $result = $client->submitOutline($content, 'English', 'Professional', 'Medium');

        if ($result['success'] && !empty($result['job_id'])) {
            return [
                'reply' => 'Generating your presentation outline. This may take a moment. When ready, you can edit the outline, then generate slide content, then pick a style and export to PPT.',
                'reply_type' => 'presentation_outline',
                'job_id' => $result['job_id'],
                'payload' => [
                    'step' => 1,
                    'next_steps' => 'Poll GET presentations/status and result for outline → user may edit → POST presentations/generate-content with outline → poll for content → user may edit → GET presentations/templates → POST presentations/export with content + template/style.',
                ],
            ];
        }

        return ['reply' => $result['error'] ?? 'Could not start presentation outline. Please try again.'];
    }

    /**
     * If message looks like a diagram request, submit to tools/diagram and return job_id.
     * When $trustIntent is true (from classifier), skip keyword check.
     * Returns ['reply' => string, 'reply_type' => 'diagram', 'job_id' => string] or ['reply' => string] or null.
     */
    private function tryDiagramReply(string $message, bool $trustIntent = false): ?array
    {
        if (! $trustIntent) {
            $lower = strtolower($message);
            $isDiagram = preg_match('/\b(diagram|flowchart|flow chart|mermaid|plantuml|visuali[sz]e)\b/i', $lower)
                || Str::contains($lower, 'draw a diagram')
                || Str::contains($lower, 'create a flowchart')
                || Str::contains($lower, 'generate a diagram')
                || Str::contains($lower, 'generate a flowchart')
                || Str::contains($lower, 'flowchart diagram')
                || Str::contains($lower, 'flow chart diagram');
            if (! $isDiagram) {
                return null;
            }
        }

        $client = app(DiagramMicroserviceClient::class);
        $result = $client->generateDiagram($message, 'flowchart', 'png');

        if ($result['success'] && ! empty($result['job_id'])) {
            return [
                'reply' => 'Generating your diagram. This may take a moment…',
                'reply_type' => 'diagram',
                'job_id' => $result['job_id'],
            ];
        }

        return ['reply' => $result['error'] ?? 'Could not start diagram generation. Please try again.'];
    }

    private function callAi(string $message, ChatSession $session, ?string $attachmentUrl): ?string
    {
        $baseUrl = rtrim(config('services.ai_manager.url') ?? env('AI_MANAGER_URL'), '/');
        $key = config('services.ai_manager.key') ?? env('AI_MANAGER_KEY');
        $model = config('services.ai_manager.model') ?? env('AI_MANAGER_MODEL', 'deepseek-chat');

        // Use same endpoint and auth as zooys: /api/custom-prompt + X-API-KEY
        if ($baseUrl && $key) {
            try {
                $body = [
                    'prompt' => $message,
                    'model' => $model,
                    'response_format' => 'text',
                ];
                if ($attachmentUrl) {
                    $body['attachment_url'] = $attachmentUrl;
                }
                $url = $baseUrl . '/api/custom-prompt';
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => $key,
                ])->timeout(120)->post($url, $body);

                if ($response->successful()) {
                    $data = $response->json();
                    if (($data['status'] ?? '') !== 'success') {
                        Log::warning('[AIChat] AI manager non-success status', ['status' => $data['status'] ?? null, 'message' => $data['message'] ?? '']);
                        return null;
                    }
                    $dataPayload = $data['data'] ?? [];
                    $text = $data['reply'] ?? $data['response'] ?? $data['content'] ?? $data['text']
                        ?? $data['message'] ?? $dataPayload['content'] ?? $dataPayload['raw'] ?? $dataPayload['text'] ?? null;
                    if (is_array($text)) {
                        $text = $text['content'] ?? $text['text'] ?? json_encode($text);
                    }
                    if ($text === null && ! empty($data)) {
                        Log::warning('[AIChat] AI manager 200 but no known key', ['keys' => array_keys($data), 'sample' => substr(json_encode($data), 0, 500)]);
                    }
                    return $text;
                }
                Log::warning('[AIChat] AI manager non-2xx', ['status' => $response->status(), 'body' => $response->body()]);
            } catch (\Throwable $e) {
                Log::error('[AIChat] AI manager error', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: OpenAI if configured
        $openaiKey = config('services.openai.key') ?? env('OPENAI_KEY');
        $openaiUrl = config('services.openai.url') ?? env('OPENAI_URL');
        if ($openaiKey && $openaiUrl) {
            try {
                $response = Http::withToken($openaiKey)
                    ->timeout(120)
                    ->post($openaiUrl, [
                        'model' => 'gpt-3.5-turbo',
                        'messages' => [
                            ['role' => 'user', 'content' => $message],
                        ],
                        'max_tokens' => 1024,
                    ]);
                if ($response->successful()) {
                    $data = $response->json();
                    return $data['choices'][0]['message']['content'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::error('[AIChat] OpenAI fallback error', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }
}
