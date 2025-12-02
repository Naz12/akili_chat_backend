<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;
use App\Models\Webhook;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WebhookLogController extends Controller
{
    public function index()
    {
        $logs = Webhook::latest()->paginate(25);
        return view('admin.webhooks.index', compact('logs'));
    }

    public function show($id)
    {
        $log = Webhook::findOrFail($id);
        return view('admin.webhooks.show', compact('log'));
    }

    /**
     * Export webhook logs to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'current');

            $query = Webhook::latest();

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $logs = $query->paginate(25);
                $data = $logs->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->limit($limit)->get();
            } else {
                $data = $query->get();
            }

            $exportData = Collection::make($data)->map(function ($log) {
                return [
                    'ID' => $log->id,
                    'Provider' => ucfirst($log->provider),
                    'Event Type' => $log->event_type,
                    'Status' => $log->status ?? 'N/A',
                    'Received At' => $log->created_at->format('Y-m-d H:i:s'),
                    'Processed' => $log->processed ? 'Yes' : 'No',
                ];
            });

            $headers = ['ID', 'Provider', 'Event Type', 'Status', 'Received At', 'Processed'];
            $filename = 'webhook_logs_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Webhook Logs Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Webhook logs export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}