<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\Payment\PaymentManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessRenewalPayment implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Subscription $subscription
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentManager $paymentManager): void
    {
        $subscription = $this->subscription->fresh(['user', 'plan']);
        
        // Check if subscription is still active and needs renewal
        if (!$subscription->is_active || !$subscription->auto_renew) {
            Log::info('Subscription no longer needs renewal', [
                'subscription_id' => $subscription->id,
                'is_active' => $subscription->is_active,
                'auto_renew' => $subscription->auto_renew,
            ]);
            return;
        }

        $user = $subscription->user;
        $plan = $subscription->plan;

        // Skip free plans (handled in service)
        if ($plan->monthly_price == 0) {
            return;
        }

        try {
            // For now, we'll create a payment and let the webhook handle subscription activation
            // In a full implementation, you'd check for saved payment methods and use them
            $payment = $paymentManager->initiatePayment($user, [
                'amount' => $plan->monthly_price,
                'currency' => $plan->currency ?? 'USD',
                'provider' => $subscription->payment_method ?? 'stripe', // Use subscription's payment method
                'metadata' => [
                    'plan_id' => $plan->id,
                    'subscription_id' => $subscription->id,
                    'description' => "Renewal for {$plan->name}",
                    'is_renewal' => true,
                ],
                'description' => "Renewal for {$plan->name}",
            ]);

            Log::info('Renewal payment created', [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'payment_reference' => $payment->reference,
            ]);

            // The payment webhook will handle subscription activation when payment succeeds
        } catch (\Exception $e) {
            Log::error('Failed to process renewal payment', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // On failure, trigger grace period logic (will be implemented in PaymentFailureService)
            throw $e; // Let job retry
        }
    }
}
