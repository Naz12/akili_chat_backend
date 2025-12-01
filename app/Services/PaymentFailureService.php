<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Bill;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentFailureService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle payment failure for a subscription
     */
    public function handlePaymentFailure(Subscription $subscription, string $reason = 'Payment failed'): void
    {
        DB::transaction(function () use ($subscription, $reason) {
            // Set grace period (3 days, configurable)
            $gracePeriodDays = config('subscriptions.grace_period_days', 3);
            $gracePeriodEndsAt = now()->addDays($gracePeriodDays);
            
            $subscription->update([
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'payment_failure_count' => ($subscription->payment_failure_count ?? 0) + 1,
            ]);

            $plan = $subscription->plan;
            $paymentMethod = $subscription->metadata['payment_method'] ?? 'stripe';

            // Create bill for manual payment (if not already exists)
            $existingBill = Bill::where('subscription_id', $subscription->id)
                ->where('type', 'renewal_failed')
                ->where('status', 'pending')
                ->first();

            if (!$existingBill && $plan->monthly_price > 0) {
                Bill::create([
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'type' => 'renewal_failed',
                    'status' => 'pending',
                    'amount' => $plan->monthly_price,
                    'currency' => $plan->currency ?? ($paymentMethod === 'chapa' ? 'ETB' : 'USD'),
                    'due_date' => $gracePeriodEndsAt,
                    'description' => "Payment failed for {$plan->name}. Please pay manually to continue.",
                    'metadata' => [
                        'plan_id' => $plan->id,
                        'plan_name' => $plan->name,
                        'payment_method' => $paymentMethod,
                        'reason' => $reason,
                        'grace_period_ends_at' => $gracePeriodEndsAt->toIso8601String(),
                    ],
                ]);

                Log::info('Bill created for payment failure', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                ]);
            }

            Log::info('Payment failure handled - grace period set', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'failure_count' => $subscription->payment_failure_count,
            ]);

            // Send notification to user (will be implemented with notification system)
            // For now, just log
            Log::info('Payment failure notification should be sent', [
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
            ]);
        });
    }

    /**
     * Process expired grace periods - downgrade to free plan
     */
    public function processExpiredGracePeriods(): array
    {
        $results = [
            'processed' => 0,
            'downgraded' => 0,
            'errors' => 0,
        ];

        // Find subscriptions with expired grace periods
        $expiredGracePeriods = Subscription::where('is_active', true)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->with(['user', 'plan'])
            ->get();

        Log::info('Processing expired grace periods', [
            'count' => $expiredGracePeriods->count(),
        ]);

        foreach ($expiredGracePeriods as $subscription) {
            try {
                $this->downgradeToFreePlan($subscription);
                $results['downgraded']++;
                $results['processed']++;
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error('Failed to downgrade subscription after grace period', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Downgrade subscription to free plan
     */
    protected function downgradeToFreePlan(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $user = $subscription->user;
            $region = $user->region ?? 'local';

            // Find default free plan
            $freePlan = Plan::where('region', $region)
                ->where('is_default', true)
                ->where('is_active', true)
                ->where('monthly_price', 0)
                ->first();

            if (!$freePlan) {
                throw new \Exception("No free plan found for region: {$region}");
            }

            // Deactivate current subscription
            $subscription->update([
                'is_active' => false,
                'grace_period_ends_at' => null,
            ]);

            // Create new free subscription
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $freePlan->id,
                'start_date' => now(),
                'end_date' => now()->addDays(30),
                'tokens_used' => 0,
                'is_active' => true,
                'auto_renew' => false,
            ]);

            Log::info('Subscription downgraded to free plan', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'free_plan_id' => $freePlan->id,
            ]);

            // Send notification about downgrade (will be implemented)
            Log::info('Downgrade notification should be sent', [
                'user_id' => $user->id,
            ]);
        });
    }
}

