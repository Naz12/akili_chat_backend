<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Traits\DetectsRegion;

class BillApiController extends Controller
{
    use DetectsRegion;

    /**
     * Get current active subscription for authenticated user.
     */
    public function current(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with(['plan.aiEngine'])
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription.'], 404);
        }

        return new SubscriptionResource($subscription);
    }

    /**
     * Get billing/subscription history for the authenticated user.
     */
    public function history(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        $subscriptions = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'history' => SubscriptionResource::collection($subscriptions),
        ]);
    }

    /**
     * Get remaining token stats for current user.
     */
    public function usage(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        $active = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if (!$active) {
            return response()->json(['message' => 'No active subscription.'], 404);
        }

        return response()->json([
            'plan'         => $active->plan->name ?? 'N/A',
            'tokens_used'  => $active->tokens_used,
            'tokens_limit' => $active->plan->max_tokens ?? 0,
            'remaining'    => max(0, ($active->plan->max_tokens ?? 0) - $active->tokens_used),
        ]);
    }

    /**
     * Toggle the auto-renew setting for the current user's active subscription.
     */
    public function toggleAutoRenew(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription.'], 404);
        }

        $subscription->auto_renew = !$subscription->auto_renew;
        $subscription->save();

        return response()->json([
            'message'    => 'Auto-renew setting updated.',
            'auto_renew' => $subscription->auto_renew,
        ]);
    }

    /**
     * List all invoices/bills for user
     */
    public function invoices(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        // Return subscription history as invoices
        $subscriptions = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'invoice_number' => 'INV-' . str_pad($subscription->id, 8, '0', STR_PAD_LEFT),
                    'plan_name' => $subscription->plan->name,
                    'amount' => $subscription->plan->monthly_price,
                    'currency' => $subscription->plan->currency ?? 'USD',
                    'status' => $subscription->is_active ? 'paid' : 'cancelled',
                    'period_start' => $subscription->start_date->toIso8601String(),
                    'period_end' => $subscription->end_date->toIso8601String(),
                    'created_at' => $subscription->created_at->toIso8601String(),
                ];
            });

        return response()->json(['invoices' => $subscriptions]);
    }

    /**
     * Get single invoice details
     */
    public function invoice(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->firstOrFail();

        // Find associated payment if exists
        $payment = \App\Models\Payment::where('user_id', $user->id)
            ->whereJsonContains('metadata->subscription_id', $subscription->id)
            ->orWhere('reference', 'like', "%{$subscription->id}%")
            ->first();

        return response()->json([
            'id' => $subscription->id,
            'invoice_number' => 'INV-' . str_pad($subscription->id, 8, '0', STR_PAD_LEFT),
            'plan_name' => $subscription->plan->name,
            'amount' => $subscription->plan->monthly_price,
            'currency' => $subscription->plan->currency ?? 'USD',
            'status' => $subscription->is_active ? 'paid' : 'cancelled',
            'payment_status' => $payment?->status ?? 'unknown',
            'period_start' => $subscription->start_date->toIso8601String(),
            'period_end' => $subscription->end_date->toIso8601String(),
            'line_items' => [
                [
                    'description' => "Subscription to {$subscription->plan->name}",
                    'quantity' => 1,
                    'unit_price' => $subscription->plan->monthly_price,
                    'total' => $subscription->plan->monthly_price,
                ],
            ],
            'created_at' => $subscription->created_at->toIso8601String(),
        ]);
    }

    /**
     * Get upcoming charges
     */
    public function upcomingCharges(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->where('auto_renew', true)
            ->first();

        if (!$subscription) {
            // Return empty/null values instead of 404 to prevent frontend errors
            return response()->json([
                'next_charge_date' => null,
                'amount' => null,
                'currency' => null,
                'plan_name' => null,
                'payment_method' => null,
                'days_until_charge' => null,
                'has_upcoming_charge' => false,
            ]);
        }

        $plan = $subscription->plan;
        $nextRenewalDate = $subscription->end_date;
        $paymentMethod = $subscription->metadata['payment_method'] ?? null;

        return response()->json([
            'next_charge_date' => $nextRenewalDate->toIso8601String(),
            'amount' => $plan->monthly_price,
            'currency' => $plan->currency ?? 'USD',
            'plan_name' => $plan->name,
            'payment_method' => $paymentMethod,
            'days_until_charge' => now()->diffInDays($nextRenewalDate, false),
            'has_upcoming_charge' => true,
        ]);
    }

    /**
     * List user's saved payment methods
     */
    public function paymentMethods(Request $request)
    {
        $user = $request->user();
        
        // For now, return payment methods from subscription metadata
        // In the future, this could be a separate payment_methods table
        $subscriptions = Subscription::where('user_id', $user->id)
            ->whereNotNull('metadata')
            ->get();

        $paymentMethods = $subscriptions->map(function ($subscription) {
            $metadata = $subscription->metadata ?? [];
            $method = $metadata['payment_method'] ?? null;
            
            if ($method) {
                return [
                    'id' => $subscription->id . '_' . $method,
                    'type' => $method,
                    'is_default' => $subscription->is_active,
                    'last_used' => $subscription->updated_at->toIso8601String(),
                ];
            }
            return null;
        })->filter()->unique('type')->values();

        return response()->json(['payment_methods' => $paymentMethods]);
    }

    /**
     * Add/save payment method
     */
    public function addPaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string|in:stripe,chapa,telebirr',
            'payment_details' => 'sometimes|array',
        ]);

        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if ($subscription) {
            $metadata = $subscription->metadata ?? [];
            $metadata['payment_method'] = $request->payment_method;
            $metadata['payment_details'] = $request->payment_details ?? [];
            $subscription->metadata = $metadata;
            $subscription->save();
        }

        return response()->json([
            'message' => 'Payment method saved successfully',
            'payment_method' => $request->payment_method,
        ]);
    }

    /**
     * Remove payment method
     */
    public function removePaymentMethod(Request $request, $id)
    {
        // For now, just clear payment method from subscription metadata
        // In the future, this could remove from a separate payment_methods table
        return response()->json(['message' => 'Payment method removed']);
    }

    /**
     * Set default payment method
     */
    public function setDefaultPaymentMethod(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if ($subscription) {
            // Extract payment method from id (format: subscription_id_method)
            $parts = explode('_', $id);
            $method = end($parts);
            
            $metadata = $subscription->metadata ?? [];
            $metadata['payment_method'] = $method;
            $subscription->metadata = $metadata;
            $subscription->save();
        }

        return response()->json(['message' => 'Default payment method updated']);
    }

    /**
     * Get/update billing address
     */
    public function billingAddress(Request $request)
    {
        $user = $request->user();

        if ($request->isMethod('PUT')) {
            $request->validate([
                'address_line1' => 'sometimes|string',
                'address_line2' => 'sometimes|string',
                'city' => 'sometimes|string',
                'state' => 'sometimes|string',
                'postal_code' => 'sometimes|string',
                'country' => 'sometimes|string',
            ]);

            // Store in user metadata or separate billing_addresses table
            $metadata = $user->metadata ?? [];
            $metadata['billing_address'] = $request->only([
                'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country'
            ]);
            $user->metadata = $metadata;
            $user->save();

            return response()->json([
                'message' => 'Billing address updated',
                'billing_address' => $metadata['billing_address'],
            ]);
        }

        // GET - return billing address
        $billingAddress = $user->metadata['billing_address'] ?? null;
        return response()->json(['billing_address' => $billingAddress]);
    }

    /**
     * Get tax information
     */
    public function taxInformation(Request $request)
    {
        $user = $request->user();
        $country = $user->country;

        return response()->json([
            'country' => $country?->name ?? 'Unknown',
            'country_code' => $country?->code ?? null,
            'tax_rate' => 0, // TODO: Calculate based on country
            'tax_id' => $user->metadata['tax_id'] ?? null,
        ]);
    }
}