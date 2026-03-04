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
        $this->publicKey = (string) config('services.chapa.public_key', '');
        $this->secretKey = (string) config('services.chapa.secret_key', '');
        $this->baseUrl = rtrim((string) config('services.chapa.base_url', 'https://api.chapa.co/v1'), '/');
    }

    /** Throw if Chapa keys are not configured (avoids null assignment and gives a clear error when using payment). */
    private function ensureConfigured(): void
    {
        if ($this->secretKey === '' || $this->publicKey === '') {
            throw new \RuntimeException(
                'Chapa payment is not configured. Set CHAPA_PUBLIC_KEY and CHAPA_SECRET_KEY in your .env file.'
            );
        }
    }

    public function createPayment(array $data): array
    {
        $this->ensureConfigured();
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
        $this->ensureConfigured();
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
            
            // Check if API response indicates success
            $apiStatus = strtolower(trim($data['status'] ?? ''));
            if ($apiStatus !== 'success') {
                Log::warning('⚠️ Chapa API response not successful', [
                    'reference' => $reference,
                    'api_status' => $data['status'] ?? null,
                ]);
                return [
                    'status' => 'failed',
                    'transaction_id' => null,
                    'amount' => 0,
                ];
            }

            $transactionData = $data['data'] ?? [];
            $transactionStatus = strtolower(trim($transactionData['status'] ?? ''));
            
            // Map Chapa transaction status to our payment status
            // Chapa can return: 'successful', 'success', 'pending', 'failed', etc.
            $paymentStatus = 'pending'; // Default
            if (in_array($transactionStatus, ['successful', 'success', 'completed', 'paid', 'settled'])) {
                $paymentStatus = 'success';
            } elseif (in_array($transactionStatus, ['failed', 'failure', 'declined', 'cancelled', 'canceled'])) {
                $paymentStatus = 'failed';
            }
            
            Log::info('✅ Chapa payment verification result', [
                'reference' => $reference,
                'transaction_status' => $transactionData['status'] ?? null,
                'mapped_status' => $paymentStatus,
                'transaction_id' => $transactionData['id'] ?? null,
            ]);

            return [
                'status' => $paymentStatus,
                'transaction_id' => $transactionData['id'] ?? $transactionData['reference'] ?? null,
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
            
            if (!$payload) {
                throw new \Exception('Invalid webhook payload: unable to decode JSON');
            }
            
            // Chapa signature verification
            // Chapa sends signature in Chapa-Signature or x-chapa-signature header
            // Signature is computed as HMAC-SHA256 of the raw request body
            if ($signature && $signingKey) {
                // Compute signature from raw payload
                $computedHash = hash_hmac('sha256', $rawPayload, $signingKey);
                
                // Normalize signatures for comparison (remove any whitespace)
                $providedSignature = trim(strtolower($signature));
                $computedSignature = trim(strtolower($computedHash));
                
                if (!hash_equals($computedSignature, $providedSignature)) {
                    Log::error('❌ Chapa webhook signature mismatch - REJECTING webhook', [
                        'provided' => substr($providedSignature, 0, 20) . '...',
                        'computed' => substr($computedSignature, 0, 20) . '...',
                        'payload_length' => strlen($rawPayload),
                        'has_webhook_secret' => !empty($webhookSecret),
                        'has_secret_key' => !empty($secretKey),
                    ]);
                    
                    // If signature verification fails but webhook_secret is not configured,
                    // allow it for backward compatibility (but log warning)
                    if (!$webhookSecret && config('services.chapa.require_signature', false) === false) {
                        Log::warning('⚠️ Signature mismatch but allowing webhook (webhook_secret not configured, require_signature=false)');
                    } else {
                        throw new \Exception('Invalid webhook signature');
                    }
                } else {
                    Log::info('✅ Chapa webhook signature verified');
                }
            } elseif ($signature && !$signingKey) {
                Log::warning('⚠️ Chapa signature provided but no signing key configured');
                // If signature is required, reject; otherwise allow (for backward compatibility)
                if (config('services.chapa.require_signature', false)) {
                    throw new \Exception('Webhook signature required but signing key not configured');
                } else {
                    Log::warning('⚠️ Allowing webhook without signature verification (require_signature=false)');
                }
            } else {
                Log::warning('⚠️ No signature provided in Chapa webhook');
                // Allow if signature is not required
                if (config('services.chapa.require_signature', false)) {
                    throw new \Exception('Webhook signature required but not provided');
                }
            }

            $event = $payload['event'] ?? 'unknown';
            
            // Chapa sends data directly in payload, not nested in 'data' key
            // For charge.success event, all fields are at root level
            $data = $payload['data'] ?? $payload;

            Log::info('✅ Chapa webhook received', [
                'event' => $event,
                'tx_ref' => $data['tx_ref'] ?? $payload['tx_ref'] ?? null,
                'status' => $data['status'] ?? $payload['status'] ?? null,
                'reference' => $data['reference'] ?? $payload['reference'] ?? null,
            ]);

            // Extract transaction details - Chapa sends them at root level
            $txRef = $data['tx_ref'] ?? $payload['tx_ref'] ?? null;
            $reference = $data['reference'] ?? $payload['reference'] ?? null;
            $status = $data['status'] ?? $payload['status'] ?? null;
            $amount = $data['amount'] ?? $payload['amount'] ?? null;
            $currency = $data['currency'] ?? $payload['currency'] ?? null;
            $meta = $data['meta'] ?? $payload['meta'] ?? [];
            
            // Normalize status value - Chapa may send 'successful', 'success', etc.
            // Ensure we capture the status correctly for PaymentManager to process
            $normalizedStatus = $status;
            if ($status) {
                $statusLower = strtolower(trim($status));
                // Map common Chapa status values
                if (in_array($statusLower, ['successful', 'success', 'completed', 'paid', 'settled'])) {
                    $normalizedStatus = 'successful'; // Use 'successful' to match PaymentManager check
                } elseif (in_array($statusLower, ['failed', 'failure', 'declined', 'cancelled', 'canceled'])) {
                    $normalizedStatus = 'failed';
                }
            }
            
            Log::info('📋 Chapa webhook data extracted', [
                'event' => $event,
                'tx_ref' => $txRef,
                'reference' => $reference,
                'raw_status' => $status,
                'normalized_status' => $normalizedStatus,
            ]);

            return [
                'event' => $event,
                'data' => [
                    'tx_ref' => $txRef,
                    'reference' => $reference,
                    'transaction_id' => $reference, // Chapa's reference is the transaction ID
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $normalizedStatus, // Use normalized status
                    'metadata' => $meta,
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

