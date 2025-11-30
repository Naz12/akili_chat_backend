<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Plan;
use App\Services\Payment\PaymentManager;
use App\Jobs\ProcessRenewalPayment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SubscriptionRenewalService
{
    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Process renewals for subscriptions expiring in the next N days
     */
    public function processRenewals(int $daysAhead = 3): array
    {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // Find subscriptions with auto_renew = true expiring in N days
        $expiringSubscriptions = Subscription::where('is_active', true)
            ->where('auto_renew', true)
            ->whereDate('end_date', '<=', now()->addDays($daysAhead))
            ->whereDate('end_date', '>=', now())
            ->with(['user', 'plan'])
            ->get();

        Log::info('Processing subscription renewals', [
            'count' => $expiringSubscriptions->count(),
            'days_ahead' => $daysAhead,
        ]);

        foreach ($expiringSubscriptions as $subscription) {
            try {
                $result = $this->renewSubscription($subscription);
                
                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    Log::warning('Subscription renewal failed', [
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'reason' => $result['reason'] ?? 'Unknown',
                    ]);
                }
                
                $results['processed']++;
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Exception during subscription renewal', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Renew a single subscription
     */
    public function renewSubscription(Subscription $subscription): array
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        // Check if plan is free (no payment required)
        if ($plan->monthly_price == 0) {
            return $this->renewFreeSubscription($subscription);
        }

        // For paid plans, check if payment method exists
        // For now, we'll dispatch a job to handle payment
        // In the future, this could check for saved payment methods
        ProcessRenewalPayment::dispatch($subscription);

        return [
            'success' => true,
            'message' => 'Renewal payment job dispatched',
        ];
    }

    /**
     * Renew a free subscription (no payment required)
     */
    protected function renewFreeSubscription(Subscription $subscription): array
    {
        try {
            DB::transaction(function () use ($subscription) {
                // Deactivate old subscription
                $subscription->update(['is_active' => false]);

                // Create new subscription
                Subscription::create([
                    'user_id' => $subscription->user_id,
                    'plan_id' => $subscription->plan_id,
                    'start_date' => $subscription->end_date,
                    'end_date' => $subscription->end_date->copy()->addDays(30), // Default to 30 days
                    'tokens_used' => 0,
                    'is_active' => true,
                    'auto_renew' => $subscription->auto_renew,
                ]);
            });

            Log::info('Free subscription renewed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);

            return [
                'success' => true,
                'message' => 'Free subscription renewed successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to renew free subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'reason' => $e->getMessage(),
            ];
        }
    }
}

