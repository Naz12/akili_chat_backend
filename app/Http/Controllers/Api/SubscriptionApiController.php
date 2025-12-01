<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\Request;
use App\Traits\DetectsRegion;
use App\Traits\AddsCorsHeaders;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;

class SubscriptionApiController extends Controller
{
    use DetectsRegion, AddsCorsHeaders;

    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Subscribe the authenticated user to a new plan.
     */
    public function store(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'nullable|string|in:stripe,chapa,telebirr', // Optional - will be auto-selected based on region
        ]);
    
        $user   = $request->user();
        $region = $this->getRegion($request);
        
        // Use user's region from database if available, otherwise use route region
        $userRegion = $user->region ?? $region;
    
        $plan = Plan::where('id', $request->plan_id)
            ->where('region', $region)
            ->with('aiEngine')
            ->first();
            
        if (!$plan) {
            // Check if plan exists but wrong region
            $planExists = Plan::where('id', $request->plan_id)->exists();
            if ($planExists) {
                $response = response()->json([
                    'message' => 'Plan not available for your region. Please select a plan for ' . $region . ' region.',
                ], 422);
            } else {
                $response = response()->json([
                    'message' => 'Plan not found.',
                ], 422);
            }
            return $this->addCorsHeaders($response, $request);
        }
    
        // Free plan: no payment required
        if ($plan->monthly_price == 0) {
            $hasUsedTrial = Subscription::where('user_id', $user->id)
                ->where('plan_id', $plan->id)
                ->exists();
    
            if ($hasUsedTrial) {
                $response = response()->json([
                    'message' => 'Trial plan already used. Please select a paid plan.',
                ], 403);
                return $this->addCorsHeaders($response, $request);
            }
    
            // Prevent multiple active subscriptions with transaction lock
            $subscription = DB::transaction(function () use ($user, $plan) {
                // Lock user's subscriptions to prevent race conditions
                $existingActive = Subscription::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                
                if ($existingActive) {
                    // Cancel previous active subscription
                    $existingActive->update([
                        'is_active' => false,
                        'end_date'  => now(),
                    ]);
                }
                
                // Calculate end_date based on billing cycle (default 30 days for free)
                $billingCycle = $plan->billing_cycle ?? 'monthly';
                $endDate = match($billingCycle) {
                    'quarterly' => now()->addMonths(3),
                    'annual' => now()->addMonths(12),
                    default => now()->addDays(30), // monthly default for free
                };
                
                return Subscription::create([
                    'user_id'     => $user->id,
                    'plan_id'     => $plan->id,
                    'start_date'  => now(),
                    'end_date'    => $endDate,
                    'tokens_used' => 0,
                    'is_active'   => true,
                    'auto_renew'  => false,
                    'metadata'    => [
                        'billing_cycle' => $billingCycle,
                    ],
                ]);
            });
    
            $response = response()->json([
                'message' => 'Free subscription activated.',
                'subscription' => new SubscriptionResource($subscription->load('plan.aiEngine')),
            ]);
            return $this->addCorsHeaders($response, $request);
        }
    
        // Paid plan: auto-select payment method based on region if not provided
        $paymentMethod = $request->payment_method;
        
        if (!$paymentMethod) {
            // Auto-select based on user's region: Stripe for intl, Chapa for local
            if ($userRegion === 'intl') {
                $paymentMethod = 'stripe';
                Log::info('Auto-selected Stripe for international user', [
                    'user_id' => $user->id,
                    'region' => $userRegion,
                ]);
            } else {
                $paymentMethod = 'chapa';
                Log::info('Auto-selected Chapa for local user', [
                    'user_id' => $user->id,
                    'region' => $userRegion,
                ]);
            }
        }
        
        // Validate payment method is supported
        if (!in_array($paymentMethod, ['stripe', 'chapa', 'telebirr'])) {
            $response = response()->json([
                'message' => 'Unsupported payment method. Use "stripe" for international or "chapa" for local.',
            ], 422);
            return $this->addCorsHeaders($response, $request);
        }
    
        // Prevent multiple active subscriptions with transaction lock
        $subscription = DB::transaction(function () use ($user, $plan, $request, $paymentMethod) {
            // Lock user's subscriptions to prevent race conditions
            $existingActive = Subscription::where('user_id', $user->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            
            if ($existingActive) {
                // Cancel previous active subscription
                $existingActive->update([
                    'is_active' => false,
                    'end_date'  => now(),
                ]);
            }
            
            // Calculate end_date based on billing cycle
            $billingCycle = $plan->billing_cycle ?? 'monthly';
            $endDate = match($billingCycle) {
                'quarterly' => now()->addMonths(3),
                'annual' => now()->addMonths(12),
                default => now()->addMonths(1), // monthly
            };
            
            // Create subscription in "pending" state
            return Subscription::create([
                'user_id'     => $user->id,
                'plan_id'     => $plan->id,
                'start_date'  => now(),
                'end_date'    => $endDate,
                'tokens_used' => 0,
                'is_active'   => false,
                'auto_renew'  => true,
                'metadata'    => [
                    'payment_method' => $paymentMethod,
                    'billing_cycle' => $billingCycle,
                    'auto_selected' => !$request->payment_method, // Track if we auto-selected
                ],
            ]);
        });
    
        // Redirect/return appropriate payment flow
        if ($paymentMethod === 'telebirr') {
            return $this->initiateTelebirrPayment($user, $plan, $subscription);
        }
    
        if (in_array($paymentMethod, ['stripe', 'chapa'])) {
            return $this->initiatePayment($user, $plan, $subscription, $paymentMethod);
        }
    
        $response = response()->json(['message' => 'Unsupported payment method.'], 422);
        return $this->addCorsHeaders($response, $request);
    }
    

    /**
     * Get the current active subscription.
     */
    public function current(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        // Filter by region to ensure we get the correct subscription for the user's region
        $subscription = Subscription::with('plan.aiEngine')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now()) // Also check that subscription hasn't expired
            ->orderByDesc('end_date') // Get the most recent active subscription
            ->first();

        if (!$subscription) {
            $defaultPlan = Plan::where('region', $region)
                ->where('is_default', true)
                ->with('aiEngine')
                ->first();

            if (!$defaultPlan) {
                $response = response()->json(['message' => 'No default plan is configured.'], 500);
                return $this->addCorsHeaders($response, $request);
            }

            $response = response()->json([
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
            return $this->addCorsHeaders($response, $request);
        }

        $resource = new SubscriptionResource($subscription);
        $response = response()->json($resource->toArray($request));
        return $this->addCorsHeaders($response, $request);
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


    /**
     * Initiate payment using PaymentManager (for Stripe and Chapa).
     */
    protected function initiatePayment($user, $plan, $subscription, $provider)
    {
        try {
            $paymentData = [
                'amount' => $plan->monthly_price,
                'currency' => $plan->currency ?? 'USD',
                'provider' => $provider,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'subscription_id' => $subscription->id,
                    'description' => "Subscription to {$plan->name}",
                ],
                'description' => "Subscription to {$plan->name}",
            ];

            // For Stripe international users, create subscription checkout
            if ($provider === 'stripe' && $user->region === 'intl') {
                $stripeService = app(\App\Services\Payment\Providers\StripePaymentService::class);
                
                // Create or get Stripe Customer
                $customerId = $stripeService->getOrCreateCustomer(
                    $user->email,
                    $user->name,
                    [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'subscription_id' => $subscription->id,
                    ]
                );

                // Add subscription-specific data
                $paymentData['is_subscription'] = true;
                $paymentData['customer_id'] = $customerId;
                $paymentData['billing_cycle'] = $plan->billing_cycle ?? 'monthly';

                Log::info('Creating Stripe subscription checkout', [
                    'user_id' => $user->id,
                    'customer_id' => $customerId,
                    'plan_id' => $plan->id,
                ]);
            }

            // Create payment using PaymentManager
            $payment = $this->paymentManager->initiatePayment($user, $paymentData);

            // Get checkout URL from gateway response
            $gatewayResponse = json_decode($payment->gateway_response, true);
            $checkoutUrl = $gatewayResponse['checkout_url'] ?? null;

            $response = response()->json([
                'payment_method' => $provider,
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'checkout_url' => $checkoutUrl,
                'subscription_id' => $subscription->id,
                'status' => 'pending',
            ]);

            return $this->addCorsHeaders($response, request());
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            $response = response()->json([
                'message' => 'Failed to initiate payment: ' . $e->getMessage(),
            ], 500);

            return $this->addCorsHeaders($response, request());
        }
    }

    /**
     * Initiate Telebirr payment (legacy, not yet in payment module).
     */
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
    
        // 3. ✅ Verify Signature (REQUIRED - reject if mismatch)
        $secret = config('services.telebirr.secret');
        if (!$secret) {
            Log::error('Telebirr secret not configured');
            return response()->json(['message' => 'Webhook configuration error'], 500);
        }
        
        $rawPayload = $request->getContent();
        $providedSignature = $request->header('X-Telebirr-Signature') 
            ?? $request->header('X-Signature')
            ?? $request->input('signature');
    
        if (!$providedSignature) {
            Log::error('Telebirr signature missing', ['headers' => $request->headers->all()]);
            return response()->json(['message' => 'Signature required'], 403);
        }
    
        $calculatedSignature = hash_hmac('sha256', $rawPayload, $secret);
    
        if (!hash_equals($providedSignature, $calculatedSignature)) {
            Log::error('❌ Telebirr Signature Mismatch - REJECTING webhook', [
                'provided' => $providedSignature,
                'calculated' => $calculatedSignature,
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }
        
        Log::info('✅ Telebirr webhook signature verified');
    
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

    /**
     * List all user subscriptions (active and inactive)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscriptions = Subscription::with('plan.aiEngine')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->orderByDesc('start_date')
            ->paginate(20);

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * Get single subscription details
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan.aiEngine')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->firstOrFail();

        return new SubscriptionResource($subscription);
    }

    /**
     * Update subscription (auto_renew, payment_method)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->firstOrFail();

        $request->validate([
            'auto_renew' => 'sometimes|boolean',
            'payment_method' => 'sometimes|string',
        ]);

        if ($request->has('auto_renew')) {
            $subscription->auto_renew = $request->auto_renew;
        }

        if ($request->has('payment_method')) {
            $metadata = $subscription->metadata ?? [];
            $metadata['payment_method'] = $request->payment_method;
            $subscription->metadata = $metadata;
        }

        $subscription->save();

        return new SubscriptionResource($subscription->load('plan.aiEngine'));
    }

    /**
     * Cancel subscription
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->firstOrFail();

        $subscription->update([
            'is_active' => false,
            'auto_renew' => false,
            'end_date' => now(),
        ]);

        return response()->json([
            'message' => 'Subscription cancelled successfully',
            'subscription' => new SubscriptionResource($subscription->load('plan.aiEngine')),
        ]);
    }

    /**
     * Check renewal status
     */
    public function renewalStatus(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        $nextRenewalDate = $subscription->end_date;
        $daysUntilRenewal = now()->diffInDays($nextRenewalDate, false);
        $paymentMethod = $subscription->metadata['payment_method'] ?? null;

        return response()->json([
            'subscription_id' => $subscription->id,
            'auto_renew' => $subscription->auto_renew,
            'next_renewal_date' => $nextRenewalDate->toIso8601String(),
            'days_until_renewal' => $daysUntilRenewal,
            'payment_method' => $paymentMethod,
            'payment_method_status' => $paymentMethod ? 'configured' : 'not_configured',
            'grace_period_ends_at' => $subscription->grace_period_ends_at?->toIso8601String(),
            'payment_failure_count' => $subscription->payment_failure_count ?? 0,
        ]);
    }

    /**
     * Manually trigger renewal
     */
    public function renew(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->firstOrFail();

        $renewalService = app(\App\Services\SubscriptionRenewalService::class);
        $result = $renewalService->renewSubscription($subscription);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Renewal initiated successfully',
                'subscription' => new SubscriptionResource($subscription->fresh()->load('plan.aiEngine')),
            ]);
        } else {
            return response()->json([
                'message' => $result['reason'] ?? 'Renewal failed',
            ], 400);
        }
    }

    /**
     * Get available upgrade options
     */
    public function upgradeOptions(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $currentSubscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if (!$currentSubscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        $currentPlan = $currentSubscription->plan;
        $currentPrice = $currentPlan->monthly_price;

        $upgradeOptions = Plan::with('aiEngine')
            ->where('region', $region)
            ->where('is_active', true)
            ->where('monthly_price', '>', $currentPrice)
            ->orderBy('monthly_price')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'monthly_price' => $plan->monthly_price,
                    'max_tokens' => $plan->max_tokens,
                    'daily_message_limit' => $plan->daily_message_limit,
                    'description' => $plan->description,
                ];
            });

        return response()->json([
            'current_plan' => [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'monthly_price' => $currentPrice,
            ],
            'upgrade_options' => $upgradeOptions,
        ]);
    }

    /**
     * Get available downgrade options
     */
    public function downgradeOptions(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $currentSubscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if (!$currentSubscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        $currentPlan = $currentSubscription->plan;
        $currentPrice = $currentPlan->monthly_price;

        $downgradeOptions = Plan::with('aiEngine')
            ->where('region', $region)
            ->where('is_active', true)
            ->where('monthly_price', '<', $currentPrice)
            ->orderByDesc('monthly_price')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'monthly_price' => $plan->monthly_price,
                    'max_tokens' => $plan->max_tokens,
                    'daily_message_limit' => $plan->daily_message_limit,
                    'description' => $plan->description,
                ];
            });

        return response()->json([
            'current_plan' => [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'monthly_price' => $currentPrice,
            ],
            'downgrade_options' => $downgradeOptions,
        ]);
    }

    /**
     * Get payment failure history for subscription
     */
    public function paymentFailureHistory(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        return response()->json([
            'subscription_id' => $subscription->id,
            'payment_failure_count' => $subscription->payment_failure_count ?? 0,
            'grace_period_ends_at' => $subscription->grace_period_ends_at?->toIso8601String(),
            'is_in_grace_period' => $subscription->isInGracePeriod(),
            'has_grace_period_expired' => $subscription->hasGracePeriodExpired(),
        ]);
    }
}