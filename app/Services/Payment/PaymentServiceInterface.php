<?php

namespace App\Services\Payment;

interface PaymentServiceInterface
{
    /**
     * Create payment and return checkout URL.
     *
     * @param array $data Payment data (amount, currency, metadata, etc.)
     * @return array ['checkout_url' => string, 'reference' => string, 'transaction_id' => string|null]
     * @throws \Exception
     */
    public function createPayment(array $data): array;

    /**
     * Verify payment status with gateway.
     *
     * @param string $reference Payment reference
     * @return array ['status' => string, 'transaction_id' => string|null, 'amount' => float]
     * @throws \Exception
     */
    public function verifyPayment(string $reference): array;

    /**
     * Get payment status from gateway.
     *
     * @param string $reference Payment reference
     * @return array ['status' => string, 'transaction_id' => string|null]
     * @throws \Exception
     */
    public function getPaymentStatus(string $reference): array;

    /**
     * Handle webhook payload from gateway.
     *
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature
     * @return array ['event' => string, 'data' => array]
     * @throws \Exception
     */
    public function handleWebhook(array $payload, string $signature): array;

    /**
     * Handle webhook with raw payload (for Stripe).
     *
     * @param string $rawPayload Raw webhook payload
     * @param string $signature Webhook signature
     * @return array ['event' => string, 'data' => array]
     * @throws \Exception
     */
    public function handleWebhookRaw(string $rawPayload, string $signature): array;

    /**
     * Refund a payment.
     *
     * @param string $transactionId Gateway transaction ID
     * @param float|null $amount Amount to refund (null = full refund)
     * @return array ['status' => string, 'refund_id' => string|null]
     * @throws \Exception
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array;
}

