<?php

namespace App\Services;

use App\Models\GuestSession;
use App\Models\Plan;
use App\Models\TokenUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records tool usage (token-equivalent) for subscription/guest metering.
 * Call after a tool job completes successfully.
 */
class UsageMeterService
{
    public function __construct(
        protected GuestUserService $guestUserService
    ) {}

    /**
     * Record token-equivalent usage for a tool action.
     *
     * @param int $tokensUsed Token cost (e.g. from config('tools.usage.presentation.outline_cost'))
     * @param string $source 'presentation' | 'diagram' | 'doc_converter'
     * @param User|null $user Authenticated user or null for guest
     * @param GuestSession|null $guestSession Required when user is null
     * @param string|null $chatSessionId Optional; used for TokenUsage and guest session scope
     */
    public function record(
        int $tokensUsed,
        string $source,
        ?User $user = null,
        ?GuestSession $guestSession = null,
        ?string $chatSessionId = null
    ): bool {
        if ($tokensUsed <= 0) {
            return true;
        }

        if ($user) {
            return $this->recordForUser($user, $tokensUsed, $source, $chatSessionId);
        }

        if ($guestSession) {
            return $this->recordForGuest($guestSession, $tokensUsed, $source, $chatSessionId);
        }

        return false;
    }

    private function recordForUser(User $user, int $tokensUsed, string $source, ?string $chatSessionId): bool
    {
        $subscription = $user->subscriptions()
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now())
            ->orderByDesc('end_date')
            ->first();

        if (!$subscription) {
            return false;
        }

        DB::transaction(function () use ($user, $subscription, $tokensUsed, $source, $chatSessionId) {
            TokenUsage::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'chat_session_id' => $chatSessionId,
                'tokens_used' => $tokensUsed,
                'source' => $source,
                'prompt' => null,
                'response' => null,
                'cost' => 0,
            ]);

            $subscription->increment('tokens_used', $tokensUsed);
        });

        return true;
    }

    private function recordForGuest(GuestSession $guestSession, int $tokensUsed, string $source, ?string $chatSessionId): bool
    {
        DB::transaction(function () use ($guestSession, $tokensUsed, $source, $chatSessionId) {
            TokenUsage::create([
                'user_id' => null,
                'subscription_id' => null,
                'chat_session_id' => $chatSessionId,
                'tokens_used' => $tokensUsed,
                'source' => $source,
                'prompt' => null,
                'response' => null,
                'cost' => 0,
            ]);

            $metadata = $guestSession->metadata ?? [];
            $metadata['total_tokens_used'] = (int)($metadata['total_tokens_used'] ?? 0) + $tokensUsed;
            $guestSession->update(['metadata' => $metadata]);
        });

        return true;
    }

    /**
     * Get token cost for a tool action from config.
     */
    public static function costFor(string $tool, string $action = 'default'): int
    {
        if ($action === 'default') {
            return (int) config("tools.usage.{$tool}.cost", 0);
        }
        return (int) config("tools.usage.{$tool}.{$action}_cost", 0);
    }
}
