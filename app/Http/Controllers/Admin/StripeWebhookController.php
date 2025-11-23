<?php

namespace App\Http\Controllers\Admin;

use App\Models\Subscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Stripe\Webhook as StripeWebhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret    = config('services.stripe.webhook_secret');
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Save raw webhook to database
        Webhook::create([
            'provider'   => 'stripe',
            'event_type' => 'raw',
            'signature'  => $signature,
            'payload'    => json_decode($payload, true),
        ]);

        // Validate event
        try {
            $event = StripeWebhook::constructEvent($payload, $signature, $secret);
        } catch (\Exception $e) {
            Log::error('❌ Stripe Webhook signature invalid', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Process successful checkout
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $userId = $session->metadata->user_id ?? null;
            $planId = $session->metadata->plan_id ?? null;

            if (!$userId || !$planId) {
                Log::warning('⚠️ Stripe Webhook: Missing metadata', ['session_id' => $session->id]);
                return response()->json(['message' => 'Missing metadata'], 400);
            }

            $user = User::find($userId);
            $plan = Plan::find($planId);

            if (!$user || !$plan) {
                Log::error('❌ Stripe Webhook: Invalid user or plan', [
                    'user_id' => $userId,
                    'plan_id' => $planId,
                ]);
                return response()->json(['message' => 'Invalid user or plan'], 404);
            }

            // Deactivate previous active subscriptions
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
                'auto_renew'  => true,
                'tx_ref'      => $session->id, // Optional: store Stripe session ID
            ]);

            Log::info('✅ Stripe subscription created', [
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'session_id' => $session->id,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}