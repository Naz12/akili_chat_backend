<?php

namespace App\Services;

use App\Models\Payment;
use App\Services\Payment\PaymentManager;
use App\Jobs\RetryFailedPayment;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentRetryService
{
    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Schedule retry for failed payment
     */
    public function scheduleRetry(Payment $payment): void
    {
        $retryCount = $payment->metadata['retry_count'] ?? 0;
        
        // Maximum 3 retries
        if ($retryCount >= 3) {
            Log::info('Payment retry limit reached, triggering grace period', [
                'payment_id' => $payment->id,
                'retry_count' => $retryCount,
            ]);
            
            // Trigger grace period for associated subscription
            if (isset($payment->metadata['subscription_id'])) {
                $subscription = \App\Models\Subscription::find($payment->metadata['subscription_id']);
                if ($subscription) {
                    $paymentFailureService = app(\App\Services\PaymentFailureService::class);
                    $paymentFailureService->handlePaymentFailure($subscription, 'Payment retry limit exceeded');
                }
            }
            
            return;
        }
        
        // Schedule retry based on retry count
        $delayMinutes = match($retryCount) {
            0 => 24 * 60,      // 1 day
            1 => 3 * 24 * 60,  // 3 days
            2 => 7 * 24 * 60,  // 7 days
            default => 24 * 60,
        };
        
        // Update metadata with retry count
        $metadata = $payment->metadata ?? [];
        $metadata['retry_count'] = $retryCount + 1;
        $metadata['next_retry_at'] = now()->addMinutes($delayMinutes)->toIso8601String();
        $payment->update(['metadata' => $metadata]);
        
        // Dispatch retry job
        RetryFailedPayment::dispatch($payment)->delay(now()->addMinutes($delayMinutes));
        
        Log::info('Payment retry scheduled', [
            'payment_id' => $payment->id,
            'retry_count' => $retryCount + 1,
            'retry_at' => now()->addMinutes($delayMinutes)->toIso8601String(),
        ]);
    }

    /**
     * Retry a failed payment
     */
    public function retryPayment(Payment $payment): array
    {
        try {
            // Get original payment details
            $user = $payment->user;
            $provider = $payment->provider;
            
            // Create new payment attempt
            $newPayment = $this->paymentManager->initiatePayment($user, [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $provider,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'is_retry' => true,
                    'original_payment_id' => $payment->id,
                    'retry_count' => ($payment->metadata['retry_count'] ?? 0) + 1,
                ]),
                'description' => "Retry payment for {$payment->reference}",
            ]);
            
            Log::info('Payment retry initiated', [
                'original_payment_id' => $payment->id,
                'new_payment_id' => $newPayment->id,
                'retry_count' => $newPayment->metadata['retry_count'] ?? 1,
            ]);
            
            return [
                'success' => true,
                'payment' => $newPayment,
            ];
        } catch (\Exception $e) {
            Log::error('Payment retry failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            
            // Schedule next retry if not at limit
            $retryCount = $payment->metadata['retry_count'] ?? 0;
            if ($retryCount < 3) {
                $this->scheduleRetry($payment);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

