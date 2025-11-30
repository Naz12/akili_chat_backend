<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Services\PaymentRetryService;
use App\Services\PaymentFailureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandlePaymentFailure implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment;
        
        Log::info('Payment failure event received', [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
        ]);
        
        // Schedule payment retry
        $retryService = app(PaymentRetryService::class);
        $retryService->scheduleRetry($payment);
        
        // If payment is associated with a subscription, handle failure
        $metadata = $payment->metadata ?? [];
        if (isset($metadata['subscription_id'])) {
            $subscription = \App\Models\Subscription::find($metadata['subscription_id']);
            if ($subscription) {
                $failureService = app(PaymentFailureService::class);
                $failureService->handlePaymentFailure($subscription, 'Payment failed');
            }
        }
    }
}
