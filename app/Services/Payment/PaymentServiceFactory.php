<?php

namespace App\Services\Payment;

use App\Services\Payment\Providers\StripePaymentService;
use App\Services\Payment\Providers\ChapaPaymentService;
use InvalidArgumentException;

class PaymentServiceFactory
{
    /**
     * Create a payment service instance for the given provider.
     *
     * @param string $provider Payment provider (stripe, chapa)
     * @return PaymentServiceInterface
     * @throws InvalidArgumentException
     */
    public static function make(string $provider): PaymentServiceInterface
    {
        return match (strtolower($provider)) {
            'stripe' => app(StripePaymentService::class),
            'chapa' => app(ChapaPaymentService::class),
            default => throw new InvalidArgumentException("Unsupported payment provider: {$provider}"),
        };
    }
}

