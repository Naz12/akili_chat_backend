<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Webhook;

class ChapaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('Chapa-Signature');
        $secretKey = config('services.chapa.secret_key');

        $computedHash = hash_hmac('sha256', json_encode($payload), $secretKey);

        // Save raw webhook
        Webhook::create([
            'provider'   => 'chapa',
            'event_type' => $payload['event'] ?? 'unknown',
            'signature'  => $signature,
            'payload'    => $payload,
        ]);

        // Validate signature
        if (!hash_equals($computedHash, $signature)) {
            Log::warning('❌ Chapa Webhook: Invalid signature');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        if (($payload['event'] ?? '') === 'charge.success') {
            $data     = $payload['data'] ?? [];
            $metadata = $data['metadata'] ?? [];

            $userId = $metadata['user_id'] ?? null;
            $planId = $metadata['plan_id'] ?? null;

            if (!$userId || !$planId) {
                Log::error('⚠️ Chapa Webhook: Missing metadata');
                return response()->json(['message' => 'Missing metadata'], 400);
            }

            $user = User::find($userId);
            $plan = Plan::find($planId);

            if (!$user || !$plan) {
                Log::error('❌ Chapa Webhook: Invalid user or plan', compact('userId', 'planId'));
                return response()->json(['message' => 'User or Plan not found'], 404);
            }

            // Deactivate previous subscriptions
            Subscription::where('user_id', $user->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date'  => now(),
                ]);

            // Create new active subscription
            Subscription::create([
                'user_id'     => $user->id,
                'plan_id'     => $plan->id,
                'start_date'  => now(),
                'end_date'    => now()->addDays(30),
                'tokens_used' => 0,
                'is_active'   => true,
                'auto_renew'  => false,
                'tx_ref'      => $data['tx_ref'] ?? null,
            ]);

            Log::info('✅ Chapa payment success', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'tx_ref'  => $data['tx_ref'] ?? null,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}