<?php

namespace App\Http\Controllers\Api;

use App\Models\TokenUsage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\DetectsRegion;

class TokenUsageApiController extends Controller
{
    use DetectsRegion;

    public function index(Request $request)
    {
        $region = $this->getRegion($request);

        $usages = $request->user()
            ->tokenUsages()
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region))
            ->latest()
            ->limit(50)
            ->get();

        return response()->json($usages);
    }

    public function stats(Request $request)
    {
        $region = $this->getRegion($request);
        $user = $request->user();

        // Query active subscription with region filter
        $subscription = $user->subscriptions()
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now())
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->with('plan')
            ->orderByDesc('end_date')
            ->first();

        if (!$subscription) {
            return response()->json(['message' => "No active {$region} subscription."], 404);
        }

        $today = now()->toDateString();
        $dailyUsage = TokenUsage::where('user_id', $user->id)
            ->where('subscription_id', $subscription->id)
            ->whereDate('created_at', $today)
            ->count();

        return response()->json([
            'total_tokens_used'     => $subscription->tokens_used ?? 0,
            'total_token_quota'     => $subscription->plan->max_tokens ?? 0,
            'daily_messages_used'   => $dailyUsage,
            'daily_message_limit'   => $subscription->plan->daily_message_limit ?? 0,
            'ads_enabled'           => $subscription->plan->ads_enabled ?? false,
        ]);
    }
}