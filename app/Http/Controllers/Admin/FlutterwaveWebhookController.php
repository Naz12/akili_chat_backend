<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Models\Subscription;

class FlutterwaveWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.flutterwave.secret_hash');
        $signature = $request->header('verif-hash');

        if (!$signature || $signature !== $secret) {
            Log::warning('❌ Flutterwave Webhook: Invalid signature', ['signature' => $signature]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? '';

        if ($event !== 'charge.completed') {
            Log::info('ℹ️ Flutterwave Webhook ignored event', ['event' => $event]);
            return response()->json(['message' => 'Event ignored'], 200);
        }

        $data = $payload['data'] ?? [];

        if (($data['status'] ?? '') !== 'successful') {
            Log::warning('❌ Payment not successful', ['data' => $data]);
            return response()->json(['message' => 'Payment not successful'], 400);
        }

        $meta = $data['meta'] ?? [];
        $userId = $meta['user_id'] ?? null;
        $planId = $meta['plan_id'] ?? null;

        if (!$userId || !$planId) {
            Log::error('❌ Missing metadata in Flutterwave payload', ['meta' => $meta]);
            return response()->json(['message' => 'Missing metadata'], 400);
        }

        $user = User::find($userId);
        $plan = Plan::find($planId);

        if (!$user || !$plan) {
            Log::error('❌ User or Plan not found', ['user_id' => $userId, 'plan_id' => $planId]);
            return response()->json(['message' => 'User or Plan not found'], 404);
        }

        // Deactivate any existing active subscriptions
        Subscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'end_date' => now(),
            ]);

        // Create new subscription
        Subscription::create([
            'user_id'     => $user->id,
            'plan_id'     => $plan->id,
            'start_date'  => now(),
            'end_date'    => now()->addDays(30),
            'tokens_used' => 0,
            'is_active'   => true,
            'auto_renew'  => false,
            'tx_ref'      => $data['tx_ref'] ?? null, // optional: add this field to your DB
        ]);

        Log::info('✅ Flutterwave payment success', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'tx_ref' => $data['tx_ref'] ?? null,
        ]);

        return response()->json(['status' => 'ok']);
    }
}