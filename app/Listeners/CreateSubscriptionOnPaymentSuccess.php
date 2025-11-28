<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class CreateSubscriptionOnPaymentSuccess
{
    /**
     * Handle the event.
     */
    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment;
        $metadata = $payment->metadata ?? [];

        // Only create subscription if plan_id is in metadata
        if (!isset($metadata['plan_id'])) {
            Log::info('Payment succeeded but no plan_id in metadata', [
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

            // Create new active subscription
            $subscription = Subscription::create([
                'user_id' => $userId,
                'plan_id' => $planId,
                'start_date' => now(),
                'end_date' => now()->addDays(30),
                'tokens_used' => 0,
                'is_active' => true,
                'auto_renew' => false,
                'tx_ref' => $payment->reference,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->reference,
                    'provider' => $payment->provider,
                ],
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
}
