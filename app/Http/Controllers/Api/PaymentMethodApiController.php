<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;

class PaymentMethodApiController extends Controller
{
    /**
     * Return a list of enabled payment methods with public config.
     */
    public function index()
    {
        $methods = PaymentMethod::where('is_enabled', true)
            ->orderBy('sort_order', 'asc') // optional: to control display order
            ->get();

        $response = $methods->map(function ($method) {
            return [
                'id' => $method->id,
                'name' => $method->name,
                'key' => $method->key,
                'logo_url' => $method->logo_url ?? null,
                'description' => $method->description,
                'metadata' => $method->config['public'] ?? [],
            ];
        });

        return response()->json([
            'payment_methods' => $response,
        ]);
    }
}