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

        $plans = Plan::with('aiEngine')
            ->where('region', $region)
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get();

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
}