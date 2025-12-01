<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Bill;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CreateSubscriptionOnPaymentSuccess
{
    /**
     * Handle the event.
     */
    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment;
        $metadata = $payment->metadata ?? [];

        // Check if this payment is for a bill (renewal)
        if (isset($metadata['bill_id'])) {
            $this->handleBillPayment($payment, $metadata['bill_id']);
            return;
        }

        // Only create subscription if plan_id is in metadata (new subscription)
        if (!isset($metadata['plan_id'])) {
            Log::info('Payment succeeded but no plan_id or bill_id in metadata', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        $planId = $metadata['plan_id'];
        $userId = $payment->user_id;

        try {
            $plan = Plan::find($planId);
            if (!$plan) {
                Log::error('Plan not found for payment', [
                    'payment_id' => $payment->id,
                    'plan_id' => $planId,
                ]);
                return;
            }

            // Deactivate previous active subscriptions
            Subscription::where('user_id', $userId)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date' => now(),
                ]);

            // Calculate end_date based on billing cycle
            $billingCycle = $plan->billing_cycle ?? 'monthly';
            $endDate = match($billingCycle) {
                'quarterly' => now()->addMonths(3),
                'annual' => now()->addMonths(12),
                default => now()->addMonths(1),
            };

            // Extract Stripe subscription ID from payment metadata if available
            $paymentMetadata = is_string($payment->metadata) 
                ? json_decode($payment->metadata, true) 
                : ($payment->metadata ?? []);
            $stripeSubscriptionId = $paymentMetadata['stripe_subscription_id'] ?? null;

            // Create subscription metadata
            $subscriptionMetadata = [
                'payment_id' => $payment->id,
                'payment_reference' => $payment->reference,
                'provider' => $payment->provider,
                'payment_method' => $payment->provider,
                'billing_cycle' => $billingCycle,
            ];

            // Add Stripe subscription ID if available (for automatic renewals)
            if ($stripeSubscriptionId) {
                $subscriptionMetadata['stripe_subscription_id'] = $stripeSubscriptionId;
                Log::info('✅ Stripe subscription ID stored in subscription metadata', [
                    'stripe_subscription_id' => $stripeSubscriptionId,
                    'payment_id' => $payment->id,
                ]);
            }

            // Create new active subscription
            $subscription = Subscription::create([
                'user_id' => $userId,
                'plan_id' => $planId,
                'start_date' => now(),
                'end_date' => $endDate,
                'tokens_used' => 0,
                'is_active' => true,
                'auto_renew' => $plan->monthly_price > 0, // Enable auto-renew for paid plans
                'tx_ref' => $payment->reference,
                'metadata' => $subscriptionMetadata,
            ]);

            Log::info('✅ Subscription created from payment', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'user_id' => $userId,
                'plan_id' => $planId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create subscription from payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle payment for a bill (renewal)
     */
    protected function handleBillPayment($payment, $billId): void
    {
        try {
            DB::transaction(function () use ($payment, $billId) {
                $bill = Bill::findOrFail($billId);

                // Mark bill as paid
                $bill->markAsPaid($payment);

                $subscription = $bill->subscription;
                if (!$subscription) {
                    Log::error('Bill has no associated subscription', [
                        'bill_id' => $billId,
                        'payment_id' => $payment->id,
                    ]);
                    return;
                }

                $plan = $subscription->plan;

                // Deactivate ALL active subscriptions for this user (prevent duplicates)
                Subscription::where('user_id', $subscription->user_id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'end_date' => now(),
                    ]);

                // Calculate end_date based on billing cycle
                $billingCycle = $plan->billing_cycle ?? 'monthly';
                $endDate = match($billingCycle) {
                    'quarterly' => now()->addMonths(3),
                    'annual' => now()->addMonths(12),
                    default => now()->addMonths(1),
                };

                // Create new subscription (renewal)
                $newSubscription = Subscription::create([
                    'user_id' => $subscription->user_id,
                    'plan_id' => $plan->id,
                    'start_date' => now(),
                    'end_date' => $endDate,
                    'tokens_used' => 0,
                    'is_active' => true,
                    'auto_renew' => $subscription->auto_renew, // Keep auto_renew setting
                    'tx_ref' => $payment->reference,
                    'metadata' => array_merge($subscription->metadata ?? [], [
                        'payment_id' => $payment->id,
                        'payment_reference' => $payment->reference,
                        'provider' => $payment->provider,
                        'payment_method' => $payment->provider,
                        'billing_cycle' => $billingCycle,
                        'renewed_from_subscription_id' => $subscription->id,
                        'renewed_from_bill_id' => $bill->id,
                    ]),
                ]);

                Log::info('✅ Subscription renewed from bill payment', [
                    'payment_id' => $payment->id,
                    'bill_id' => $billId,
                    'old_subscription_id' => $subscription->id,
                    'new_subscription_id' => $newSubscription->id,
                    'user_id' => $subscription->user_id,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to handle bill payment', [
                'payment_id' => $payment->id,
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
