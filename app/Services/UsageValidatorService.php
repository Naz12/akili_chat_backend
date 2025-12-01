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
     *  
     *  For guest users, pass $guestSession and $plan to check limits.
     */
    public function checkQuota($user, bool $onlySubscription = false, $guestSession = null, $plan = null): array
    {
        // Handle guest users (null user) - they use default free plan with limits
        if (!$user && $guestSession && $plan) {
            // For guests, check limits based on guest session and default free plan
            
            // 1. Token quota check (max_tokens)
            if ($plan->max_tokens) {
                // Get total tokens used by this guest session
                // Query TokenUsage records where chat_session belongs to this guest_session
                $tokensUsed = \App\Models\TokenUsage::whereNull('user_id')
                    ->whereNull('subscription_id')
                    ->whereHas('chatSession', function($q) use ($guestSession) {
                        $q->where('guest_session_id', $guestSession->id);
                    })
                    ->sum('tokens_used');
                
                // Also check metadata as fallback (in case TokenUsage wasn't created yet)
                $metadataTokens = (int)($guestSession->metadata['total_tokens_used'] ?? 0);
                $tokensUsed = max((int)$tokensUsed, $metadataTokens);
                
                $maxTokens = $plan->max_tokens;
                
                if ($tokensUsed >= $maxTokens) {
                    return [
                        'error'   => true,
                        'message' => "You've reached your token quota ({$tokensUsed}/{$maxTokens}). "
                                   . 'Please sign up to continue using AI. 😊',
                    ];
                }
            }
            
            // 2. Daily message limit check
            if ($plan->daily_message_limit) {
                // Count messages from guest session today
                $usedToday = \App\Models\ChatMessage::where('guest_session_id', $guestSession->id)
                    ->whereDate('created_at', \Carbon\Carbon::today())
                    ->where('role', 'user') // Only count user messages
                    ->count();
                
                if ($usedToday >= $plan->daily_message_limit) {
                    return [
                        'error'   => true,
                        'message' => "You've reached your daily message limit ({$usedToday}/{$plan->daily_message_limit}). "
                                   . 'Try again tomorrow or sign up for more. 😊',
                    ];
                }
            }
            
            return ['error' => false, 'message' => '✅ Guest user - quota OK'];
        }
        
        // Legacy: Handle guest users without session/plan (backward compatibility)
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
            // Count messages sent after the current subscription started
            // This ensures the count resets when a plan changes
            $usedToday = \App\Models\ChatMessage::where('user_id', $user->id)
                ->where('created_at', '>=', $subscription->start_date) // Only count messages after subscription started
                ->whereDate('created_at', Carbon::today()) // Still within today
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