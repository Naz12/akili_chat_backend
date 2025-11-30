<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use App\Traits\DetectsRegion;

class PlanApiController extends Controller
{
    use DetectsRegion;

    public function index(Request $request)
    {
        $region = $this->getRegion($request);
        $user = $request->user();

        if ($region === 'unknown') {
            return response()->json(['error' => 'Invalid region.'], 400);
        }

        $userCurrency = optional($user->country)->currency ?? 'USD';

        $query = Plan::with('aiEngine')
            ->where('region', $region)
            ->where('is_active', true);

        // Apply filters
        if ($request->filled('price_min')) {
            $query->where('monthly_price', '>=', $request->price_min);
        }
        if ($request->filled('price_max')) {
            $query->where('monthly_price', '<=', $request->price_max);
        }
        if ($request->filled('features')) {
            // Features filter could be an array of feature names
            // For now, we'll just apply basic filtering
            // In the future, this could filter by specific plan features
        }

        $plans = $query->orderBy('monthly_price')->get();

        $response = $plans->map(function ($plan) use ($userCurrency) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'monthly_price' => $plan->monthly_price,
                'currency' => $plan->currency ?? $userCurrency,
                'region' => $plan->region,
                'max_tokens' => $plan->max_tokens,
                'daily_message_limit' => $plan->daily_message_limit,
                'ads_enabled' => $plan->ads_enabled,
                'is_default' => $plan->is_default,
                'description' => $plan->description,
                'image_url' => $plan->image_url,
                'tag' => $plan->tag,
                'trial_days' => $plan->trial_days ?? 0, // 🟡 Only if you add this column
                'engine_id' => $plan->engine_id,
                'badge' => $plan->monthly_price == 0
                    ? 'Free'
                    : ($plan->monthly_price < 20 ? 'Recommended' : 'Best Value'),
                'engine' => [
                    'name' => optional($plan->aiEngine)->name,
                    'provider' => optional($plan->aiEngine)->provider,
                    'max_tokens' => optional($plan->aiEngine)->max_tokens,
                    'price_per_1k' => optional($plan->aiEngine)->price_per_1k,
                    'is_vision_support' => optional($plan->aiEngine)->is_vision_support,
                ],
            ];
        });

        return response()->json([
            'region' => $region,
            'currency' => $userCurrency,
            'plans' => $response,
        ]);
    }

    /**
     * Get single plan details
     */
    public function show(Request $request, $id)
    {
        $region = $this->getRegion($request);
        $user = $request->user();
        $userCurrency = optional($user?->country)->currency ?? 'USD';

        $plan = Plan::with('aiEngine')
            ->where('id', $id)
            ->where('region', $region)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'id' => $plan->id,
            'name' => $plan->name,
            'monthly_price' => $plan->monthly_price,
            'currency' => $plan->currency ?? $userCurrency,
            'region' => $plan->region,
            'max_tokens' => $plan->max_tokens,
            'daily_message_limit' => $plan->daily_message_limit,
            'ads_enabled' => $plan->ads_enabled,
            'is_default' => $plan->is_default,
            'description' => $plan->description,
            'image_url' => $plan->image_url,
            'tag' => $plan->tag,
            'trial_days' => $plan->trial_days ?? 0,
            'billing_cycle' => $plan->billing_cycle ?? 'monthly',
            'engine_id' => $plan->engine_id,
            'badge' => $plan->monthly_price == 0
                ? 'Free'
                : ($plan->monthly_price < 20 ? 'Recommended' : 'Best Value'),
            'engine' => [
                'name' => optional($plan->aiEngine)->name,
                'provider' => optional($plan->aiEngine)->provider,
                'max_tokens' => optional($plan->aiEngine)->max_tokens,
                'price_per_1k' => optional($plan->aiEngine)->price_per_1k,
                'is_vision_support' => optional($plan->aiEngine)->is_vision_support,
            ],
        ]);
    }

    /**
     * Compare multiple plans
     */
    public function compare(Request $request)
    {
        $request->validate([
            'plan_ids' => 'required|array|min:2',
            'plan_ids.*' => 'required|exists:plans,id',
        ]);

        $region = $this->getRegion($request);
        $user = $request->user();
        $userCurrency = optional($user?->country)->currency ?? 'USD';

        $plans = Plan::with('aiEngine')
            ->whereIn('id', $request->plan_ids)
            ->where('region', $region)
            ->where('is_active', true)
            ->get();

        if ($plans->count() < 2) {
            return response()->json(['error' => 'At least 2 plans required for comparison'], 422);
        }

        $comparison = $plans->map(function ($plan) use ($userCurrency) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'monthly_price' => $plan->monthly_price,
                'currency' => $plan->currency ?? $userCurrency,
                'billing_cycle' => $plan->billing_cycle ?? 'monthly',
                'max_tokens' => $plan->max_tokens,
                'daily_message_limit' => $plan->daily_message_limit,
                'ads_enabled' => $plan->ads_enabled,
                'description' => $plan->description,
                'trial_days' => $plan->trial_days ?? 0,
                'engine' => [
                    'name' => optional($plan->aiEngine)->name,
                    'provider' => optional($plan->aiEngine)->provider,
                    'is_vision_support' => optional($plan->aiEngine)->is_vision_support,
                ],
            ];
        });

        return response()->json([
            'plans' => $comparison,
        ]);
    }
}