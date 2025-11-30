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
        $user = $request->user();

        $query = $user->tokenUsages()
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region));

        // Date range filtering
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $usages = $query->latest()->paginate($request->get('per_page', 50));

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

    /**
     * Get daily usage breakdown (last 30 days)
     */
    public function daily(Request $request)
    {
        $region = $this->getRegion($request);
        $user = $request->user();

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

        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $tokens = TokenUsage::where('user_id', $user->id)
                ->where('subscription_id', $subscription->id)
                ->whereDate('created_at', $date)
                ->sum('tokens_used');
            
            $messages = \App\Models\ChatMessage::where('user_id', $user->id)
                ->whereDate('created_at', $date)
                ->where('role', 'user')
                ->count();

            $days[] = [
                'date' => $date,
                'tokens_used' => $tokens,
                'messages_count' => $messages,
            ];
        }

        return response()->json(['daily_usage' => $days]);
    }

    /**
     * Get weekly usage summary (last 12 weeks)
     */
    public function weekly(Request $request)
    {
        $region = $this->getRegion($request);
        $user = $request->user();

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

        $weeks = [];
        for ($i = 11; $i >= 0; $i--) {
            $startDate = now()->subWeeks($i)->startOfWeek();
            $endDate = now()->subWeeks($i)->endOfWeek();

            $tokens = TokenUsage::where('user_id', $user->id)
                ->where('subscription_id', $subscription->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('tokens_used');
            
            $messages = \App\Models\ChatMessage::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('role', 'user')
                ->count();

            $weeks[] = [
                'week_start' => $startDate->toDateString(),
                'week_end' => $endDate->toDateString(),
                'tokens_used' => $tokens,
                'messages_count' => $messages,
            ];
        }

        return response()->json(['weekly_usage' => $weeks]);
    }

    /**
     * Get monthly usage summary (last 12 months)
     */
    public function monthly(Request $request)
    {
        $region = $this->getRegion($request);
        $user = $request->user();

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

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();

            $tokens = TokenUsage::where('user_id', $user->id)
                ->where('subscription_id', $subscription->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('tokens_used');
            
            $messages = \App\Models\ChatMessage::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('role', 'user')
                ->count();

            $months[] = [
                'month' => $startDate->format('Y-m'),
                'month_name' => $startDate->format('F Y'),
                'tokens_used' => $tokens,
                'messages_count' => $messages,
            ];
        }

        return response()->json(['monthly_usage' => $months]);
    }

    /**
     * Get usage analytics data for charts
     */
    public function analytics(Request $request)
    {
        $region = $this->getRegion($request);
        $user = $request->user();

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

        // Get last 30 days of token usage
        $dailyTokens = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $tokens = TokenUsage::where('user_id', $user->id)
                ->where('subscription_id', $subscription->id)
                ->whereDate('created_at', $date)
                ->sum('tokens_used');
            
            $dailyTokens[] = [
                'date' => $date,
                'tokens' => $tokens,
            ];
        }

        // Get peak hours (messages per hour of day)
        $peakHours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $count = \App\Models\ChatMessage::where('user_id', $user->id)
                ->whereRaw('HOUR(created_at) = ?', [$hour])
                ->where('role', 'user')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
            
            $peakHours[] = [
                'hour' => $hour,
                'messages_count' => $count,
            ];
        }

        return response()->json([
            'tokens_over_time' => $dailyTokens,
            'peak_hours' => $peakHours,
        ]);
    }

    /**
     * Get detailed quota status
     */
    public function quotaStatus(Request $request)
    {
        $region = $this->getRegion($request);
        $user = $request->user();

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

        $plan = $subscription->plan;
        $tokensUsed = $subscription->tokens_used ?? 0;
        $maxTokens = $plan->max_tokens ?? 0;
        $tokensRemaining = max(0, $maxTokens - $tokensUsed);
        $tokensPercentage = $maxTokens > 0 ? ($tokensUsed / $maxTokens) * 100 : 0;

        $today = now()->toDateString();
        $messagesUsed = \App\Models\ChatMessage::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->where('role', 'user')
            ->count();
        $messagesRemaining = max(0, ($plan->daily_message_limit ?? 0) - $messagesUsed);
        $messagesPercentage = ($plan->daily_message_limit ?? 0) > 0 
            ? ($messagesUsed / ($plan->daily_message_limit ?? 1)) * 100 
            : 0;

        $daysRemaining = now()->diffInDays($subscription->end_date, false);

        $warnings = [];
        if ($tokensPercentage >= 80) {
            $warnings[] = 'token_quota_warning';
        }
        if ($tokensPercentage >= 100) {
            $warnings[] = 'token_quota_exceeded';
        }
        if ($messagesPercentage >= 80) {
            $warnings[] = 'message_limit_warning';
        }
        if ($messagesPercentage >= 100) {
            $warnings[] = 'message_limit_exceeded';
        }

        return response()->json([
            'tokens' => [
                'used' => $tokensUsed,
                'limit' => $maxTokens,
                'remaining' => $tokensRemaining,
                'percentage_used' => round($tokensPercentage, 2),
            ],
            'messages' => [
                'used_today' => $messagesUsed,
                'limit' => $plan->daily_message_limit ?? 0,
                'remaining_today' => $messagesRemaining,
                'percentage_used' => round($messagesPercentage, 2),
            ],
            'subscription' => [
                'days_remaining' => $daysRemaining,
                'end_date' => $subscription->end_date->toIso8601String(),
            ],
            'warnings' => $warnings,
        ]);
    }

    /**
     * Export usage data
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => 'sometimes|in:csv,json',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        $region = $this->getRegion($request);
        $user = $request->user();
        $format = $request->get('format', 'json');

        $query = $user->tokenUsages()
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region));

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $usages = $query->latest()->get();

        if ($format === 'csv') {
            $csv = "Date,Tokens Used,Cost,Engine\n";
            foreach ($usages as $usage) {
                $csv .= sprintf(
                    "%s,%s,%s,%s\n",
                    $usage->created_at->toDateString(),
                    $usage->tokens_used,
                    $usage->cost,
                    $usage->engine->name ?? 'N/A'
                );
            }
            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="usage_export.csv"',
            ]);
        }

        return response()->json(['data' => $usages]);
    }
}