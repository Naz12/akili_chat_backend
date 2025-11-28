<?php

namespace App\Http\Controllers\Api;

use App\Services\Payment\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Webhook;

/**
 * Legacy Chapa Webhook Controller
 * 
 * This controller is kept for backward compatibility.
 * New webhooks should use PaymentWebhookController.
 * 
 * @deprecated Use PaymentWebhookController instead
 */
class ChapaWebhookController extends Controller
{
    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    public function handle(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('Chapa-Signature');

        // Save raw webhook
        try {
            Webhook::create([
                'provider' => 'chapa',
                'event_type' => $payload['event'] ?? 'legacy_webhook',
                'signature' => $signature,
                'payload' => $payload,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to save webhook log', ['error' => $e->getMessage()]);
        }

        try {
            // Use PaymentManager to process webhook
            // This will update payment status and trigger subscription creation via event
            $this->paymentManager->processWebhook('chapa', $payload, $signature);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('❌ Chapa Webhook processing failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
            ]);

            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
