<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\PaymentServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChapaPaymentService implements PaymentServiceInterface
{
    private string $publicKey;
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->publicKey = config('services.chapa.public_key');
        $this->secretKey = config('services.chapa.secret_key');
        $this->baseUrl = config('services.chapa.base_url', 'https://api.chapa.co/v1');
    }

    public function createPayment(array $data): array
    {
        try {
            $amount = (float) $data['amount'];
            $currency = strtoupper($data['currency'] ?? 'ETB');
            $email = $data['email'] ?? null;
            
            // Validate email format - Chapa requires valid email
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('⚠️ Invalid email format for Chapa payment', ['email' => $email]);
                throw new \Exception('Invalid email format. Chapa requires a valid email address.');
            }
            
            $firstName = $data['first_name'] ?? $data['name'] ?? 'Customer';
            $lastName = $data['last_name'] ?? '';
            $phone = $data['phone'] ?? null;
            $metadata = $data['metadata'] ?? [];
            $callbackUrl = $data['callback_url'] ?? config('app.url') . '/api/v1/payments/webhook/chapa';
            // Chapa redirects users directly to return_url, but doesn't include params
            // So we'll ALWAYS use our backend endpoint that processes and redirects with params
            // This ensures we can process the payment and add tx_ref to the redirect
            $frontendSuccessUrl = $data['return_url'] ?? $data['success_url'] ?? config('app.frontend_url', config('app.url')) . '/payment/success';
            // Store frontend URL in metadata so we can redirect there after processing
            $formattedMetadata['frontend_success_url'] = $frontendSuccessUrl;
            // Use backend redirect endpoint as return_url
            $returnUrl = config('app.url') . '/api/v1/payments/webhook/chapa/redirect';

            // Generate unique transaction reference
            $txRef = 'chapa_' . uniqid() . '_' . time();

            // Format metadata - Chapa expects simple key-value pairs (strings/numbers, not nested arrays)
            $formattedMetadata = [];
            foreach ($metadata as $key => $value) {
                // Convert to string or number - no arrays allowed
                if (is_array($value)) {
                    // If it's an array, convert to JSON string
                    $formattedMetadata[$key] = json_encode($value);
                } elseif (is_numeric($value) && $key === 'plan_id') {
                    // Ensure plan_id is integer
                    $formattedMetadata[$key] = (int) $value;
                } elseif (is_numeric($value)) {
                    $formattedMetadata[$key] = (int) $value;
                } else {
                    $formattedMetadata[$key] = (string) $value;
                }
            }

            // Chapa requires email to be a valid, non-test domain email
            // Ensure email is trimmed and lowercase
            $email = $email ? strtolower(trim($email)) : null;
            
            // Remove email from payload if it's null or empty
            $payload = [
                'amount' => $amount,
                'currency' => $currency,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'tx_ref' => $txRef,
                'callback_url' => $callbackUrl,
                'return_url' => $returnUrl,
                'meta' => $formattedMetadata,
            ];
            
            // Only add email if it's provided and valid
            if ($email) {
                $payload['email'] = $email;
            }
            
            // Only add phone_number if provided
            if ($phone) {
                $payload['phone_number'] = $phone;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/transaction/initialize', $payload);

            if ($response->failed()) {
                $error = $response->json();
                Log::error('❌ Chapa payment initialization failed', [
                    'response' => $error,
                    'payload' => $payload,
                ]);
                
                // Handle error message (can be string or array)
                $errorMessage = 'Unknown error';
                if (isset($error['message'])) {
                    if (is_array($error['message'])) {
                        // Convert array of errors to string
                        $errorMessage = json_encode($error['message']);
                    } else {
                        $errorMessage = $error['message'];
                    }
                }
                
                throw new \Exception('Chapa API error: ' . $errorMessage);
            }

            $responseData = $response->json();
            
            if (($responseData['status'] ?? '') !== 'success') {
                Log::error('❌ Chapa payment initialization failed', [
                    'response' => $responseData,
                ]);
                throw new \Exception('Chapa payment initialization failed');
            }

            $checkoutUrl = $responseData['data']['checkout_url'] ?? null;

            if (!$checkoutUrl) {
                throw new \Exception('No checkout URL returned from Chapa');
            }

            Log::info('✅ Chapa payment initialized', [
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return [
                'checkout_url' => $checkoutUrl,
                'reference' => $txRef,
                'transaction_id' => null, // Will be set after payment
            ];
        } catch (\Exception $e) {
            Log::error('❌ Chapa payment creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw new \Exception('Failed to create Chapa payment: ' . $e->getMessage());
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/transaction/verify/' . $reference);

            if ($response->failed()) {
                $error = $response->json();
                Log::error('❌ Chapa payment verification failed', [
                    'reference' => $reference,
                    'response' => $error,
                ]);
                throw new \Exception('Chapa API error: ' . ($error['message'] ?? 'Unknown error'));
            }

            $data = $response->json();
            
            if (($data['status'] ?? '') !== 'success') {
                return [
                    'status' => 'failed',
                    'transaction_id' => null,
                    'amount' => 0,
                ];
            }

            $transactionData = $data['data'] ?? [];
            $status = ($transactionData['status'] ?? '') === 'successful' ? 'success' : 'pending';

            return [
                'status' => $status,
                'transaction_id' => $transactionData['id'] ?? null,
                'amount' => $transactionData['amount'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('❌ Chapa payment verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to verify Chapa payment: ' . $e->getMessage());
        }
    }

    public function getPaymentStatus(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        // Chapa uses array payload
        return $this->handleWebhookRaw(json_encode($payload), $signature);
    }

    public function handleWebhookRaw(string $rawPayload, string $signature): array
    {
        try {
            // Verify signature - Chapa uses webhook_secret for signature verification
            $webhookSecret = config('services.chapa.webhook_secret');
            $secretKey = config('services.chapa.secret_key');
            
            // Try webhook_secret first, fallback to secret_key if webhook_secret not set
            $signingKey = $webhookSecret ?: $secretKey;
            
            $payload = json_decode($rawPayload, true);
            
            // Chapa may send signature in header or we compute it
            // If signature is provided, verify it - REJECT if mismatch
            if ($signature && $signingKey) {
                $computedHash = hash_hmac('sha256', $rawPayload, $signingKey);
                
                if (!hash_equals($computedHash, $signature)) {
                    Log::error('❌ Chapa webhook signature mismatch - REJECTING webhook', [
                        'provided' => $signature,
                        'computed' => $computedHash,
                    ]);
                    throw new \Exception('Invalid webhook signature');
                } else {
                    Log::info('✅ Chapa webhook signature verified');
                }
            } elseif ($signature && !$signingKey) {
                Log::warning('⚠️ Chapa signature provided but no signing key configured');
                // If signature is required, reject; otherwise allow (for backward compatibility)
                if (config('services.chapa.require_signature', false)) {
                    throw new \Exception('Webhook signature required but signing key not configured');
                }
            }

            $event = $payload['event'] ?? 'unknown';
            $data = $payload['data'] ?? $payload;

            Log::info('✅ Chapa webhook received', [
                'event' => $event,
                'tx_ref' => $data['tx_ref'] ?? null,
            ]);

            return [
                'event' => $event,
                'data' => [
                    'tx_ref' => $data['tx_ref'] ?? null,
                    'transaction_id' => $data['id'] ?? null,
                    'amount' => $data['amount'] ?? null,
                    'currency' => $data['currency'] ?? null,
                    'status' => $data['status'] ?? null,
                    'metadata' => $data['metadata'] ?? [],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('❌ Chapa webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to process webhook: ' . $e->getMessage());
        }
    }

    public function refundPayment(string $transactionId, ?float $amount = null): array
    {
        // Chapa refund API - check Chapa documentation for exact endpoint
        // For now, return not implemented
        throw new \Exception('Chapa refund functionality not yet implemented. Please contact support.');
    }
}

