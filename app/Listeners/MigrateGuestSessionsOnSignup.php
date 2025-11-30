<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\GuestUserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MigrateGuestSessionsOnSignup
{
    protected GuestUserService $guestUserService;

    public function __construct(GuestUserService $guestUserService)
    {
        $this->guestUserService = $guestUserService;
    }

    /**
     * Handle the event - migrate guest sessions when user registers
     */
    public function handle(Registered $event): void
    {
        $this->migrateGuestSessions($event->user, request());
    }

    /**
     * Migrate guest sessions to user account (can be called from login too)
     */
    public static function migrateGuestSessionsForUser(User $user, $request): int
    {
        $guestUserService = app(GuestUserService::class);
        $fingerprint = $guestUserService->generateDeviceFingerprint($request);
        
        $guestSession = $guestUserService->getGuestSessionByFingerprint($fingerprint);
        
        if (!$guestSession) {
            Log::info('No guest session found for migration', [
                'user_id' => $user->id,
                'fingerprint' => substr($fingerprint, 0, 8) . '...',
            ]);
            return 0;
        }

        return DB::transaction(function () use ($user, $guestSession) {
            // Get all guest chat sessions - include sessions that might not have is_guest flag set
            $guestSessions = ChatSession::where('guest_session_id', $guestSession->id)
                ->whereNull('user_id') // Only migrate sessions that don't already have a user_id
                ->get();

            Log::info('Found guest sessions to migrate', [
                'user_id' => $user->id,
                'guest_session_id' => $guestSession->id,
                'sessions_found' => $guestSessions->count(),
                'session_ids' => $guestSessions->pluck('id')->toArray(),
            ]);

            $migratedCount = 0;

            foreach ($guestSessions as $chatSession) {
                // Update chat session - set user_id and clear guest_session_id
                $chatSession->update([
                    'user_id' => $user->id,
                    'guest_session_id' => null, // Clear guest_session_id after migration
                    'is_guest' => false,
                ]);

                // Update chat messages - migrate all messages for this session
                $messagesUpdated = ChatMessage::where('chat_session_id', $chatSession->id)
                    ->whereNull('user_id') // Only update messages without user_id
                    ->update([
                        'user_id' => $user->id,
                        'guest_session_id' => null,
                    ]);

                Log::info('Migrated chat session', [
                    'session_id' => $chatSession->id,
                    'user_id' => $user->id,
                    'messages_updated' => $messagesUpdated,
                ]);

                $migratedCount++;
            }

            // Transfer guest usage metadata to user's subscription if needed
            if (isset($guestSession->metadata['total_tokens_used'])) {
                // Optionally transfer token usage to user's subscription
                // This is optional since guests don't have subscriptions
                Log::info('Guest token usage', [
                    'user_id' => $user->id,
                    'guest_tokens' => $guestSession->metadata['total_tokens_used'],
                ]);
            }

            Log::info('Guest sessions migrated', [
                'user_id' => $user->id,
                'guest_session_id' => $guestSession->id,
                'sessions_migrated' => $migratedCount,
            ]);

            return $migratedCount;
        });
    }
}
