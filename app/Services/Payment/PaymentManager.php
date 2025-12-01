<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Events\PaymentCreated;
use App\Events\PaymentSucceeded;
use App\Events\PaymentFailed;
use App\Events\PaymentRefunded;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentManager
{
    /**
     * Initiate a payment and create payment record.
     *
     * @param User $user
     * @param array $data Payment data
     * @return Payment
     * @throws \Exception
     */
    public function initiatePayment(User $user, array $data): Payment
    {
        $provider = $data['provider'] ?? 'stripe';
        $amount = $data['amount'] ?? 0;
        $currency = $data['currency'] ?? 'USD';
        $metadata = $data['metadata'] ?? [];

        // Get payment service
        $paymentService = PaymentServiceFactory::make($provider);

        // Prepare payment data for gateway
        // Ensure metadata values are properly formatted
        $formattedMetadata = [];
        foreach (array_merge($metadata, ['user_id' => $user->id]) as $key => $value) {
            // Convert to appropriate type
            if (is_numeric($value) && $key === 'plan_id') {
                $formattedMetadata[$key] = (int) $value;
            } elseif (is_numeric($value)) {
                $formattedMetadata[$key] = (int) $value;
            } else {
                $formattedMetadata[$key] = (string) $value;
            }
        }

        // Validate email - Chapa requires valid email format
        $userEmail = $user->email;
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('⚠️ User email is not valid format', [
                'user_id' => $user->id,
                'email' => $userEmail,
            ]);
            throw new \Exception('User email is not in a valid format. Please update your email address.');
        }

        $paymentData = [
            'amount' => (float) $amount,
            'currency' => $currency,
            'email' => $userEmail,
            'name' => $user->name,
            'first_name' => explode(' ', $user->name)[0] ?? $user->name,
            'last_name' => explode(' ', $user->name, 2)[1] ?? '',
            'metadata' => $formattedMetadata,
            'callback_url' => $data['callback_url'] ?? config('app.url') . '/api/v1/payments/webhook/' . $provider,
            'return_url' => $data['return_url'] ?? config('app.frontend_url', config('app.url')) . '/payment/success',
            'success_url' => $data['success_url'] ?? config('app.frontend_url', config('app.url')) . '/payment/success',
            'cancel_url' => $data['cancel_url'] ?? config('app.frontend_url', config('app.url')) . '/payment/cancel',
            'description' => $data['description'] ?? 'Payment',
        ];

        // Create payment with gateway
        $gatewayResponse = $paymentService->createPayment($paymentData);

        // Create payment record
        $payment = Payment::create([
            'reference' => $gatewayResponse['reference'],
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'provider' => $provider,
            'status' => 'pending',
            'transaction_id' => $gatewayResponse['transaction_id'] ?? null,
            'metadata' => $metadata,
            'gateway_response' => json_encode($gatewayResponse),
        ]);

        // Fire event
        event(new PaymentCreated($payment));

        Log::info('✅ Payment initiated', [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'provider' => $provider,
            'amount' => $amount,
        ]);

        return $payment;
    }

    /**
     * Verify payment with gateway and update record.
     *
     * @param string $reference Payment reference
     * @return Payment
     * @throws \Exception
     */
    public function verifyPayment(string $reference): Payment
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();

        Log::info('🔍 Verifying payment with gateway', [
            'payment_id' => $payment->id,
            'reference' => $reference,
            'provider' => $payment->provider,
            'current_status' => $payment->status,
        ]);

        $paymentService = PaymentServiceFactory::make($payment->provider);
        $verificationResult = $paymentService->verifyPayment($reference);

        $oldStatus = $payment->status;
        $newStatus = $verificationResult['status'] ?? 'pending';
        
        // Always update payment status from verification result
        $payment->status = $newStatus;
        $payment->transaction_id = $verificationResult['transaction_id'] ?? $payment->transaction_id;
        $payment->gateway_response = json_encode($verificationResult);
        $payment->updated_at = now(); // Ensure updated_at is refreshed
        $payment->save();

        Log::info('✅ Payment verified and database updated', [
            'payment_id' => $payment->id,
            'reference' => $reference,
            'old_status' => $oldStatus,
            'new_status' => $payment->status,
            'transaction_id' => $payment->transaction_id,
            'status_changed' => $oldStatus !== $payment->status,
        ]);

        // Fire events if status changed
        if ($oldStatus !== $payment->status) {
            match ($payment->status) {
                'success' => event(new PaymentSucceeded($payment)),
                'failed' => event(new PaymentFailed($payment)),
                default => null,
            };
            
            Log::info('📢 Payment status change event fired', [
                'payment_id' => $payment->id,
                'new_status' => $payment->status,
            ]);
        }

        return $payment;
    }

    /**
     * Get payment status (from DB, optionally refresh from gateway).
     *
     * @param string $reference Payment reference
     * @param bool $refreshFromGateway Whether to refresh from gateway
     * @return array
     */
    public function getPaymentStatus(string $reference, bool $refreshFromGateway = false): array
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();

        if ($refreshFromGateway && $payment->isPending()) {
            try {
                $payment = $this->verifyPayment($reference);
            } catch (\Exception $e) {
                Log::warning('⚠️ Failed to refresh payment status from gateway', [
                    'reference' => $reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'transaction_id' => $payment->transaction_id,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
        ];
    }

    /**
     * Process webhook and update payment.
     *
     * @param string $provider Payment provider
     * @param array|string $payload Webhook payload (array for most, raw string for Stripe)
     * @param string $signature Webhook signature
     * @return Payment
     * @throws \Exception
     */
    public function processWebhook(string $provider, array|string $payload, string $signature, ?string $rawPayload = null): Payment
    {
        $paymentService = PaymentServiceFactory::make($provider);
        
        // Stripe and Chapa need raw payload string for signature verification
        if ($provider === 'stripe' && is_string($payload)) {
            $webhookData = $paymentService->handleWebhookRaw($payload, $signature);
        } elseif ($provider === 'chapa' && $rawPayload) {
            // Chapa signature verification requires raw request body
            $webhookData = $paymentService->handleWebhookRaw($rawPayload, $signature);
        } else {
            $webhookData = $paymentService->handleWebhook(is_array($payload) ? $payload : [], $signature);
        }

        // Extract webhook ID and event ID for idempotency
        $webhookId = $webhookData['id'] ?? $webhookData['webhook_id'] ?? $webhookData['data']['id'] ?? null;
        $eventId = $webhookData['event'] ?? $webhookData['event_id'] ?? $webhookData['type'] ?? null;
        
        // Check for duplicate webhook processing (idempotency)
        if ($webhookId) {
            $existingWebhook = \App\Models\Webhook::where('provider', $provider)
                ->where('webhook_id', $webhookId)
                ->where('processed', true)
                ->first();
            
            if ($existingWebhook) {
                Log::info('Webhook already processed (idempotency check)', [
                    'provider' => $provider,
                    'webhook_id' => $webhookId,
                    'original_processed_at' => $existingWebhook->processed_at,
                ]);
                
                // Try to find and return the payment that was already processed
                $reference = $webhookData['data']['session_id'] 
                    ?? $webhookData['data']['payment_intent']
                    ?? $webhookData['data']['transaction_id'] 
                    ?? $webhookData['data']['tx_ref']
                    ?? null;
                
                if ($reference) {
                    $payment = Payment::where('reference', $reference)
                        ->orWhere('transaction_id', $reference)
                        ->first();
                    
                    if ($payment) {
                        return $payment;
                    }
                }
                
                throw new \Exception('Webhook already processed');
            }
        }

        // Find payment by reference or transaction_id
        // For Chapa: tx_ref is the payment reference, reference is the transaction_id
        // For Stripe: session_id or payment_intent
        $reference = null;
        $transactionId = null;
        
        if ($provider === 'chapa') {
            // Chapa sends tx_ref (payment reference) and reference (transaction ID)
            $reference = $webhookData['data']['tx_ref'] ?? null;
            $transactionId = $webhookData['data']['reference'] ?? $webhookData['data']['transaction_id'] ?? null;
        } else {
            // Stripe and others
            $reference = $webhookData['data']['session_id'] 
                ?? $webhookData['data']['payment_intent']
                ?? $webhookData['data']['transaction_id'] 
                ?? $webhookData['data']['tx_ref']
                ?? null;
        }

        if (!$reference) {
            throw new \Exception('No payment reference found in webhook data');
        }

        // Extract Stripe subscription ID from checkout.session.completed events
        $stripeSubscriptionId = null;
        if ($provider === 'stripe' && $webhookData['event'] === 'checkout.session.completed') {
            $stripeSubscriptionId = $webhookData['data']['subscription'] 
                ?? $webhookData['data']['subscription_id'] 
                ?? null;
            
            if ($stripeSubscriptionId) {
                Log::info('✅ Stripe subscription ID extracted from checkout session', [
                    'subscription_id' => $stripeSubscriptionId,
                    'session_id' => $reference,
                ]);
            }
        }

        // Find payment by reference (tx_ref for Chapa, session_id for Stripe)
        $payment = Payment::where('reference', $reference)->first();
        
        // If not found and we have transaction_id, try that
        if (!$payment && $transactionId) {
            $payment = Payment::where('transaction_id', $transactionId)->first();
        }

        // For payment_intent.succeeded events, try to find payment by payment_intent ID
        // Payment records created via checkout session have session_id as reference,
        // but we need to find them by the payment_intent transaction_id
        if (!$payment && $webhookData['event'] === 'payment_intent.succeeded') {
            $paymentIntentId = $webhookData['data']['payment_intent'] ?? $reference;
            
            // Try to find by transaction_id (payment_intent ID)
            $payment = Payment::where('transaction_id', $paymentIntentId)->first();
            
            // If still not found, try to find by looking up checkout session from Stripe
            if (!$payment && $provider === 'stripe') {
                try {
                    $stripeService = PaymentServiceFactory::make('stripe');
                    // Retrieve payment intent to get checkout session ID
                    $stripe = new \Stripe\StripeClient(config('services.stripe.secret_key'));
                    $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);
                    
                    // Payment intents from checkout sessions have metadata with session_id
                    $sessionId = $paymentIntent->metadata->session_id ?? null;
                    
                    if ($sessionId) {
                        $payment = Payment::where('reference', $sessionId)->first();
                        
                        // Update payment with transaction_id if found
                        if ($payment && !$payment->transaction_id) {
                            $payment->transaction_id = $paymentIntentId;
                            $payment->save();
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to lookup checkout session from payment_intent', [
                        'error' => $e->getMessage(),
                        'payment_intent' => $paymentIntentId,
                    ]);
                }
            }
            
            // If still not found, try to find by metadata
            if (!$payment) {
                $metadata = $webhookData['data']['metadata'] ?? [];
                
                if (isset($metadata['user_id'])) {
                    $userId = $metadata['user_id'];
                    $planId = $metadata['plan_id'] ?? null;
                    
                    // Find most recent pending payment for this user
                    $payment = Payment::where('user_id', $userId)
                        ->where('status', 'pending')
                        ->where('provider', $provider)
                        ->latest()
                        ->first();
                    
                    // Update with transaction_id if found
                    if ($payment && !$payment->transaction_id) {
                        $payment->transaction_id = $paymentIntentId;
                        $payment->save();
                    }
                    
                    // If still not found and we have plan_id, create payment record
                    if (!$payment && $planId && isset($webhookData['data']['amount_total'])) {
                        try {
                            $user = \App\Models\User::find($userId);
                            if ($user) {
                                $payment = Payment::create([
                                    'reference' => $paymentIntentId,
                                    'user_id' => $userId,
                                    'amount' => $webhookData['data']['amount_total'],
                                    'currency' => $webhookData['data']['currency'] ?? 'USD',
                                    'provider' => $provider,
                                    'status' => 'success', // Payment already succeeded
                                    'transaction_id' => $paymentIntentId,
                                    'metadata' => $metadata,
                                    'gateway_response' => json_encode($webhookData),
                                ]);
                                
                                Log::info('✅ Created payment record from webhook', [
                                    'payment_id' => $payment->id,
                                    'payment_intent' => $paymentIntentId,
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to create payment from webhook', [
                                'error' => $e->getMessage(),
                                'payment_intent' => $paymentIntentId,
                            ]);
                        }
                    }
                }
            }
        }

        if (!$payment) {
            Log::warning('⚠️ Payment not found for webhook', [
                'provider' => $provider,
                'reference' => $reference,
                'event' => $webhookData['event'],
                'webhook_data' => $webhookData,
            ]);
            
            // For payment_intent.succeeded without payment record, return null
            // This allows the webhook controller to return 200 to prevent infinite retries
            // The payment might have been created outside our system or already processed
            if ($webhookData['event'] === 'payment_intent.succeeded') {
                Log::info('ℹ️ Payment intent succeeded but no payment record found', [
                    'payment_intent' => $reference,
                    'note' => 'Payment may have been processed via checkout.session.completed',
                ]);
                return null; // Signal to webhook controller to return 200
            }
            
            throw new \Exception('Payment not found');
        }

        $oldStatus = $payment->status;

        // Update payment status based on event
        $event = $webhookData['event'] ?? 'unknown';
        
        // Chapa events: 'charge.success', 'charge.failure', etc.
        // Also check status in data for Chapa (Chapa sends status at various levels)
        $dataStatus = $webhookData['data']['status'] ?? null;
        
        // Normalize status values for comparison (handle case-insensitive and variations)
        $normalizedDataStatus = $dataStatus ? strtolower(trim($dataStatus)) : null;
        
        // Check for success conditions - be more flexible with Chapa status values
        $isSuccess = in_array($event, ['charge.success', 'checkout.session.completed', 'payment_intent.succeeded'])
            || in_array($normalizedDataStatus, ['success', 'successful', 'completed', 'paid', 'settled'])
            || (str_contains(strtolower($event), 'success') && !str_contains(strtolower($event), 'fail'));
        
        // Check for failure conditions
        $isFailure = in_array($event, ['charge.failure', 'payment_intent.payment_failed'])
            || in_array($normalizedDataStatus, ['failed', 'failure', 'declined', 'cancelled', 'canceled']);
        
        // Check for refund conditions
        $isRefunded = $event === 'charge.refunded' 
            || str_contains(strtolower($event), 'refund')
            || in_array($normalizedDataStatus, ['refunded', 'refund']);
        
        Log::info('🔍 Determining payment status from webhook', [
            'payment_id' => $payment->id,
            'provider' => $provider,
            'event' => $event,
            'data_status' => $dataStatus,
            'normalized_status' => $normalizedDataStatus,
            'is_success' => $isSuccess,
            'is_failure' => $isFailure,
            'is_refunded' => $isRefunded,
            'current_status' => $oldStatus,
        ]);
        
        // Extract transaction_id from webhook data (available for all statuses)
        $newTransactionId = $webhookData['data']['transaction_id'] 
            ?? $webhookData['data']['reference']  // Chapa uses 'reference' as transaction_id
            ?? $webhookData['data']['id']
            ?? $webhookData['data']['payment_intent'] 
            ?? null;
        
        if ($isSuccess) {
            $payment->status = 'success';
            
            // Update transaction_id if available (even if already set, in case we get a better one)
            if ($newTransactionId) {
                $payment->transaction_id = $newTransactionId;
            }
            
            Log::info('✅ Payment status updated to success', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status_changed' => $oldStatus !== 'success',
            ]);
        } elseif ($isFailure) {
            $payment->status = 'failed';
            
            // Update transaction_id if available
            if ($newTransactionId) {
                $payment->transaction_id = $newTransactionId;
            }
            
            Log::info('❌ Payment status updated to failed', [
                'payment_id' => $payment->id,
                'status_changed' => $oldStatus !== 'failed',
            ]);
        } elseif ($isRefunded) {
            $payment->status = 'refunded';
            
            // Update transaction_id if available
            if ($newTransactionId) {
                $payment->transaction_id = $newTransactionId;
            }
            
            Log::info('↩️ Payment status updated to refunded', [
                'payment_id' => $payment->id,
                'status_changed' => $oldStatus !== 'refunded',
            ]);
        } else {
            // Even if status doesn't match, update transaction_id if available
            if ($newTransactionId && !$payment->transaction_id) {
                $payment->transaction_id = $newTransactionId;
                Log::info('📝 Updated transaction_id from webhook', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $newTransactionId,
                ]);
            }
            
            Log::warning('⚠️ Payment status not updated - no matching condition', [
                'payment_id' => $payment->id,
                'event' => $event,
                'data_status' => $dataStatus,
                'current_status' => $oldStatus,
            ]);
        }

        // Store Stripe subscription ID in payment metadata if available
        if ($stripeSubscriptionId) {
            $paymentMetadata = is_string($payment->metadata) 
                ? json_decode($payment->metadata, true) 
                : ($payment->metadata ?? []);
            $paymentMetadata['stripe_subscription_id'] = $stripeSubscriptionId;
            $payment->metadata = $paymentMetadata;
        }

        $payment->gateway_response = json_encode($webhookData);
        $payment->updated_at = now(); // Ensure updated_at is refreshed
        $payment->save();

        // Fire events if status changed
        if ($oldStatus !== $payment->status) {
            match ($payment->status) {
                'success' => event(new PaymentSucceeded($payment)),
                'failed' => event(new PaymentFailed($payment)),
                'refunded' => event(new PaymentRefunded($payment)),
                default => null,
            };
        }

        Log::info('✅ Webhook processed', [
            'payment_id' => $payment->id,
            'provider' => $provider,
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $payment->status,
        ]);

        return $payment;
    }

    /**
     * Refund a payment.
     *
     * @param Payment $payment
     * @param float|null $amount Amount to refund (null = full refund)
     * @return Payment
     * @throws \Exception
     */
    public function refundPayment(Payment $payment, ?float $amount = null): Payment
    {
        if (!$payment->transaction_id) {
            throw new \Exception('Cannot refund payment without transaction ID');
        }

        if ($payment->status !== 'success') {
            throw new \Exception('Can only refund successful payments');
        }

        $paymentService = PaymentServiceFactory::make($payment->provider);
        $refundResult = $paymentService->refundPayment($payment->transaction_id, $amount);

        $payment->status = 'refunded';
        $payment->gateway_response = json_encode($refundResult);
        $payment->save();

        event(new PaymentRefunded($payment));

        Log::info('✅ Payment refunded', [
            'payment_id' => $payment->id,
            'amount' => $amount ?? $payment->amount,
        ]);

        return $payment;
    }

    /**
     * Get user payment history.
     *
     * @param User $user
     * @param array $filters Filters (status, provider, date_from, date_to)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserPayments(User $user, array $filters = [])
    {
        $query = Payment::where('user_id', $user->id);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->get();
    }
}

