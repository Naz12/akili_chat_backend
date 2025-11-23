<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Traits\DetectsRegion;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;

class SubscriptionApiController extends Controller
{
    use DetectsRegion;

    /**
     * Subscribe the authenticated user to a new plan.
     */
    public function store(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'nullable|string', // Required only for paid plans
        ]);
    
        $user   = $request->user();
        $region = $this->getRegion($request);
    
        $plan = Plan::where('id', $request->plan_id)
            ->where('region', $region)
            ->with('aiEngine')
            ->firstOrFail();
    
        // Free plan: no payment required
        if ($plan->monthly_price == 0) {
            $hasUsedTrial = Subscription::where('user_id', $user->id)
                ->where('plan_id', $plan->id)
                ->exists();
    
            if ($hasUsedTrial) {
                return response()->json([
                    'message' => 'Trial plan already used. Please select a paid plan.',
                ], 403);
            }
    
            // Cancel previous
            Subscription::where('user_id', $user->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date'  => now(),
                ]);
    
            $subscription = Subscription::create([
                'user_id'     => $user->id,
                'plan_id'     => $plan->id,
                'start_date'  => now(),
                'end_date'    => now()->addDays(30),
                'tokens_used' => 0,
                'is_active'   => true,
                'auto_renew'  => false,
            ]);
    
            return response()->json([
                'message' => 'Free subscription activated.',
                'subscription' => new SubscriptionResource($subscription->load('plan.aiEngine')),
            ]);
        }
    
        // Paid plan: require valid payment method
        if (!$request->payment_method) {
            return response()->json([
                'message' => 'Payment method is required for paid plans.',
            ], 422);
        }
    
        // Cancel previous
        Subscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'end_date'  => now(),
            ]);
    
        // Create subscription in "pending" state
        $subscription = Subscription::create([
            'user_id'     => $user->id,
            'plan_id'     => $plan->id,
            'start_date'  => now(),
            'end_date'    => now()->addDay(), // temporary until payment verified
            'tokens_used' => 0,
            'is_active'   => false,
            'auto_renew'  => true,
            'status'      => 'pending', // if you use status column
            'payment_method' => $request->payment_method,
        ]);
    
        // Redirect/return appropriate payment flow
        if ($request->payment_method === 'telebirr') {
            return $this->initiateTelebirrPayment($user, $plan, $subscription);
        }
    
        if ($request->payment_method === 'stripe') {
            return $this->initiateStripePayment($user, $plan, $subscription);
        }
    
        return response()->json(['message' => 'Unsupported payment method.'], 422);
    }
    

    /**
     * Get the current active subscription.
     */
    public function current(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan.aiEngine')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            $defaultPlan = Plan::where('region', $region)
                ->where('is_default', true)
                ->with('aiEngine')
                ->first();

            if (!$defaultPlan) {
                return response()->json(['message' => 'No default plan is configured.'], 500);
            }

            return response()->json([
                'subscription' => null,
                'plan' => [
                    'id' => $defaultPlan->id,
                    'name' => $defaultPlan->name,
                    'max_tokens' => $defaultPlan->max_tokens,
                    'daily_message_limit' => $defaultPlan->daily_message_limit,
                    'ads_enabled' => $defaultPlan->ads_enabled,
                    'is_guest_mode' => true,
                    'engine' => [
                        'name' => optional($defaultPlan->aiEngine)->name,
                        'provider' => optional($defaultPlan->aiEngine)->provider,
                        'max_tokens' => optional($defaultPlan->aiEngine)->max_tokens,
                        'price_per_1k' => optional($defaultPlan->aiEngine)->price_per_1k,
                        'is_vision_support' => optional($defaultPlan->aiEngine)->is_vision_support,
                    ],
                ],
            ]);
        }

        return new SubscriptionResource($subscription);
    }

    /**
     * Check if the user has a valid subscription.
     */
        public function hasSubscription(Request $request)
    {
        $userId = $request->user()->id;

        // original existence check (fast COUNT query)
        $exists = Subscription::where('user_id', $userId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date',   '>=', now())
            ->exists();

        // if no active sub, flag is false
        if (!$exists) {
            return response()->json([
                'has_active_subscription' => false,
                'is_vision_support'       => false,
            ]);
        }

        // fetch the actual sub to inspect its engine
        $activeSub = Subscription::with('plan.aiEngine')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'has_active_subscription' => true,
            'is_vision_support'       => (bool) $activeSub->plan->aiEngine?->is_vision_support,
        ]);
    }


        protected function initiateTelebirrPayment($user, $plan, $subscription)
    {
        // Replace with your actual Telebirr integration logic
        $tx_ref = 'telebirr_' . $subscription->id . '_' . now()->timestamp;

        // 🔁 You should send this to Telebirr's API instead
        $paymentData = [
            'amount' => $plan->monthly_price,
            'phone' => $user->phone ?? null,
            'reference' => $tx_ref,
            'description' => 'Subscription to ' . $plan->name,
            'subscription_id' => $subscription->id,
            'callback_url' => route('api.payment.telebirr.callback'),
        ];

        // 💡 You would POST this to Telebirr’s real API, but for now we mock it
        return response()->json([
            'payment_method' => 'telebirr',
            'tx_ref' => $tx_ref,
            'payment_url' => 'https://pay.telebirr.com/checkout?ref=' . $tx_ref,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);
    }


    public function handleTelebirrCallback(Request $request)
    {
        // 1. ✅ Optional: Block unauthorized IPs (replace with real ones from Telebirr)
        $allowedIps = ['196.188.56.10', '196.188.56.11']; // example IPs
        if (!in_array($request->ip(), $allowedIps)) {
            Log::warning('Unauthorized Telebirr IP', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized IP'], 403);
        }
    
        // 2. ✅ Log payload and headers for debugging
        Log::info('[Telebirr Callback]', [
            'ip' => $request->ip(),
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);
    
        // 3. ✅ Verify Signature
        $secret = config('services.telebirr.secret');
        $rawPayload = $request->getContent();
        $providedSignature = $request->header('X-Telebirr-Signature'); // Adjust header name if different
    
        $calculatedSignature = hash_hmac('sha256', $rawPayload, $secret);
    
        if (!hash_equals($providedSignature, $calculatedSignature)) {
            Log::error('Telebirr Signature Mismatch', [
                'provided' => $providedSignature,
                'calculated' => $calculatedSignature,
            ]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }
    
        // 4. ✅ Find and activate the subscription
        $tx_ref = $request->input('reference');
        $subscriptionId = $request->input('subscription_id');
    
        $subscription = Subscription::find($subscriptionId);
        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }
    
        $subscription->update([
            'is_active' => true,
            'status' => 'paid',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);
    
        Log::info('Telebirr Payment Successful', [
            'subscription_id' => $subscription->id,
            'tx_ref' => $tx_ref,
        ]);
    
        return response()->json(['message' => 'Payment confirmed and subscription activated']);
    }
    


}