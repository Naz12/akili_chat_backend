<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Chapa',
                'key' => 'chapa',
                'description' => 'Chapa payment gateway for local/Ethiopian users. Supports ETB currency.',
                'is_enabled' => true,
                'regions' => ['local'],
            ],
            [
                'name' => 'Stripe',
                'key' => 'stripe',
                'description' => 'Stripe payment gateway for international users. Supports USD and other international currencies.',
                'is_enabled' => true,
                'regions' => ['intl'],
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['key' => $method['key']],
                $method
            );
        }
    }
}
