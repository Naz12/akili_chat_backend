<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChatSession;
use App\Traits\DetectsRegion;
use Illuminate\Support\Facades\Log;

class AiChatHistoryApiController extends Controller
{
    use DetectsRegion;

    /**
     * Get all chat sessions for the authenticated user
     */
    public function sessions(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            
            $withMessages = $request->boolean('with_messages');
        
            $query = ChatSession::with($withMessages ? ['messages' => function ($q) {
                $q->select('id', 'chat_session_id', 'role', 'content', 'created_at')
                  ->orderBy('created_at');
            }] : [])
            ->where('user_id', $user->id)
            ->latest()
            ->take(10); // ✅ Limit to 10 most recent sessions
        
            $sessions = $query->get(['id', 'title', 'created_at']);
        
            return response()->json($sessions);
        } catch (\Throwable $e) {
            Log::error('Chat sessions error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to fetch sessions: ' . $e->getMessage()], 500);
        }
    }
    

    /**
     * Get all messages for a given chat session
     */
    public function messages(Request $request, $sessionId)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $session = ChatSession::where('id', $sessionId)
                ->where('user_id', $user->id)
                ->first();

            if (!$session) {
                return response()->json(['message' => 'Session not found.'], 404);
            }

            $messages = $session->messages()
                ->orderBy('created_at')
                ->get(['id', 'role', 'content', 'created_at']);

            return response()->json($messages);
        } catch (\Throwable $e) {
            Log::error('Chat messages error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'session_id' => $sessionId,
            ]);
            return response()->json(['error' => 'Failed to fetch messages: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Rename a session
     */
    public function rename(Request $request, $sessionId)
    {
        $user = $request->user();

        $request->validate([
            'title' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (trim($value) !== $value) {
                    $fail('Title must not contain leading or trailing spaces.');
                }
            }],
        ]);

        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $session->update(['title' => $request->title]);

        return response()->json([
            'id' => $session->id,
            'title' => $session->title,
            'updated_at' => $session->updated_at,
        ]);
    }

    /**
     * Delete a session and its related messages
     */
    public function destroy(Request $request, $sessionId)
    {
        $user = $request->user();

        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $session->messages()->delete();
        $session->delete();

        Log::info('🗑️ Chat session deleted', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
        ]);

        return response()->json(['message' => 'Session deleted']);
    }
}