<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\GuestUserService;
use App\Services\UsageMeterService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AiChatHistoryApiController extends Controller
{
    public function __construct(
        protected GuestUserService $guestUserService,
        protected UsageMeterService $usageMeterService
    ) {}

    /**
     * GET /api/v1/{region}/chat/sessions
     * Chat routes do not use auth middleware (allow guests). When Bearer token is sent, resolve user so we return user sessions.
     */
    public function sessions(Request $request)
    {
        $user = $request->user();
        if (!$user && $request->bearerToken()) {
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Throwable $e) {
                // Bearer was sent but invalid/expired: return 401 so client can refresh token, not empty guest list.
                \Illuminate\Support\Facades\Log::warning('[Chat] JWT invalid or expired on GET /sessions', ['message' => $e->getMessage()]);
                return response()->json(['error' => 'Unauthorized', 'message' => 'Token invalid or expired'], 401);
            }
        }
        $withMessages = $request->boolean('with_messages');

        if ($user) {
            $query = ChatSession::where('user_id', $user->id)->orderByDesc('updated_at')->limit(10);
        } else {
            $guestSession = $this->guestUserService->getOrExtendGuestSessionFromRequest($request);
            if (!$guestSession) {
                return response()->json([]);
            }
            $query = ChatSession::where('guest_session_id', $guestSession->id)->orderByDesc('updated_at')->limit(10);
        }

        $sessions = $query->get()->map(function (ChatSession $s) use ($withMessages) {
            $out = [
                'id' => $s->id,
                'title' => $s->title ?? 'Chat',
                'created_at' => $s->created_at?->toIso8601String(),
                'updated_at' => $s->updated_at?->toIso8601String(),
                'is_guest' => (bool) $s->is_guest,
                'user_id' => $s->user_id,
            ];
            if ($withMessages) {
                $out['messages'] = $s->messages()->orderBy('created_at')->get()->map(fn (ChatMessage $m) => $this->messageToArray($m))->values()->all();
            }
            return $out;
        });

        return response()->json($sessions->all());
    }

    /**
     * GET /api/v1/{region}/chat/messages/{sessionId}
     * When session does not exist yet (e.g. new chat before first message), return empty array so frontend does not get 404.
     */
    public function messages(Request $request, string $sessionId)
    {
        $session = $this->resolveSession($request, $sessionId);
        if (!$session) {
            return response()->json([]);
        }

        $messages = $session->messages()->orderBy('created_at')->get()->map(fn (ChatMessage $m) => $this->messageToArray($m))->values()->all();

        return response()->json($messages);
    }

    /**
     * PUT /api/v1/{region}/chat/sessions/{sessionId}
     */
    public function rename(Request $request, string $sessionId)
    {
        $session = $this->resolveSession($request, $sessionId);
        if (!$session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        $request->validate(['title' => 'required|string|max:255']);
        $session->update(['title' => $request->input('title')]);

        return response()->json(['message' => 'Session renamed.', 'session' => [
            'id' => $session->id,
            'title' => $session->title,
        ]]);
    }

    /**
     * DELETE /api/v1/{region}/chat/sessions/{sessionId}
     */
    public function destroy(Request $request, string $sessionId)
    {
        $session = $this->resolveSession($request, $sessionId);
        if (!$session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        $session->messages()->delete();
        $session->delete();

        return response()->json(['message' => 'Session deleted.']);
    }

    /**
     * PATCH /api/v1/{region}/chat/messages/{messageId} - Update message metadata (e.g. tool result payload).
     */
    public function updateMessage(Request $request, string $sessionId, string $messageId)
    {
        $session = $this->resolveSession($request, $sessionId);
        if (!$session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }
        $message = ChatMessage::where('id', $messageId)->where('chat_session_id', $session->id)->first();
        if (!$message || $message->role !== 'assistant') {
            return response()->json(['error' => 'Message not found.'], 404);
        }
        $request->validate([
            'metadata' => 'required|array',
            'metadata.reply_type' => 'required|string|in:presentation_outline,presentation_content,presentation_export,diagram,doc_converter',
            'metadata.payload' => 'nullable|array',
        ]);
        $meta = $request->input('metadata');
        $newMeta = array_merge($message->metadata ?? [], [
            'reply_type' => $meta['reply_type'],
            'payload' => $meta['payload'] ?? null,
        ]);
        if (!empty($message->metadata['job_id'])) {
            $newMeta['job_id'] = $message->metadata['job_id'];
        }
        $message->update(['metadata' => $newMeta]);

        $cost = (int) config('tools.usage.presentation.outline_cost', 500);
        if ($meta['reply_type'] === 'presentation_content') {
            $cost = (int) config('tools.usage.presentation.content_cost', 500);
        } elseif ($meta['reply_type'] === 'presentation_export') {
            $cost = (int) config('tools.usage.presentation.export_cost', 1000);
        } elseif ($meta['reply_type'] === 'diagram') {
            $cost = (int) config('tools.usage.diagram.cost', 300);
        } elseif ($meta['reply_type'] === 'doc_converter') {
            $cost = (int) config('tools.usage.doc_converter.cost', 200);
        }
        $user = $request->user();
        $guestSession = !$user && $session->guest_session_id ? $session->guestSession : null;
        $toolSource = match ($meta['reply_type'] ?? '') {
            'diagram' => 'diagram',
            'doc_converter' => 'doc_converter',
            default => 'presentation',
        };
        try {
            $this->usageMeterService->record($cost, $toolSource, $user, $guestSession, $session->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AiChatHistory] Usage recording failed on message update', [
                'message_id' => $messageId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Updated.', 'metadata' => $newMeta]);
    }

    private function messageToArray(ChatMessage $m): array
    {
        $out = [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'is_attachment' => $m->is_attachment,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
        $meta = $m->metadata;
        if (is_array($meta) && !empty($meta['reply_type'])) {
            $out['reply_type'] = $meta['reply_type'];
            if (isset($meta['job_id'])) {
                $out['job_id'] = $meta['job_id'];
            }
            if (isset($meta['payload'])) {
                $out['payload'] = $meta['payload'];
            }
        }
        return $out;
    }

    /**
     * Resolve user from request. Chat routes have no auth middleware; when Bearer token is sent, authenticate via JWT.
     * When Bearer is present but invalid/expired, throws 401 so we do not fall back to guest.
     */
    private function resolveUser(Request $request): ?\App\Models\User
    {
        $user = $request->user();
        if (!$user && $request->bearerToken()) {
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[Chat] JWT invalid or expired', ['message' => $e->getMessage()]);
                throw new HttpResponseException(
                    response()->json(['error' => 'Unauthorized', 'message' => 'Token invalid or expired'], 401)
                );
            }
        }
        return $user;
    }

    private function resolveSession(Request $request, string $sessionId): ?ChatSession
    {
        $user = $this->resolveUser($request);
        if ($user) {
            $session = ChatSession::where('id', $sessionId)->where('user_id', $user->id)->first();
            if ($session) {
                return $session;
            }
            // Continue existing chat: same fallback as POST /chat (e.g. type coercion)
            $existing = ChatSession::find($sessionId);
            if ($existing && (int) $existing->user_id === (int) $user->id) {
                return $existing;
            }
            return null;
        }
        $guestSession = $this->guestUserService->getOrExtendGuestSessionFromRequest($request);
        if (!$guestSession) {
            return null;
        }
        $session = ChatSession::where('id', $sessionId)->where('guest_session_id', $guestSession->id)->first();
        if ($session) {
            return $session;
        }
        $existing = ChatSession::find($sessionId);
        if ($existing && $existing->guest_session_id !== null) {
            if ((int) $existing->guest_session_id === (int) $guestSession->id) {
                return $existing;
            }
            // Same device, different guest_session_id: reassign so GET /messages shows history (same as POST /chat).
            $currentFingerprint = $this->guestUserService->generateDeviceFingerprint($request);
            $oldGuest = $existing->guestSession;
            if ($oldGuest && $oldGuest->device_fingerprint === $currentFingerprint) {
                $existing->update(['guest_session_id' => $guestSession->id]);
                return $existing->fresh();
            }
        }
        return null;
    }
}
