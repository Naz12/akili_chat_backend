<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\PaymentServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use Stripe\Refund;
use Stripe\Exception\SignatureVerificationException;

class StripePaymentService implements PaymentServiceInterface
{
    private string $secretKey;
    private string $webhookSecret;
    private string $mode;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret_key');
        $this->webhookSecret = config('services.stripe.webhook_secret');
        $this->mode = config('services.stripe.mode', 'test');

        // Set Stripe API key
        Stripe::setApiKey($this->secretKey);
    }

    public function createPayment(array $data): array
    {
        try {
            $amount = $data['amount'] * 100; // Convert to cents
            $currency = strtolower($data['currency'] ?? 'usd');
            $metadata = $data['metadata'] ?? [];
            $successUrl = $data['success_url'] ?? config('app.frontend_url', config('app.url')) . '/payment/success';
            $cancelUrl = $data['cancel_url'] ?? config('app.frontend_url', config('app.url')) . '/payment/cancel';

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => $data['description'] ?? 'Payment',
                        ],
                        'unit_amount' => (int) $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
            ]);

            Log::info('✅ Stripe payment session created', [
                'session_id' => $session->id,
                'amount' => $amount / 100,
                'currency' => $currency,
            ]);

            return [
                'checkout_url' => $session->url,
                'reference' => $session->id,
                'transaction_id' => null, // Will be set after payment
            ];
        } catch (\Exception $e) {
            Log::error('❌ Stripe payment creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw new \Exception('Failed to create Stripe payment: ' . $e->getMessage());
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $session = Session::retrieve($reference);

            $status = 'pending';
            if ($session->payment_status === 'paid') {
                $status = 'success';
            } elseif ($session->payment_status === 'unpaid') {
                $status = 'pending';
            } else {
                $status = 'failed';
            }

            return [
                'status' => $status,
                'transaction_id' => $session->payment_intent ?? null,
                'amount' => ($session->amount_total ?? 0) / 100,
            ];
        } catch (\Exception $e) {
            Log::error('❌ Stripe payment verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to verify Stripe payment: ' . $e->getMessage());
        }
    }

    public function getPaymentStatus(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        // For array payload, convert to JSON
        return $this->handleWebhookRaw(json_encode($payload), $signature);
    }

    public function handleWebhookRaw(string $rawPayload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                $rawPayload,
                $signature,
                $this->webhookSecret
            );

            $eventType = $event->type;
            $data = $event->data->object;

            Log::info('✅ Stripe webhook received', [
                'event' => $eventType,
                'id' => $event->id,
            ]);

            // Handle different event types
            $paymentIntentId = null;
            $sessionId = null;
            $amount = null;
            
            if ($eventType === 'payment_intent.succeeded' || $eventType === 'payment_intent.payment_failed') {
                // For payment_intent events, the ID is the payment_intent itself
                $paymentIntentId = $data->id ?? null;
                $amount = isset($data->amount) ? $data->amount / 100 : null;
            } elseif ($eventType === 'checkout.session.completed') {
                // For checkout.session events, get session_id and payment_intent
                $sessionId = $data->id ?? null;
                $paymentIntentId = $data->payment_intent ?? null;
                $amount = isset($data->amount_total) ? $data->amount_total / 100 : null;
            } else {
                // For other events, try to extract payment_intent
                $paymentIntentId = $data->payment_intent ?? $data->id ?? null;
                $amount = isset($data->amount_total) ? $data->amount_total / 100 : (isset($data->amount) ? $data->amount / 100 : null);
            }

            // Extract metadata properly (handle StripeObject conversion)
            $metadata = [];
            if (isset($data->metadata)) {
                if (is_object($data->metadata) && method_exists($data->metadata, 'toArray')) {
                    $metadata = $data->metadata->toArray();
                } elseif (is_array($data->metadata)) {
                    $metadata = $data->metadata;
                } else {
                    $metadata = (array) $data->metadata;
                }
            }

            return [
                'event' => $eventType,
                'data' => [
                    'session_id' => $sessionId,
                    'payment_intent' => $paymentIntentId,
                    'transaction_id' => $paymentIntentId, // Use payment_intent as transaction_id
                    'amount_total' => $amount,
                    'currency' => $data->currency ?? null,
                    'payment_status' => $data->payment_status ?? $data->status ?? null,
                    'metadata' => $metadata,
                ],
            ];
        } catch (SignatureVerificationException $e) {
            Log::error('❌ Stripe webhook signature invalid', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Invalid webhook signature');
        } catch (\Exception $e) {
            Log::error('❌ Stripe webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to process webhook: ' . $e->getMessage());
        }
    }

    public function refundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            $refundData = [
                'payment_intent' => $transactionId,
            ];

            if ($amount !== null) {
                $refundData['amount'] = (int) ($amount * 100); // Convert to cents
            }

            $refund = Refund::create($refundData);

            Log::info('✅ Stripe refund created', [
                'refund_id' => $refund->id,
                'transaction_id' => $transactionId,
                'amount' => $refund->amount / 100,
            ]);

            return [
                'status' => $refund->status,
                'refund_id' => $refund->id,
            ];
        } catch (\Exception $e) {
            Log::error('❌ Stripe refund failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to process refund: ' . $e->getMessage());
        }
    }
}

