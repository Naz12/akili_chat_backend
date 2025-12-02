<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenUsage;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TokenUsageAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = TokenUsage::with(['user', 'engine'])->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('engine_id')) {
            $query->where('engine_id', $request->engine_id);
        }

        $usages = $query->paginate(20);

        return view('admin.token_usages.index', compact('usages'));
    }

    /**
     * Export token usage to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'current');

            $query = TokenUsage::with(['user', 'engine'])->latest();

            // Apply filters
            if ($range === 'filtered' || $range === 'current') {
                if ($request->filled('user_id')) {
                    $query->where('user_id', $request->user_id);
                }
                if ($request->filled('engine_id')) {
                    $query->where('engine_id', $request->engine_id);
                }
            }

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $usages = $query->paginate(20);
                $data = $usages->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->limit($limit)->get();
            } else {
                $data = $query->get();
            }

            $exportData = Collection::make($data)->map(function ($usage) {
                return [
                    'ID' => $usage->id,
                    'User Name' => $usage->user->name ?? 'N/A',
                    'User Email' => $usage->user->email ?? 'N/A',
                    'Engine' => $usage->engine->name ?? 'Unknown',
                    'Provider' => $usage->engine->provider ?? 'N/A',
                    'Tokens Used' => number_format($usage->tokens_used ?? 0),
                    'Cost' => '$' . number_format($usage->cost ?? 0, 4),
                    'Prompt' => \Illuminate\Support\Str::limit($usage->prompt ?? '-', 50),
                    'Response' => \Illuminate\Support\Str::limit($usage->response ?? '-', 50),
                    'Date' => $usage->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $headers = ['ID', 'User Name', 'User Email', 'Engine', 'Provider', 'Tokens Used', 'Cost', 'Prompt', 'Response', 'Date'];
            $filename = 'token_usage_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Token Usage Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Token usage export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}