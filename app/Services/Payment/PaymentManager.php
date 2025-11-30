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

        $paymentService = PaymentServiceFactory::make($payment->provider);
        $verificationResult = $paymentService->verifyPayment($reference);

        $oldStatus = $payment->status;
        $payment->status = $verificationResult['status'];
        $payment->transaction_id = $verificationResult['transaction_id'] ?? $payment->transaction_id;
        $payment->gateway_response = json_encode($verificationResult);
        $payment->save();

        // Fire events if status changed
        if ($oldStatus !== $payment->status) {
            match ($payment->status) {
                'success' => event(new PaymentSucceeded($payment)),
                'failed' => event(new PaymentFailed($payment)),
                default => null,
            };
        }

        Log::info('✅ Payment verified', [
            'payment_id' => $payment->id,
            'reference' => $reference,
            'old_status' => $oldStatus,
            'new_status' => $payment->status,
        ]);

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
    public function processWebhook(string $provider, array|string $payload, string $signature): Payment
    {
        $paymentService = PaymentServiceFactory::make($provider);
        
        // Stripe needs raw payload string for signature verification
        if ($provider === 'stripe' && is_string($payload)) {
            $webhookData = $paymentService->handleWebhookRaw($payload, $signature);
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
        // Priority: session_id (for checkout.session.completed) > payment_intent/transaction_id > tx_ref
        $reference = $webhookData['data']['session_id'] 
            ?? $webhookData['data']['payment_intent']
            ?? $webhookData['data']['transaction_id'] 
            ?? $webhookData['data']['tx_ref']
            ?? null;

        if (!$reference) {
            throw new \Exception('No payment reference found in webhook data');
        }

        $payment = Payment::where('reference', $reference)
            ->orWhere('transaction_id', $reference)
            ->first();

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
        $event = $webhookData['event'];
        
        // Chapa events: 'charge.success', 'charge.failure', etc.
        // Also check status in data for Chapa
        $dataStatus = $webhookData['data']['status'] ?? null;
        
        if (in_array($event, ['charge.success', 'checkout.session.completed', 'payment_intent.succeeded']) 
            || $dataStatus === 'successful' 
            || $dataStatus === 'success') {
            $payment->status = 'success';
            $payment->transaction_id = $webhookData['data']['transaction_id'] 
                ?? $webhookData['data']['id']
                ?? $webhookData['data']['payment_intent'] 
                ?? $payment->transaction_id;
        } elseif (in_array($event, ['charge.failure', 'payment_intent.payment_failed']) 
            || $dataStatus === 'failed') {
            $payment->status = 'failed';
        } elseif ($event === 'charge.refunded' || str_contains($event, 'refund') || $dataStatus === 'refunded') {
            $payment->status = 'refunded';
        }

        $payment->gateway_response = json_encode($webhookData);
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

