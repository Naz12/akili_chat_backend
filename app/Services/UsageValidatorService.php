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

        // 3.  Daily-message-limit check
        $plan = $subscription->plan;
        if ($plan->daily_message_limit) {
            $usedToday = TokenUsage::where('user_id', $user->id)
                ->whereDate('created_at', Carbon::today())
                ->count();            // count messages, not tokens

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