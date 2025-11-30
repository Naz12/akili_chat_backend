<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RetryFailedPayment implements ShouldQueue
{
    use Queueable;

    public $tries = 1; // Only try once, retries are scheduled separately

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Payment $payment
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentRetryService $retryService): void
    {
        $payment = $this->payment->fresh();
        
        // Check if payment is still failed
        if ($payment->status !== 'failed') {
            Log::info('Payment no longer failed, skipping retry', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);
            return;
        }
        
        // Retry the payment
        $result = $retryService->retryPayment($payment);
        
        if (!$result['success']) {
            Log::warning('Payment retry failed', [
                'payment_id' => $payment->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        }
    }
}
