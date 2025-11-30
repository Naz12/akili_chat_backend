<?php

namespace App\Services;

use App\Models\TokenUsage;
use Carbon\Carbon;

class UsageValidatorService
{
    /**
     *  If $onlySubscription = true  → just verify the user still has an
     *  active, non-expired subscription (used by middleware).
     *  Otherwise                        → also check daily message limit.
     */
    public function checkQuota($user, bool $onlySubscription = false): array
    {
        // Handle guest users (null user) - they use default free plan
        if (!$user) {
            // Guests are allowed but with 24-hour expiration (checked in controller)
            return ['error' => false, 'message' => '✅ Guest user - using default free plan'];
        }

        // 1.  Active + un-expired subscription?
        $subscription = $user->subscriptions()
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now())
            ->orderByDesc('end_date')
            ->first();

        if (!$subscription) {
            return [
                'error'   => true,
                'message' => '⚠️ Your subscription has expired or is inactive. '
                           . 'Please renew to continue using AI.',
            ];
        }

        // 2.  If the caller only wants the subscription test, stop here.
        if ($onlySubscription) {
            return ['error' => false, 'message' => '✅ Active subscription'];
        }

        // 3.  Token quota check (max_tokens)
        $plan = $subscription->plan;
        if ($plan->max_tokens) {
            $tokensUsed = $subscription->tokens_used ?? 0;
            $maxTokens = $plan->max_tokens;
            $percentageUsed = $maxTokens > 0 ? ($tokensUsed / $maxTokens) * 100 : 0;
            
            // Check if quota exceeded
            if ($tokensUsed >= $maxTokens) {
                return [
                    'error'   => true,
                    'message' => "Hi {$user->name}, you've reached your token quota ({$tokensUsed}/{$maxTokens}). "
                               . 'Please upgrade your plan to continue using AI. 😊',
                ];
            }
            
            // Soft limit warning at 80%
            if ($percentageUsed >= 80 && $percentageUsed < 100) {
                // Warning will be sent via notification (handled separately)
                // Just log for now
                \Log::info('Token quota warning threshold reached', [
                    'user_id' => $user->id,
                    'tokens_used' => $tokensUsed,
                    'max_tokens' => $maxTokens,
                    'percentage' => round($percentageUsed, 2),
                ]);
            }
        }

        // 4.  Daily-message-limit check
        if ($plan->daily_message_limit) {
            // Count actual ChatMessage records instead of TokenUsage records
            $usedToday = \App\Models\ChatMessage::where('user_id', $user->id)
                ->whereDate('created_at', Carbon::today())
                ->where('role', 'user') // Only count user messages, not assistant responses
                ->count();

            if ($usedToday >= $plan->daily_message_limit) {
                return [
                    'error'   => true,
                    'message' => "Hi {$user->name}, you've reached your daily "
                               . 'message limit. Try again tomorrow or upgrade '
                               . 'your plan. 😊',
                ];
            }
        }

        return ['error' => false, 'message' => '✅ Quota OK'];
    }
}