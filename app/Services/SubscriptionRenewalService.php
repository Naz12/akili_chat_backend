<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Bill;
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
    public function processRenewals(?int $daysAhead = null): array
    {
        // Get from system settings if not provided
        if ($daysAhead === null) {
            $daysAhead = \App\Models\SystemSetting::getValue('subscription.renewal_days_ahead', 3);
        }
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

        // Get payment method from subscription metadata
        $paymentMethod = $subscription->metadata['payment_method'] ?? null;
        
        // For Chapa (manual renewal), create a bill for user to pay
        if ($paymentMethod === 'chapa' || $paymentMethod === 'telebirr') {
            return $this->createManualRenewalBill($subscription);
        }

        // For Stripe, check if we have Stripe Subscription (automatic renewal)
        // If not, create manual renewal bill as fallback
        $stripeSubscriptionId = $subscription->metadata['stripe_subscription_id'] ?? null;
        
        if ($paymentMethod === 'stripe' && $stripeSubscriptionId) {
            // Stripe handles automatic renewal via webhooks
            // If we reach here, it means automatic renewal might have failed
            // Create a manual renewal bill as fallback
            return $this->createManualRenewalBill($subscription);
        }

        // Default: create manual renewal bill
        return $this->createManualRenewalBill($subscription);
    }

    /**
     * Create a bill for manual renewal (Chapa or Stripe fallback)
     */
    protected function createManualRenewalBill(Subscription $subscription): array
    {
        try {
            // Check if bill already exists for this subscription
            $existingBill = Bill::where('subscription_id', $subscription->id)
                ->where('type', 'renewal')
                ->where('status', 'pending')
                ->first();

            if ($existingBill) {
                Log::info('Bill already exists for subscription renewal', [
                    'subscription_id' => $subscription->id,
                    'bill_id' => $existingBill->id,
                ]);

                return [
                    'success' => true,
                    'message' => 'Renewal bill already exists',
                    'bill_id' => $existingBill->id,
                ];
            }

            $plan = $subscription->plan;
            $paymentMethod = $subscription->metadata['payment_method'] ?? 'chapa';

            $bill = Bill::create([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'type' => 'renewal',
                'status' => 'pending',
                'amount' => $plan->monthly_price,
                'currency' => $plan->currency ?? ($paymentMethod === 'chapa' ? 'ETB' : 'USD'),
                'due_date' => $subscription->end_date,
                'description' => "Renewal for {$plan->name}",
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'payment_method' => $paymentMethod,
                    'billing_cycle' => $plan->billing_cycle ?? 'monthly',
                ],
            ]);

            Log::info('Manual renewal bill created', [
                'bill_id' => $bill->id,
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'amount' => $bill->amount,
                'due_date' => $bill->due_date,
            ]);

            return [
                'success' => true,
                'message' => 'Renewal bill created. User can pay manually.',
                'bill_id' => $bill->id,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create manual renewal bill', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'reason' => $e->getMessage(),
            ];
        }
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
                $freeDurationDays = \App\Models\SystemSetting::getValue('subscription.free_duration_days', 30);
                Subscription::create([
                    'user_id' => $subscription->user_id,
                    'plan_id' => $subscription->plan_id,
                    'start_date' => $subscription->end_date,
                    'end_date' => $subscription->end_date->copy()->addDays($freeDurationDays),
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

