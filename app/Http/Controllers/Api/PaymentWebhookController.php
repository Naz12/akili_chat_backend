<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentManager;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Handle Stripe webhooks.
     */
    public function handleStripe(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Extract webhook ID for idempotency (Stripe sends event ID)
        $webhookData = json_decode($payload, true) ?: [];
        $webhookId = $webhookData['id'] ?? $webhookData['data']['id'] ?? null;
        $eventId = $webhookData['type'] ?? $webhookData['event'] ?? null;
        
        // Save raw webhook (check for duplicates first)
        try {
            $webhook = Webhook::firstOrCreate(
                [
                    'provider' => 'stripe',
                    'webhook_id' => $webhookId,
                ],
                [
                    'event_id' => $eventId,
                    'event_type' => $eventId ?? 'payment_webhook',
                    'signature' => $signature,
                    'payload' => $webhookData,
                    'processed' => false,
                ]
            );
            
            // If webhook already exists and was processed, return early
            if ($webhook->processed) {
                Log::info('Stripe webhook already processed', [
                    'webhook_id' => $webhookId,
                    'processed_at' => $webhook->processed_at,
                ]);
                return response()->json(['status' => 'ok', 'message' => 'Webhook already processed'], 200);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to save webhook log', ['error' => $e->getMessage()]);
        }

        try {
            // Stripe needs raw payload string for signature verification
            $payment = $this->paymentManager->processWebhook('stripe', $payload, $signature);
            
            // Mark webhook as processed
            if (isset($webhook)) {
                $webhook->update([
                    'processed' => true,
                    'processed_at' => now(),
                ]);
            }

            // If payment is null, it means webhook was processed but no payment record exists
            // Return 200 to prevent Stripe from retrying
            if ($payment === null) {
                return response()->json(['status' => 'ok', 'message' => 'Webhook processed but no payment record found'], 200);
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
            ]);

            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle Chapa webhooks.
     * Chapa sends webhooks via POST and also redirects users via GET with query params.
     */
    public function handleChapa(Request $request)
    {
        // Handle GET request (return_url redirect with query params OR callback_url notification)
        if ($request->isMethod('GET')) {
            $txRef = $request->query('trx_ref') ?? $request->query('tx_ref');
            $status = $request->query('status');
            $refId = $request->query('ref_id');
            
            Log::info('📥 Chapa GET request received', [
                'tx_ref' => $txRef,
                'status' => $status,
                'ref_id' => $refId,
                'all_params' => $request->all(),
            ]);
            
            // If we have transaction reference, process it
            if ($txRef) {
                try {
                    // Verify payment via API and update status
                    $payment = \App\Models\Payment::where('reference', $txRef)->first();
                    
                    if ($payment) {
                        if ($payment->status === 'pending' && $status === 'success') {
                            // Verify payment status via Chapa API
                            $chapaService = \App\Services\Payment\PaymentServiceFactory::make('chapa');
                            $verification = $chapaService->verifyPayment($txRef);
                            
                            if ($verification['status'] === 'success') {
                                $payment->status = 'success';
                                $payment->transaction_id = $verification['transaction_id'] ?? $refId ?? $payment->transaction_id;
                                $payment->save();
                                
                                // Fire payment succeeded event to create subscription
                                event(new \App\Events\PaymentSucceeded($payment));
                                
                                Log::info('✅ Payment verified and updated from return_url', [
                                    'payment_id' => $payment->id,
                                    'tx_ref' => $txRef,
                                ]);
                            }
                        }
                    } else {
                        Log::warning('⚠️ Payment not found for tx_ref', ['tx_ref' => $txRef]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to verify payment from return_url', [
                        'error' => $e->getMessage(),
                        'tx_ref' => $txRef,
                    ]);
                }
            }
            
            // Get frontend URL from payment metadata if available, otherwise use default
            $frontendUrl = config('app.frontend_url', config('app.url'));
            if ($txRef) {
                // Try to get frontend URL from payment metadata
                $payment = \App\Models\Payment::where('reference', $txRef)->first();
                if ($payment && $payment->metadata) {
                    $metadata = is_string($payment->metadata) ? json_decode($payment->metadata, true) : $payment->metadata;
                    if (isset($metadata['frontend_success_url'])) {
                        $frontendUrl = $metadata['frontend_success_url'];
                    }
                }
                return redirect($frontendUrl . '/payment/success?tx_ref=' . urlencode($txRef));
            } else {
                // If no tx_ref in URL, try to find the most recent pending/successful payment
                // This handles the case where Chapa redirects without params
                try {
                    // Find most recent Chapa payment (likely the one just completed)
                    $recentPayment = \App\Models\Payment::where('provider', 'chapa')
                        ->whereIn('status', ['pending', 'success'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($recentPayment && $recentPayment->created_at->gt(now()->subMinutes(10))) {
                        // Payment was created in last 10 minutes, use its reference
                        $frontendUrl = config('app.frontend_url', config('app.url'));
                        if ($recentPayment->metadata) {
                            $metadata = is_string($recentPayment->metadata) ? json_decode($recentPayment->metadata, true) : $recentPayment->metadata;
                            if (isset($metadata['frontend_success_url'])) {
                                $frontendUrl = $metadata['frontend_success_url'];
                            }
                        }
                        Log::info('✅ Found recent payment, redirecting with tx_ref', [
                            'tx_ref' => $recentPayment->reference,
                        ]);
                        return redirect($frontendUrl . '/payment/success?tx_ref=' . urlencode($recentPayment->reference));
                    }
                    
                    Log::info('⚠️ Chapa redirect without tx_ref - frontend should check recent payments');
                    return redirect($frontendUrl . '/payment/success');
                } catch (\Exception $e) {
                    Log::error('Error handling Chapa redirect without tx_ref', ['error' => $e->getMessage()]);
                    return redirect($frontendUrl . '/payment/success');
                }
            }
        }
        
        // Handle POST request (webhook)
        $payload = $request->all();
        $signature = $request->header('Chapa-Signature');

        // Extract webhook ID for idempotency
        $webhookId = $payload['id'] ?? $payload['webhook_id'] ?? $payload['data']['id'] ?? null;
        $eventId = $payload['event'] ?? $payload['event_id'] ?? null;
        
        // Save raw webhook (check for duplicates first)
        try {
            $webhook = Webhook::firstOrCreate(
                [
                    'provider' => 'chapa',
                    'webhook_id' => $webhookId,
                ],
                [
                    'event_id' => $eventId,
                    'event_type' => $payload['event'] ?? 'payment_webhook',
                    'signature' => $signature,
                    'payload' => $payload,
                    'processed' => false,
                ]
            );
            
            // If webhook already exists and was processed, return early
            if ($webhook->processed) {
                Log::info('Chapa webhook already processed', [
                    'webhook_id' => $webhookId,
                    'processed_at' => $webhook->processed_at,
                ]);
                return response()->json(['status' => 'ok', 'message' => 'Webhook already processed'], 200);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to save webhook log', ['error' => $e->getMessage()]);
        }

        try {
            $payment = $this->paymentManager->processWebhook('chapa', $payload, $signature);
            
            // Mark webhook as processed
            if (isset($webhook)) {
                $webhook->update([
                    'processed' => true,
                    'processed_at' => now(),
                ]);
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error('Chapa webhook processing failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
            ]);

            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
