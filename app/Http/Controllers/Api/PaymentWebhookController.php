<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentManager;
use App\Models\Webhook;
use App\Models\Bill;
use App\Models\Subscription;
use App\Services\PaymentFailureService;
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
            // Handle Stripe Subscription invoice events (invoice.payment_failed, invoice.payment_succeeded)
            $eventType = $webhookData['type'] ?? null;
            
            if ($eventType === 'invoice.payment_failed') {
                $this->handleStripeInvoicePaymentFailed($webhookData);
                
                // Mark webhook as processed
                if (isset($webhook)) {
                    $webhook->update([
                        'processed' => true,
                        'processed_at' => now(),
                    ]);
                }
                
                return response()->json(['status' => 'ok'], 200);
            }
            
            if ($eventType === 'invoice.payment_succeeded') {
                $this->handleStripeInvoicePaymentSucceeded($webhookData);
                
                // Mark webhook as processed
                if (isset($webhook)) {
                    $webhook->update([
                        'processed' => true,
                        'processed_at' => now(),
                    ]);
                }
                
                return response()->json(['status' => 'ok'], 200);
            }

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
                        // If redirect shows success and we have a ref_id, trust it (Chapa API may have delay)
                        if ($payment->status === 'pending' && ($status === 'success' || $refId)) {
                            // Try to verify via API first, but don't block if redirect says success
                            $verified = false;
                            $transactionId = $refId;
                            
                            try {
                                $chapaService = \App\Services\Payment\PaymentServiceFactory::make('chapa');
                                $verification = $chapaService->verifyPayment($txRef);
                                
                                if ($verification['status'] === 'success') {
                                    $verified = true;
                                    $transactionId = $verification['transaction_id'] ?? $refId ?? $transactionId;
                                    Log::info('✅ Payment verified via Chapa API', [
                                        'payment_id' => $payment->id,
                                        'tx_ref' => $txRef,
                                    ]);
                                } else {
                                    // API says pending but redirect says success - trust redirect if we have ref_id
                                    if ($status === 'success' && $refId) {
                                        $verified = true;
                                        Log::info('✅ Payment confirmed via redirect (API may have delay)', [
                                            'payment_id' => $payment->id,
                                            'tx_ref' => $txRef,
                                            'ref_id' => $refId,
                                            'api_status' => $verification['status'],
                                        ]);
                                    }
                                }
                            } catch (\Exception $e) {
                                // If API verification fails but redirect says success with ref_id, trust redirect
                                if ($status === 'success' && $refId) {
                                    $verified = true;
                                    Log::warning('⚠️ API verification failed but redirect confirms success', [
                                        'payment_id' => $payment->id,
                                        'tx_ref' => $txRef,
                                        'ref_id' => $refId,
                                        'error' => $e->getMessage(),
                                    ]);
                                } else {
                                    Log::error('❌ Payment verification failed', [
                                        'payment_id' => $payment->id,
                                        'tx_ref' => $txRef,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                            
                            if ($verified) {
                                $payment->status = 'success';
                                $payment->transaction_id = $transactionId ?? $payment->transaction_id;
                                $payment->save();
                                
                                // Fire payment succeeded event to create subscription
                                event(new \App\Events\PaymentSucceeded($payment));
                                
                                Log::info('✅ Payment activated and subscription event fired', [
                                    'payment_id' => $payment->id,
                                    'tx_ref' => $txRef,
                                    'transaction_id' => $transactionId,
                                ]);
                            }
                        } elseif ($payment->status === 'success') {
                            Log::info('ℹ️ Payment already activated', [
                                'payment_id' => $payment->id,
                                'tx_ref' => $txRef,
                            ]);
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
        $rawPayload = $request->getContent();
        $payload = $request->all();
        
        // Chapa sends signature in Chapa-Signature or x-chapa-signature header
        $signature = $request->header('Chapa-Signature') 
            ?? $request->header('x-chapa-signature')
            ?? $request->header('X-Chapa-Signature');

        Log::info('📥 Chapa webhook POST received', [
            'event' => $payload['event'] ?? 'unknown',
            'tx_ref' => $payload['tx_ref'] ?? null,
            'status' => $payload['status'] ?? null,
            'has_signature' => !empty($signature),
            'signature_header' => $signature ? substr($signature, 0, 20) . '...' : null,
        ]);

        // Extract webhook ID for idempotency (use tx_ref as unique identifier for Chapa)
        $webhookId = $payload['tx_ref'] ?? $payload['reference'] ?? $payload['id'] ?? null;
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
            // For Chapa, use raw payload for signature verification
            // The PaymentManager will handle the signature verification via ChapaPaymentService
            $payment = $this->paymentManager->processWebhook('chapa', $payload, $signature, $rawPayload);
            
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
                'signature' => $signature ? substr($signature, 0, 20) . '...' : null,
                'tx_ref' => $payload['tx_ref'] ?? null,
            ]);

            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle Stripe invoice.payment_failed event
     * Creates a bill for manual payment when automatic renewal fails
     */
    protected function handleStripeInvoicePaymentFailed(array $webhookData): void
    {
        try {
            $invoice = $webhookData['data']['object'] ?? [];
            $subscriptionId = $invoice['subscription'] ?? null;
            $customerId = $invoice['customer'] ?? null;
            $amount = ($invoice['amount_due'] ?? 0) / 100; // Convert from cents
            $currency = strtoupper($invoice['currency'] ?? 'USD');
            
            if (!$subscriptionId) {
                Log::warning('Stripe invoice.payment_failed: No subscription ID', [
                    'invoice_id' => $invoice['id'] ?? null,
                ]);
                return;
            }

            // Find subscription by Stripe subscription ID
            $subscription = Subscription::whereJsonContains('metadata->stripe_subscription_id', $subscriptionId)
                ->orWhere('metadata->stripe_subscription_id', $subscriptionId)
                ->first();

            if (!$subscription) {
                Log::warning('Stripe invoice.payment_failed: Subscription not found', [
                    'stripe_subscription_id' => $subscriptionId,
                ]);
                return;
            }

            // Check if bill already exists
            $existingBill = Bill::where('subscription_id', $subscription->id)
                ->where('type', 'renewal_failed')
                ->where('status', 'pending')
                ->first();

            if ($existingBill) {
                Log::info('Bill already exists for failed invoice', [
                    'bill_id' => $existingBill->id,
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }

            $plan = $subscription->plan;
            $gracePeriodDays = \App\Models\SystemSetting::getValue('payment.grace_period_days', 3);

            // Create bill for manual payment
            $bill = Bill::create([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'type' => 'renewal_failed',
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $currency,
                'due_date' => now()->addDays($gracePeriodDays),
                'description' => "Payment failed for {$plan->name}. Please pay manually to continue.",
                'metadata' => [
                    'stripe_invoice_id' => $invoice['id'] ?? null,
                    'stripe_subscription_id' => $subscriptionId,
                    'stripe_customer_id' => $customerId,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'payment_method' => 'stripe',
                    'reason' => 'automatic_payment_failed',
                ],
            ]);

            // Set grace period on subscription
            $failureService = app(PaymentFailureService::class);
            $failureService->handlePaymentFailure($subscription, 'Stripe automatic payment failed');

            Log::info('✅ Bill created for failed Stripe invoice', [
                'bill_id' => $bill->id,
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscriptionId,
                'amount' => $amount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle Stripe invoice.payment_failed', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
            ]);
        }
    }

    /**
     * Handle Stripe invoice.payment_succeeded event
     * Renews subscription when automatic payment succeeds
     */
    protected function handleStripeInvoicePaymentSucceeded(array $webhookData): void
    {
        try {
            $invoice = $webhookData['data']['object'] ?? [];
            $subscriptionId = $invoice['subscription'] ?? null;
            
            if (!$subscriptionId) {
                return;
            }

            // Find subscription by Stripe subscription ID
            $subscription = Subscription::whereJsonContains('metadata->stripe_subscription_id', $subscriptionId)
                ->orWhere('metadata->stripe_subscription_id', $subscriptionId)
                ->first();

            if (!$subscription) {
                Log::warning('Stripe invoice.payment_succeeded: Subscription not found', [
                    'stripe_subscription_id' => $subscriptionId,
                ]);
                return;
            }

            // Renew subscription (similar to bill payment success)
            $plan = $subscription->plan;
            $billingCycle = $plan->billing_cycle ?? 'monthly';
            $endDate = match($billingCycle) {
                'quarterly' => now()->addMonths(3),
                'annual' => now()->addMonths(12),
                default => now()->addMonths(1),
            };

            // Deactivate old subscription
            $subscription->update([
                'is_active' => false,
                'end_date' => now(),
            ]);

            // Create new subscription (renewal)
            $newSubscription = Subscription::create([
                'user_id' => $subscription->user_id,
                'plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => $endDate,
                'tokens_used' => 0,
                'is_active' => true,
                'auto_renew' => $subscription->auto_renew,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'stripe_subscription_id' => $subscriptionId,
                    'payment_method' => 'stripe',
                    'billing_cycle' => $billingCycle,
                    'renewed_from_subscription_id' => $subscription->id,
                    'renewed_via' => 'stripe_automatic',
                ]),
            ]);

            Log::info('✅ Subscription renewed via Stripe automatic payment', [
                'old_subscription_id' => $subscription->id,
                'new_subscription_id' => $newSubscription->id,
                'stripe_subscription_id' => $subscriptionId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle Stripe invoice.payment_succeeded', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
            ]);
        }
    }
}
