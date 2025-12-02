<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.view_audit_logs') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to view audit logs.');
        }
        $query = AdminActivityLog::with('admin');

        // Filter by admin
        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        // Filter by model type
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->paginate(50);

        $admins = User::whereHas('roles')->orWhere('role', 'admin')->get();

        return view('admin.audit-logs.index', compact('logs', 'admins'));
    }

    public function show(AdminActivityLog $log)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.view_audit_logs') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to view audit logs.');
        }
        $log->load('admin');

        return view('admin.audit-logs.show', compact('log'));
    }

    public function forAdmin(User $admin, Request $request)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.view_audit_logs') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to view audit logs.');
        }
        $query = $admin->adminActivityLogs();

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->paginate(50);

        return view('admin.audit-logs.admin', compact('admin', 'logs'));
    }

    /**
     * Export audit logs to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'current');

            $query = AdminActivityLog::with('admin');

            // Apply filters
            if ($range === 'filtered' || $range === 'current') {
                if ($request->filled('admin_id')) {
                    $query->where('admin_id', $request->admin_id);
                }
                if ($request->filled('action')) {
                    $query->where('action', 'like', "%{$request->action}%");
                }
                if ($request->filled('model_type')) {
                    $query->where('model_type', $request->model_type);
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
                $logs = $query->latest()->paginate(50);
                $data = $logs->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->latest()->limit($limit)->get();
            } else {
                $data = $query->latest()->get();
            }

            $exportData = Collection::make($data)->map(function ($log) {
                return [
                    'ID' => $log->id,
                    'Admin Name' => $log->admin->name ?? 'N/A',
                    'Admin Email' => $log->admin->email ?? 'N/A',
                    'Action' => $log->action,
                    'Model Type' => $log->model_type ? class_basename($log->model_type) : '-',
                    'Model ID' => $log->model_id ?? '-',
                    'Description' => \Illuminate\Support\Str::limit($log->description ?? '-', 100),
                    'IP Address' => $log->ip_address ?? '-',
                    'Timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $headers = ['ID', 'Admin Name', 'Admin Email', 'Action', 'Model Type', 'Model ID', 'Description', 'IP Address', 'Timestamp'];
            $filename = 'audit_logs_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Audit Logs Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Audit logs export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}

