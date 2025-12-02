<?php

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use App\Models\AIEngine;
use App\Services\ExportService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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

    /**
     * Export plans to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'all'); // Plans are usually small, so default to all

            $query = Plan::with('aiEngine')->withCount('subscriptions');

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $plans = $query->paginate(20);
                $plans = $plans->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $plans = $query->limit($limit)->get();
            } else {
                $plans = $query->get();
            }

            $exportData = Collection::make($plans)->map(function ($plan) {
                return [
                    'ID' => $plan->id,
                    'Name' => $plan->name,
                    'Price' => number_format($plan->monthly_price, 2),
                    'Currency' => $plan->currency ?? 'ETB',
                    'Max Tokens' => number_format($plan->max_tokens),
                    'Daily Message Limit' => $plan->daily_message_limit,
                    'Engine' => $plan->aiEngine->name ?? 'N/A',
                    'Provider' => $plan->aiEngine->provider ?? 'N/A',
                    'Region' => ucfirst($plan->region),
                    'Ads Enabled' => $plan->ads_enabled ? 'Yes' : 'No',
                    'Is Default' => $plan->is_default ? 'Yes' : 'No',
                    'Is Active' => $plan->is_active ? 'Yes' : 'No',
                    'Subscribers' => $plan->subscriptions_count ?? 0,
                ];
            });

            $headers = ['ID', 'Name', 'Price', 'Currency', 'Max Tokens', 'Daily Message Limit', 'Engine', 'Provider', 'Region', 'Ads Enabled', 'Is Default', 'Is Active', 'Subscribers'];
            $filename = 'plans_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Plans Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Plans export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}
