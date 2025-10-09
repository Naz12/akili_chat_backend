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
            'tokens_limit' => $active->plan->token_limit ?? 0,
            'remaining'    => max(0, ($active->plan->token_limit ?? 0) - $active->tokens_used),
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
}