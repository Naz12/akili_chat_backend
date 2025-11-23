<?php

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use App\Models\AIEngine;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PlanAdminController extends Controller
{
    /*────────────────────────────── index / create ──────────────────────────────*/

    public function index()
    {
        $plans = Plan::with('aiEngine')
            ->withCount('subscriptions')
            ->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        $engines     = AIEngine::all();
        $currencies  = config('currencies');

        return view('admin.plans.create', compact('engines', 'currencies'));
    }

    /*──────────────────────────────── store ─────────────────────────────────────*/

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'monthly_price'       => 'required|numeric|min:0',
            'max_tokens'          => 'required|integer|min:1',
            'daily_message_limit' => 'required|integer|min:1',
            'engine_id'           => 'required|exists:ai_engines,id',
            'ads_enabled'         => 'required|boolean',
            'region'              => 'required|in:local,intl',
            'currency'            => 'required|string|max:10',
            'is_default'          => 'nullable|boolean',
            'image_url'           => 'nullable|url|max:255',
            'description'         => 'nullable|string|max:1000',
            'tag'                 => 'nullable|string|max:20',
        ]);

        if ($request->boolean('is_default')) {
            Plan::where('is_default', true)->update(['is_default' => false]);
        }

        Plan::create(array_merge(
            $validated,
            ['is_default' => $request->boolean('is_default')]
        ));

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    /*──────────────────────────────── edit ──────────────────────────────────────*/

    public function edit(Plan $plan)
    {
        $engines     = AIEngine::withCount('plans')->get();
        $currencies  = config('currencies');

        return view('admin.plans.edit', compact('plan', 'engines', 'currencies'));
    }

    /*──────────────────────────────── update ────────────────────────────────────*/

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'monthly_price'       => 'required|numeric|min:0',
            'max_tokens'          => 'required|integer|min:1',
            'daily_message_limit' => 'required|integer|min:1',
            'engine_id'           => 'required|exists:ai_engines,id',
            'ads_enabled'         => 'required|boolean',
            'region'              => 'required|in:local,intl',
            'currency'            => 'required|string|max:10',
            'is_default'          => 'nullable|boolean',
            'image_url'           => 'nullable|url|max:255',
            'description'         => 'nullable|string|max:1000',
            'tag'                 => 'nullable|string|max:20',

        ]);

        if ($request->boolean('is_default')) {
            Plan::where('is_default', true)
                ->where('id', '!=', $plan->id)
                ->update(['is_default' => false]);
        }

        $plan->update(array_merge(
            $validated,
            ['is_default' => $request->boolean('is_default')]
        ));

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    /*──────────────────────────────── destroy ───────────────────────────────────*/

    public function destroy(Plan $plan)
    {
        if ($plan->is_default) {
            return back()->with('error', 'Default plan cannot be deleted.');
        }

        $plan->delete();

        return back()->with('success', 'Plan deleted.');
    }
}
