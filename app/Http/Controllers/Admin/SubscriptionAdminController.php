<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class SubscriptionAdminController extends Controller
{
    public function index()
    {
        $subscriptions = Subscription::with('user', 'plan')->latest()->paginate(20);
        return view('admin.subscriptions.index', compact('subscriptions'));
    }

    public function create()
    {
        $users = User::all();
        $plans = Plan::where('is_active', true)->get();
        return view('admin.subscriptions.create', compact('users', 'plans'));
    }

        public function edit(Subscription $subscription)
    {
        $users = User::all();
        $plans = Plan::where('is_active', true)->get();

        return view('admin.subscriptions.edit', compact('subscription', 'users', 'plans'));
    }

    public function update(Request $request, Subscription $subscription)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'auto_renew' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'grace_period_ends_at' => 'nullable|date',
            'payment_failure_count' => 'nullable|integer|min:0',
            'payment_method' => 'nullable|string',
            'stripe_subscription_id' => 'nullable|string',
            'billing_cycle' => 'nullable|string',
        ]);

        // Update metadata
        $metadata = $subscription->metadata ?? [];
        if ($request->has('payment_method')) {
            $metadata['payment_method'] = $request->payment_method;
        }
        if ($request->has('stripe_subscription_id')) {
            $metadata['stripe_subscription_id'] = $request->stripe_subscription_id;
        }
        if ($request->has('billing_cycle')) {
            $metadata['billing_cycle'] = $request->billing_cycle;
        }

        $subscription->update([
            'user_id'    => $request->user_id,
            'plan_id'    => $request->plan_id,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'auto_renew' => $request->has('auto_renew') ? (bool) $request->auto_renew : $subscription->auto_renew,
            'is_active'  => $request->has('is_active') ? (bool) $request->is_active : $subscription->is_active,
            'grace_period_ends_at' => $request->grace_period_ends_at ? $request->grace_period_ends_at : null,
            'payment_failure_count' => $request->payment_failure_count ?? $subscription->payment_failure_count ?? 0,
            'metadata' => $metadata,
        ]);

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'Subscription updated successfully.');
    }


    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:plans,id',
            'auto_renew' => 'nullable|boolean',
            'payment_method' => 'nullable|string',
            'billing_cycle' => 'nullable|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $plan = Plan::findOrFail($request->plan_id);

        // End previous active subscriptions
        Subscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'end_date' => now()]);

        // Calculate end_date based on billing cycle
        $billingCycle = $request->billing_cycle ?? $plan->billing_cycle ?? 'monthly';
        $endDate = match($billingCycle) {
            'quarterly' => now()->addMonths(3),
            'annual' => now()->addMonths(12),
            default => now()->addMonths(1),
        };

        // Build metadata
        $metadata = [];
        if ($request->payment_method) {
            $metadata['payment_method'] = $request->payment_method;
        }
        if ($request->billing_cycle) {
            $metadata['billing_cycle'] = $request->billing_cycle;
        }

        // Create new subscription
        Subscription::create([
            'user_id'     => $user->id,
            'plan_id'     => $plan->id,
            'start_date'  => now(),
            'end_date'    => $endDate,
            'tokens_used' => 0,
            'is_active'   => true,
            'auto_renew'  => $request->has('auto_renew') ? (bool) $request->auto_renew : ($plan->monthly_price > 0),
            'metadata'    => $metadata,
        ]);

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'New subscription assigned successfully.');
    }

    public function destroy(Subscription $subscription)
    {
        $subscription->delete();

        return back()->with('success', 'Subscription deleted.');
    }

    /**
     * List subscriptions in grace period
     */
    public function gracePeriod()
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '>=', now())
            ->where('is_active', true)
            ->latest('grace_period_ends_at')
            ->paginate(20);

        return view('admin.subscriptions.grace_period', compact('subscriptions'));
    }

    /**
     * Extend grace period for a subscription
     */
    public function extendGracePeriod(Request $request, Subscription $subscription)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:30',
        ]);

        $newGracePeriod = $subscription->grace_period_ends_at 
            ? $subscription->grace_period_ends_at->copy()->addDays($request->days)
            : now()->addDays($request->days);

        $subscription->update([
            'grace_period_ends_at' => $newGracePeriod,
        ]);

        return back()->with('success', "Grace period extended by {$request->days} days.");
    }

    /**
     * Process grace period expiration manually
     */
    public function processGraceExpiration(Subscription $subscription)
    {
        $failureService = app(\App\Services\PaymentFailureService::class);
        $failureService->processExpiredGracePeriods();

        return back()->with('success', 'Grace period expiration processed.');
    }

    /**
     * List subscriptions with payment failures
     */
    public function paymentFailures()
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->where(function($query) {
                $query->where('payment_failure_count', '>', 0)
                      ->orWhereNotNull('grace_period_ends_at');
            })
            ->latest('payment_failure_count')
            ->paginate(20);

        return view('admin.subscriptions.payment_failures', compact('subscriptions'));
    }

    /**
     * List expiring subscriptions
     */
    public function expiring(Request $request)
    {
        $days = $request->input('days', 7);
        
        $subscriptions = Subscription::with(['user', 'plan'])
            ->where('is_active', true)
            ->where('auto_renew', true)
            ->whereBetween('end_date', [now(), now()->addDays($days)])
            ->orderBy('end_date')
            ->paginate(20);

        return view('admin.subscriptions.expiring', compact('subscriptions', 'days'));
    }

    /**
     * Manually trigger renewal
     */
    public function triggerRenewal(Subscription $subscription)
    {
        $renewalService = app(\App\Services\SubscriptionRenewalService::class);
        $result = $renewalService->renewSubscription($subscription);

        return back()->with('success', $result['message'] ?? 'Renewal processed.');
    }

    /**
     * List Stripe subscriptions
     */
    public function stripeSubscriptions()
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereRaw("JSON_EXTRACT(metadata, '$.stripe_subscription_id') LIKE 'sub_%'")
                      ->orWhereRaw("JSON_EXTRACT(metadata, '$.stripe_subscription_id') IS NOT NULL");
            })
            ->latest()
            ->paginate(20);

        return view('admin.subscriptions.stripe', compact('subscriptions'));
    }

    /**
     * Reset payment failure count
     */
    public function resetFailureCount(Subscription $subscription)
    {
        $subscription->update([
            'payment_failure_count' => 0,
            'grace_period_ends_at' => null,
        ]);

        return back()->with('success', 'Payment failure count reset.');
    }
}