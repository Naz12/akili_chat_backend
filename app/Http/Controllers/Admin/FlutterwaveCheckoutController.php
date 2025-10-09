<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Plan;

class FlutterwaveCheckoutController extends Controller
{
    /**
     * Initialize a Flutterwave checkout session.
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($request->plan_id);

        if ($plan->monthly_price <= 0) {
            return response()->json(['message' => 'Free plan does not require payment.'], 400);
        }

        $txRef = 'tx_' . uniqid();
        $payload = [
            'tx_ref' => $txRef,
            'amount' => $plan->monthly_price,
            'currency' => $plan->currency ?? 'USD',
            'redirect_url' => config('services.flutterwave.redirect_url'),
            'customer' => [
                'email' => $user->email,
                'name' => $user->name,
            ],
            'meta' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
            'customizations' => [
                'title' => config('app.name') . ' Subscription',
                'description' => 'Payment for ' . $plan->name . ' plan',
            ],
        ];

        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->post('https://api.flutterwave.com/v3/payments', $payload);

        if ($response->failed() || !$response->json('status') === 'success') {
            Log::error('Flutterwave init failed', ['response' => $response->json()]);
            return response()->json(['message' => 'Unable to initialize payment.'], 500);
        }

        return response()->json([
            'checkout_url' => $response->json('data.link'),
            'tx_ref' => $txRef,
        ]);
    }
}