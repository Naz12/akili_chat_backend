<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\GuestUserService;
use App\Services\IntentClassifierService;
use App\Services\PresentationService;
use App\Services\Tools\DiagramMicroserviceClient;
use App\Services\Tools\DocConverterClient;
use App\Services\Tools\PptMicroserviceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        ], [
            'message.required' => 'Please enter a message.',
            'message.max' => 'Message is too long.',
            'attachment_url.url' => 'Attachment URL is invalid.',
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
            $diagramResult = $this->tryDiagramReply($message, $intent, true);
            if ($diagramResult !== null) {
                return $this->sendToolReply($session, $user, $diagramResult);
            }
        } elseif ($intentType === 'doc_converter' && $confidence >= self::INTENT_CONFIDENCE_THRESHOLD) {
            $docConverterResult = $this->tryDocConverterReply($request, $message, $attachmentUrl, $intent, $session, $user);
            if ($docConverterResult !== null) {
                return $this->sendToolReply($session, $user, $docConverterResult);
            }
        }

        // Fallback: keyword-based detection (when classifier unavailable or returned general)
        $presentationResult = $this->tryPresentationReply($message, $session, $user, false);
        if ($presentationResult !== null) {
            return $this->sendToolReply($session, $user, $presentationResult);
        }
        $diagramResult = $this->tryDiagramReply($message, $intent, false);
        if ($diagramResult !== null) {
            return $this->sendToolReply($session, $user, $diagramResult);
        }
        if ($attachmentUrl && $this->hasDocConverterKeywords($message)) {
            $docConverterIntent = $this->resolveDocConverterIntentFromMessage($message);
            $docConverterResult = $this->tryDocConverterReply($request, $message, $attachmentUrl, $docConverterIntent, $session, $user);
            if ($docConverterResult !== null) {
                return $this->sendToolReply($session, $user, $docConverterResult);
            }
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

    /** @return \Illuminate\Http\JsonResponse */
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
        ], [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'Invalid file.',
            'file.max' => 'File is too large. Maximum size is 50 MB.',
        ]);

        try {
            $disk = Storage::disk('public');
            $root = storage_path('app/public');
            $chatDir = 'chat-attachments';
            $fullPath = $root . DIRECTORY_SEPARATOR . $chatDir;

            // Ensure root exists (storage/app/public)
            if (! is_dir($root)) {
                @mkdir($root, 0775, true);
            }
            // Ensure chat-attachments directory exists and is writable
            if (! is_dir($fullPath)) {
                if (! @mkdir($fullPath, 0775, true)) {
                    Log::warning('[AIChat] Upload: could not create directory', ['path' => $fullPath]);
                    return response()->json([
                        'error' => 'Upload failed. Storage directory could not be created. On the server run: ./fix-permissions.sh from the backend directory.',
                    ], 500);
                }
            }
            if (! is_writable($fullPath)) {
                Log::warning('[AIChat] Upload: directory not writable', ['path' => $fullPath]);
                return response()->json([
                    'error' => 'Upload failed. Storage directory is not writable. On the server run: ./fix-permissions.sh from the backend directory.',
                ], 500);
            }

            $file = $request->file('file');
            $path = $file->store($chatDir, 'public');
            if (! $path) {
                Log::warning('[AIChat] Upload store returned empty path', ['disk' => 'public', 'dir_writable' => is_writable($fullPath)]);
                return response()->json([
                    'error' => 'Upload failed. Storage directory may be read-only. On the server run: ./fix-permissions.sh from the backend directory.',
                ], 500);
            }

            $url = asset('storage/' . $path);
            return response()->json(['url' => $url]);
        } catch (\Throwable $e) {
            Log::error('[AIChat] Upload failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $message = config('app.debug') ? $e->getMessage() : 'Upload failed. Please try again or run ./fix-permissions.sh on the server.';
            return response()->json(['error' => $message], 500);
        }
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
     * Uses diagram_type from intent entities (AI) or infers from message. When $trustIntent is true, skip keyword check.
     */
    private function tryDiagramReply(string $message, array $intent = [], bool $trustIntent = false): ?array
    {
        if (! $trustIntent) {
            $lower = strtolower($message);
            $isDiagram = preg_match('/\b(diagram|flowchart|flow chart|mermaid|plantuml|visuali[sz]e)\b/i', $lower)
                || preg_match('/\b(pie ?chart|bar ?chart|line ?chart|chart of|generate a chart|create a chart|draw a chart)\b/i', $lower)
                || Str::contains($lower, 'piechart')
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

        $diagramType = $this->resolveDiagramType($message, $intent);
        $client = app(DiagramMicroserviceClient::class);
        $result = $client->generateDiagram($message, $diagramType, 'png');

        if ($result['success'] && ! empty($result['job_id'])) {
            return [
                'reply' => 'Generating your diagram. This may take a moment…',
                'reply_type' => 'diagram',
                'job_id' => $result['job_id'],
                'payload' => ['diagram_type' => $diagramType],
            ];
        }

        return ['reply' => $result['error'] ?? 'Could not start diagram generation. Please try again.'];
    }

    /** Resolve diagram_type from AI entities or infer from message. Matches tools/diagram: 13 types. */
    private function resolveDiagramType(string $message, array $intent): string
    {
        $allowed = [
            'flowchart', 'sequence', 'class', 'state', 'er', 'user_journey', 'block', 'mindmap',
            'pie', 'quadrant', 'timeline', 'sankey', 'xy',
        ];
        $fromEntity = $intent['entities']['diagram_type'] ?? null;
        if (is_string($fromEntity) && $fromEntity !== '') {
            $normalized = strtolower(trim($fromEntity));
            if ($normalized === 'journey') {
                $normalized = 'user_journey';
            }
            if (in_array($normalized, $allowed, true)) {
                return $normalized;
            }
        }
        $lower = strtolower($message);
        if (preg_match('/\bsequence\b/i', $lower)) {
            return 'sequence';
        }
        if (preg_match('/\b(class diagram|object relationship)\b/i', $lower)) {
            return 'class';
        }
        if (preg_match('/\b(entity|er diagram|database)\b/i', $lower)) {
            return 'er';
        }
        if (preg_match('/\b(state machine|state diagram)\b/i', $lower)) {
            return 'state';
        }
        if (preg_match('/\b(user ?journey|journey map)\b/i', $lower)) {
            return 'user_journey';
        }
        if (preg_match('/\b(block diagram|architecture)\b/i', $lower)) {
            return 'block';
        }
        if (preg_match('/\bmind ?map\b/i', $lower)) {
            return 'mindmap';
        }
        if (preg_match('/\bpie\b/i', $lower)) {
            return 'pie';
        }
        if (preg_match('/\bquadrant\b/i', $lower)) {
            return 'quadrant';
        }
        if (preg_match('/\btimeline\b/i', $lower)) {
            return 'timeline';
        }
        if (preg_match('/\bsankey\b/i', $lower)) {
            return 'sankey';
        }
        if (preg_match('/\b(xy|scatter|line chart)\b/i', $lower)) {
            return 'xy';
        }
        return 'flowchart';
    }

    private const DOC_CONVERTER_CACHE_PREFIX = 'doc_convert_job:';
    private const DOC_CONVERTER_CACHE_TTL = 3600;

    /**
     * If intent is doc_converter and user attached a file, run the requested operation
     * (convert, extract, merge message, split, or PDF: compress, watermark, page_numbers, protect, unlock, preview, edit_pdf).
     */
    private function tryDocConverterReply(Request $request, string $message, ?string $attachmentUrl, array $intent, ChatSession $session, $user): ?array
    {
        if (empty($attachmentUrl)) {
            return ['reply' => 'Please attach a document. Supported doc-converter operations (confirmed): Convert, Extract, Split, Compress, Page numbers, Watermark, Protect. Merge requires multiple files.'];
        }

        $entities = $intent['entities'] ?? [];
        $operation = strtolower(trim((string) ($entities['operation'] ?? 'convert')));
        if (! in_array($operation, self::DOC_CONVERTER_OPERATIONS, true)) {
            $operation = 'convert';
        }
        $targetFormat = $this->resolveDocConverterTargetFormat($message, $intent);
        $splitPoints = trim((string) ($entities['split_points'] ?? ''));
        // Microservice "convert" does not support target_format=text; use extract for text.
        if ($operation === 'convert' && $targetFormat === 'text') {
            $operation = 'extract';
        }

        if ($operation === 'merge') {
            return ['reply' => 'To merge documents, please attach multiple files and use the Merge option, or use the doc-converter merge endpoint with multiple files.'];
        }

        if ($operation === 'split' && $splitPoints === '') {
            return ['reply' => 'To split a PDF, please specify at which page numbers to split (e.g. "split at pages 3, 7 and 12").'];
        }

        // PDF operations that need extra params from the user
        if ($operation === 'protect') {
            $password = trim((string) ($entities['password'] ?? ''));
            if ($password === '') {
                return ['reply' => 'To protect this PDF, please tell me the password (e.g. "protect with password mypass").'];
            }
        }
        if ($operation === 'unlock') {
            $password = trim((string) ($entities['password'] ?? ''));
            if ($password === '') {
                return ['reply' => 'To unlock this PDF, please provide the document password (e.g. "unlock with password mypass").'];
            }
        }
        if ($operation === 'watermark') {
            $content = trim((string) ($entities['watermark_content'] ?? ''));
            if ($content === '') {
                return ['reply' => 'To add a watermark, please say the text to use (e.g. "add watermark CONFIDENTIAL" or "watermark with DRAFT").'];
            }
        }
        if ($operation === 'edit_pdf') {
            $pageOrder = trim((string) ($entities['page_order'] ?? ''));
            if ($pageOrder === '') {
                return ['reply' => 'To reorder pages, please specify the order (e.g. "reverse pages" or "reorder to 1, 3, 2").'];
            }
        }
        if ($operation === 'annotate') {
            return ['reply' => 'To add annotations (shapes, text, highlights), please use the doc-converter tool with a JSON annotations array, or say "convert to PDF" after editing elsewhere.'];
        }

        $tempPath = $this->downloadAttachmentToTemp($attachmentUrl);
        if ($tempPath === null) {
            return ['reply' => 'Could not read the attached file. Please try again or upload again.'];
        }

        $client = app(DocConverterClient::class);
        $result = null;
        try {
            if ($operation === 'split') {
                $result = $client->splitDocument($tempPath, ['split_points' => $splitPoints]);
            } elseif ($operation === 'extract') {
                $result = $client->extractDocument($tempPath, 'text');
            } elseif ($operation === 'convert') {
                $result = $client->convertDocument($tempPath, $targetFormat);
            } elseif (in_array($operation, ['compress', 'watermark', 'page_numbers', 'annotate', 'protect', 'unlock', 'preview', 'edit_pdf'], true)) {
                $params = $this->buildPdfOperationParamsForChat($operation, $entities);
                $result = $client->postPdfOperation($operation, $tempPath, $params);
            } else {
                $result = $client->convertDocument($tempPath, $targetFormat);
            }
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        if (! $result['success'] || empty($result['job_id'])) {
            return ['reply' => $result['error'] ?? 'Doc-converter is temporarily unavailable. Please try again.'];
        }

        $jobId = $result['job_id'];
        $guestSession = ! $user ? $this->guestUserService->getGuestSessionFromRequest($request) : null;
        $cachePayload = [
            'user_id' => $user?->id,
            'guest_session_id' => $guestSession?->id,
            'chat_session_id' => $session->id,
            'operation' => $operation,
        ];
        if ($operation === 'convert' && $targetFormat !== null && $targetFormat !== '') {
            $cachePayload['target_format'] = $targetFormat;
        }
        Cache::put(self::DOC_CONVERTER_CACHE_PREFIX . $jobId, $cachePayload, self::DOC_CONVERTER_CACHE_TTL);

        $replyMsg = match ($operation) {
            'split' => 'Splitting your document. This may take a moment…',
            'extract' => 'Extracting text from your document. This may take a moment…',
            'compress' => 'Compressing your PDF. This may take a moment…',
            'watermark' => 'Adding watermark. This may take a moment…',
            'page_numbers' => 'Adding page numbers. This may take a moment…',
            'protect' => 'Protecting your PDF. This may take a moment…',
            'unlock' => 'Unlocking your PDF. This may take a moment…',
            'preview' => 'Generating preview. This may take a moment…',
            'edit_pdf' => 'Reordering pages. This may take a moment…',
            default => 'Converting your document. This may take a moment…',
        };
        $operationsList = 'Supported doc-converter operations (confirmed): Convert, Extract, Split, Compress, Page numbers, Watermark, Protect. Merge requires multiple files.';
        return [
            'reply' => $replyMsg . "\n\n" . $operationsList,
            'reply_type' => 'doc_converter',
            'job_id' => $jobId,
            'payload' => ['operation' => $operation, 'target_format' => $operation === 'convert' ? $targetFormat : null],
        ];
    }

    /** Build params for PDF operations from chat intent entities. */
    private function buildPdfOperationParamsForChat(string $operation, array $entities): array
    {
        $params = [];
        switch ($operation) {
            case 'watermark':
                $params['watermark_type'] = $entities['watermark_type'] ?? 'text';
                $params['watermark_content'] = trim((string) ($entities['watermark_content'] ?? ''));
                break;
            case 'protect':
            case 'unlock':
                $params['password'] = trim((string) ($entities['password'] ?? ''));
                break;
            case 'edit_pdf':
                $params['page_order'] = trim((string) ($entities['page_order'] ?? 'as_is'));
                break;
            case 'compress':
                if (isset($entities['compression_level'])) {
                    $params['compression_level'] = $entities['compression_level'];
                }
                break;
            case 'page_numbers':
            case 'preview':
                // Optional params can be added from entities if we parse them later
                break;
        }
        return $params;
    }

    /** Allowed doc-converter operations from chat (convert, extract, merge, split + PDF ops). */
    private const DOC_CONVERTER_OPERATIONS = [
        'convert', 'extract', 'merge', 'split',
        'compress', 'watermark', 'page_numbers', 'annotate', 'protect', 'unlock', 'preview', 'edit_pdf',
    ];

    /** Keyword check for doc-converter fallback (mirrors IntentClassifierService). */
    private function hasDocConverterKeywords(string $message): bool
    {
        $lower = strtolower($message);
        if (preg_match('/\b(convert|merge|split|extract|compress|watermark|page\s*numbers?|protect|unlock|preview|edit\s*pdf|reorder|reverse)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(to|into|as)\s+(jpg|jpeg|png|pdf|docx?|md|text|word|image)\b/i', $lower)) {
            return true;
        }
        return str_contains($lower, 'convert this') || str_contains($lower, 'convert the')
            || str_contains($lower, 'turn this into') || str_contains($lower, 'to image')
            || str_contains($lower, 'add watermark') || str_contains($lower, 'protect with password')
            || str_contains($lower, 'make this pdf smaller');
    }

    /** Build intent entities for doc_converter from message when LLM did not classify as doc_converter. */
    private function resolveDocConverterIntentFromMessage(string $message): array
    {
        $lower = strtolower($message);
        $operation = 'convert';
        $entities = [];

        if (preg_match('/\b(merge|combine)\b/i', $lower)) {
            $operation = 'merge';
        } elseif (preg_match('/\bsplit\b/i', $lower)) {
            $operation = 'split';
            if (preg_match('/\b(?:at|page|pages?)\s*(\d+(?:\s*(?:,|\band\b)\s*\d+)*)/i', $message, $m)) {
                $entities['split_points'] = preg_replace('/\s*(?:,|\band\b)\s*/', ',', trim($m[1]));
            } else {
                $entities['split_points'] = '';
            }
            $entities['operation'] = 'split';
            return ['intent' => 'doc_converter', 'confidence' => 0.8, 'entities' => $entities];
        } elseif (preg_match('/\b(extract|get)\s+text\b/i', $lower) || str_contains($lower, 'to text')) {
            $operation = 'extract';
        } elseif (preg_match('/\bcompress\b/i', $lower) || str_contains($lower, 'make this pdf smaller') || str_contains($lower, 'reduce file size')) {
            $operation = 'compress';
        } elseif (preg_match('/\bwatermark\b/i', $lower) || str_contains($lower, 'add watermark')) {
            $operation = 'watermark';
            if (preg_match('/watermark\s+["\']?([^"\']+)["\']?|watermark\s+(?:with|:)\s*["\']?([^"\']+)["\']?/i', $message, $wm)) {
                $entities['watermark_content'] = trim($wm[1] ?? $wm[2] ?? '');
                $entities['watermark_type'] = 'text';
            }
        } elseif (preg_match('/\bpage\s*numbers?\b/i', $lower) || str_contains($lower, 'add page numbers')) {
            $operation = 'page_numbers';
        } elseif (preg_match('/\bprotect\b/i', $lower) || str_contains($lower, 'password protect')) {
            $operation = 'protect';
            if (preg_match('/password\s+["\']?([^\s"\']+)["\']?|with\s+password\s+["\']?([^\s"\']+)["\']?/i', $message, $pw)) {
                $entities['password'] = trim($pw[1] ?? $pw[2] ?? '');
            }
        } elseif (preg_match('/\bunlock\b/i', $lower) || str_contains($lower, 'remove password')) {
            $operation = 'unlock';
            if (preg_match('/password\s+["\']?([^\s"\']+)["\']?|(?:is|:)\s*["\']?([^\s"\']+)["\']?/i', $message, $pw)) {
                $entities['password'] = trim($pw[1] ?? $pw[2] ?? '');
            }
        } elseif (preg_match('/\bpreview\b/i', $lower) || str_contains($lower, 'thumbnail')) {
            $operation = 'preview';
        } elseif (preg_match('/\bedit\s*pdf\b|\breorder\s*pages?\b|\breverse\s*pages?\b/i', $lower)) {
            $operation = 'edit_pdf';
            if (preg_match('/\breverse\b/i', $lower)) {
                $entities['page_order'] = 'reverse';
            } elseif (preg_match('/\b(?:order|pages?)\s*[:\s]*(\d+(?:\s*,\s*\d+)*)/i', $message, $m)) {
                $entities['page_order'] = preg_replace('/\s+/', ',', trim($m[1]));
            }
        }

        $entities['operation'] = $operation;
        if ($operation === 'convert') {
            $entities['target_format'] = $this->resolveDocConverterTargetFormat($message, ['entities' => []]);
        }
        return ['intent' => 'doc_converter', 'confidence' => 0.8, 'entities' => $entities];
    }

    /** Resolve target_format for convert. Microservice: doc, docx, html, jpg, md, pdf, png, ppt, pptx, xls, xlsx. */
    private function resolveDocConverterTargetFormat(string $message, array $intent): string
    {
        $fromEntity = isset($intent['entities']['target_format']) ? strtolower(trim((string) $intent['entities']['target_format'])) : '';
        $allowed = ['doc', 'docx', 'html', 'jpg', 'jpeg', 'md', 'pdf', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'text'];
        if ($fromEntity !== '' && in_array($fromEntity, $allowed, true)) {
            return $fromEntity === 'jpeg' ? 'jpg' : $fromEntity;
        }
        $lower = strtolower($message);
        if (preg_match('/\b(?:to|into|as)\s+(jpg|jpeg)\b/i', $lower) || str_contains($lower, 'as jpg')) {
            return 'jpg';
        }
        if (preg_match('/\b(?:to|into|as)\s+png\b/i', $lower) || str_contains($lower, 'as png')) {
            return 'png';
        }
        if (preg_match('/\b(?:to|into|as)\s+pdf\b/i', $lower)) {
            return 'pdf';
        }
        if (preg_match('/\b(?:to|into|as)\s+(?:docx?|word)\b/i', $lower)) {
            return 'docx';
        }
        if (preg_match('/\b(?:to|into|as)\s+(?:md|markdown)\b/i', $lower) || str_contains($lower, 'to text')) {
            return 'md';
        }
        return $fromEntity !== '' ? ($fromEntity === 'jpeg' ? 'jpg' : $fromEntity) : 'md';
    }

    /**
     * Download attachment from URL to a temp file for doc-converter (etc.).
     * If the URL points to our own storage (e.g. /storage/chat-attachments/...), read from disk
     * so the server does not need to HTTP GET itself (which can fail due to hostname/SSL).
     */
    private function downloadAttachmentToTemp(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        $urlPath = parse_url($url, PHP_URL_PATH);
        $urlPath = $urlPath !== null ? trim($urlPath, '/') : '';

        // Our upload returns asset('storage/' . $path) with $path = 'chat-attachments/...'
        if (str_starts_with($urlPath, 'storage/chat-attachments/')) {
            $relativePath = substr($urlPath, strlen('storage/'));
            $localPath = Storage::disk('public')->path($relativePath);
            if (is_readable($localPath)) {
                try {
                    $ext = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'bin';
                    $tmp = tempnam(sys_get_temp_dir(), 'akili_doc_');
                    if ($tmp === false) {
                        return null;
                    }
                    $path = $tmp . '.' . $ext;
                    if (! rename($tmp, $path)) {
                        @unlink($tmp);
                        return null;
                    }
                    if (copy($localPath, $path)) {
                        return $path;
                    }
                    @unlink($path);
                } catch (\Throwable $e) {
                    Log::warning('[AIChat] Copy local attachment failed', ['url' => $url, 'local' => $localPath, 'error' => $e->getMessage()]);
                }
                return null;
            }
            Log::warning('[AIChat] Local attachment not readable', ['url' => $url, 'local' => $localPath]);
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);
            if (! $response->successful()) {
                Log::warning('[AIChat] Download attachment HTTP failed', ['url' => $url, 'status' => $response->status()]);
                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'akili_doc_');
            if ($tmp === false) {
                return null;
            }
            $ext = pathinfo($urlPath ?: '', PATHINFO_EXTENSION) ?: 'bin';
            $path = $tmp . '.' . $ext;
            if (! rename($tmp, $path)) {
                @unlink($tmp);
                return null;
            }
            file_put_contents($path, $response->body());
            return $path;
        } catch (\Throwable $e) {
            Log::warning('[AIChat] Download attachment failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
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
