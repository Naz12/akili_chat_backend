<?php

namespace App\Http\Controllers\Admin;

use App\Services\Payment\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Webhook;

/**
 * Legacy Stripe Webhook Controller
 * 
 * This controller is kept for backward compatibility.
 * New webhooks should use PaymentWebhookController.
 * 
 * @deprecated Use PaymentWebhookController instead
 */
class StripeWebhookController extends Controller
{
    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Save raw webhook to database
        try {
            Webhook::create([
                'provider' => 'stripe',
                'event_type' => 'legacy_webhook',
                'signature' => $signature,
                'payload' => json_decode($payload, true) ?: [],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to save webhook log', ['error' => $e->getMessage()]);
        }

        try {
            // Use PaymentManager to process webhook
            // This will update payment status and trigger subscription creation via event
            $this->paymentManager->processWebhook('stripe', $payload, $signature);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('❌ Stripe Webhook processing failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
            ]);

            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
