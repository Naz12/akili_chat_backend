<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatSessionShare;
use App\Models\ChatMessage;
use App\Models\ChatSessionDoc;
use App\Models\User;
use App\Notifications\ChatSessionSharedNotification;
use App\Services\Notification\NotificationService;
use App\Traits\DetectsRegion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatSessionShareController extends Controller
{
    use DetectsRegion;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Share a chat session with another user
     * 
     * POST /chat/sessions/{sessionId}/share
     */
    public function share(Request $request, string $sessionId)
    {
        $user = $request->user();

        $request->validate([
            'email' => 'required_without:user_id|email|exists:users,email',
            'user_id' => 'required_without:email|exists:users,id',
            'message' => 'nullable|string|max:500',
        ]);

        // Validate session belongs to user
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Get user by email or user_id
        if ($request->has('email')) {
            $sharedToUser = User::where('email', $request->input('email'))->firstOrFail();
            $sharedToUserId = $sharedToUser->id;
        } else {
            $sharedToUserId = $request->input('user_id');
            $sharedToUser = User::findOrFail($sharedToUserId);
        }

        // Prevent self-sharing
        if ($sharedToUserId == $user->id) {
            throw ValidationException::withMessages([
                $request->has('email') ? 'email' : 'user_id' => ['You cannot share a session with yourself.'],
            ]);
        }

        // Check for existing pending share
        $existingPendingShare = ChatSessionShare::where('original_chat_session_id', $sessionId)
            ->where('shared_to_user_id', $sharedToUserId)
            ->where('status', 'pending')
            ->first();

        if ($existingPendingShare) {
            return response()->json([
                'message' => 'This session has already been shared with this user and is pending acceptance.',
                'share' => $existingPendingShare->load(['sharedBy', 'sharedTo', 'originalSession']),
            ], 409);
        }

        // Check if already accepted (can't share again if already accepted)
        $existingAcceptedShare = ChatSessionShare::where('original_chat_session_id', $sessionId)
            ->where('shared_to_user_id', $sharedToUserId)
            ->where('status', 'accepted')
            ->first();

        if ($existingAcceptedShare) {
            return response()->json([
                'message' => 'This session has already been accepted by this user.',
                'share' => $existingAcceptedShare->load(['sharedBy', 'sharedTo', 'originalSession', 'duplicatedSession']),
            ], 409);
        }

        // Create share record
        $share = ChatSessionShare::create([
            'shared_by_user_id' => $user->id,
            'shared_to_user_id' => $sharedToUserId,
            'original_chat_session_id' => $sessionId,
            'status' => 'pending',
            'message' => $request->input('message'),
        ]);

        // Load relationships for response
        $share->load(['sharedBy', 'sharedTo', 'originalSession']);

        // Send notification using the notification service
        try {
            $notification = new ChatSessionSharedNotification($share);
            $this->notificationService->send($sharedToUser, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to send share notification', [
                'share_id' => $share->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the request if notification fails
        }

        Log::info('Chat session shared', [
            'share_id' => $share->id,
            'shared_by' => $user->id,
            'shared_to' => $sharedToUserId,
            'session_id' => $sessionId,
        ]);

        return response()->json([
            'message' => 'Chat session shared successfully.',
            'share' => $share,
        ], 201);
    }

    /**
     * Accept a shared chat session
     * 
     * POST /chat/shares/{shareId}/accept
     */
    public function accept(Request $request, int $shareId)
    {
        $user = $request->user();

        $share = ChatSessionShare::where('id', $shareId)
            ->where('shared_to_user_id', $user->id)
            ->where('status', 'pending')
            ->with(['originalSession.messages', 'originalSession.docs'])
            ->firstOrFail();

        // Check if already accepted
        if ($share->isAccepted()) {
            return response()->json([
                'message' => 'This share has already been accepted.',
                'share' => $share->load(['duplicatedSession']),
                'session_id' => $share->duplicated_chat_session_id,
            ]);
        }

        DB::beginTransaction();
        try {
            // Duplicate the chat session
            $originalSession = $share->originalSession;
            $newSession = ChatSession::create([
                'user_id' => $user->id,
                'title' => $originalSession->title . ' (Shared)',
            ]);

            // Duplicate all messages
            foreach ($originalSession->messages as $message) {
                ChatMessage::create([
                    'user_id' => $message->user_id, // Keep original user_id for attribution
                    'chat_session_id' => $newSession->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'is_attachment' => $message->is_attachment,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                ]);
            }

            // Duplicate all session docs
            foreach ($originalSession->docs as $doc) {
                ChatSessionDoc::create([
                    'chat_session_id' => $newSession->id,
                    'doc_id' => $doc->doc_id,
                    'provider' => $doc->provider,
                ]);
            }

            // Update share record
            $share->update([
                'duplicated_chat_session_id' => $newSession->id,
                'status' => 'accepted',
            ]);

            // Update existing notification to include duplicated session ID
            $notification = $user->notifications()
                ->where('type', \App\Notifications\ChatSessionSharedNotification::class)
                ->whereRaw("JSON_EXTRACT(data, '$.share_id') = ?", [$share->id])
                ->first();
            
            if ($notification) {
                // Decode JSON data, update, and re-encode
                $data = is_string($notification->data) ? json_decode($notification->data, true) : $notification->data;
                $data['share_status'] = 'accepted';
                $data['duplicated_session_id'] = $newSession->id;
                $data['action_url'] = "/chat/{$newSession->id}";
                $notification->update(['data' => $data]);
            }

            DB::commit();

            Log::info('Chat session share accepted', [
                'share_id' => $share->id,
                'original_session_id' => $originalSession->id,
                'new_session_id' => $newSession->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Chat session accepted and duplicated successfully.',
                'share' => $share->load(['duplicatedSession']),
                'session_id' => $newSession->id,
                'session' => $newSession,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept chat session share', [
                'share_id' => $shareId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to accept chat session share.',
            ], 500);
        }
    }

    /**
     * Decline a shared chat session
     * 
     * POST /chat/shares/{shareId}/decline
     */
    public function decline(Request $request, int $shareId)
    {
        $user = $request->user();

        $share = ChatSessionShare::where('id', $shareId)
            ->where('shared_to_user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $share->update(['status' => 'declined']);

        Log::info('Chat session share declined', [
            'share_id' => $shareId,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Chat session share declined.',
            'share' => $share->load(['originalSession']),
        ]);
    }

    /**
     * Get incoming shares (shares received by current user)
     * 
     * GET /chat/shares/incoming
     */
    public function incoming(Request $request)
    {
        $user = $request->user();

        $shares = ChatSessionShare::where('shared_to_user_id', $user->id)
            ->with(['sharedBy', 'originalSession', 'duplicatedSession'])
            ->latest()
            ->get();

        return response()->json([
            'shares' => $shares,
        ]);
    }

    /**
     * Get outgoing shares (shares sent by current user)
     * 
     * GET /chat/shares/outgoing
     */
    public function outgoing(Request $request)
    {
        $user = $request->user();

        $shares = ChatSessionShare::where('shared_by_user_id', $user->id)
            ->with(['sharedTo', 'originalSession', 'duplicatedSession'])
            ->latest()
            ->get();

        return response()->json([
            'shares' => $shares,
        ]);
    }

    /**
     * Get a specific share
     * 
     * GET /chat/shares/{shareId}
     */
    public function show(Request $request, int $shareId)
    {
        $user = $request->user();

        $share = ChatSessionShare::where('id', $shareId)
            ->where(function ($query) use ($user) {
                $query->where('shared_by_user_id', $user->id)
                      ->orWhere('shared_to_user_id', $user->id);
            })
            ->with(['sharedBy', 'sharedTo', 'originalSession', 'duplicatedSession'])
            ->firstOrFail();

        return response()->json([
            'share' => $share,
        ]);
    }
}

