<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VisitorAdminController extends Controller
{
    /**
     * Display a listing of visitors with analytics
     */
    public function index(Request $request)
    {
        $query = Visitor::with('user')->latest('last_visit_at');

        // Filters
        if ($request->has('country') && $request->country) {
            $query->where('country', $request->country);
        }

        if ($request->has('is_logged_in')) {
            $query->where('is_logged_in', $request->is_logged_in === '1');
        }

        if ($request->has('device_type') && $request->device_type) {
            $query->where('device_type', $request->device_type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('first_visit_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('first_visit_at', '<=', $request->date_to);
        }

        $visitors = $query->paginate(50);

        // Statistics
        $stats = [
            'total_visitors' => Visitor::count(),
            'unique_visitors' => Visitor::select('ip_address', 'user_id')
                ->where(function($q) {
                    $q->whereNotNull('user_id')
                      ->orWhereNotNull('ip_address');
                })
                ->distinct()
                ->count(),
            'logged_in_count' => Visitor::where('is_logged_in', true)->count(),
            'guest_count' => Visitor::where('is_logged_in', false)->count(),
            'today_visitors' => Visitor::whereDate('first_visit_at', today())->count(),
            'this_week_visitors' => Visitor::where('first_visit_at', '>=', now()->startOfWeek())->count(),
            'this_month_visitors' => Visitor::where('first_visit_at', '>=', now()->startOfMonth())->count(),
        ];

        // Country statistics
        $countryStats = Visitor::select('country', 'country_name', DB::raw('count(*) as count'))
            ->whereNotNull('country')
            ->groupBy('country', 'country_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Device statistics
        $deviceStats = Visitor::select('device_type', DB::raw('count(*) as count'))
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get();

        // Browser statistics
        $browserStats = Visitor::select('browser', DB::raw('count(*) as count'))
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // OS statistics
        $osStats = Visitor::select('os', DB::raw('count(*) as count'))
            ->whereNotNull('os')
            ->groupBy('os')
            ->orderByDesc('count')
            ->get();

        // Recent activity (configurable via system settings)
        $recentActivityHours = \App\Models\SystemSetting::getValue('dashboard.recent_activity_hours', 24);
        $recentActivity = Visitor::where('last_activity_at', '>=', now()->subHours($recentActivityHours))
            ->orderByDesc('last_activity_at')
            ->limit(20)
            ->get();

        return view('admin.visitors.index', compact(
            'visitors',
            'stats',
            'countryStats',
            'deviceStats',
            'browserStats',
            'osStats',
            'recentActivity'
        ));
    }

    /**
     * Show detailed visitor information
     */
    public function show(Visitor $visitor)
    {
        $visitor->load('user');

        // Get visitor's page view history (if you have a page_views table)
        // For now, we'll just show the visitor details

        return view('admin.visitors.show', compact('visitor'));
    }

    /**
     * Get visitor analytics data (for charts)
     */
    public function analytics(Request $request)
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days);

        // Visitors over time
        $visitorsOverTime = Visitor::select(
            DB::raw('DATE(first_visit_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('first_visit_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Logged in vs Guest
        $loggedInVsGuest = [
            'logged_in' => Visitor::where('is_logged_in', true)
                ->where('first_visit_at', '>=', $startDate)
                ->count(),
            'guests' => Visitor::where('is_logged_in', false)
                ->where('first_visit_at', '>=', $startDate)
                ->count(),
        ];

        // Top countries
        $topCountries = Visitor::select(
            'country',
            'country_name',
            DB::raw('COUNT(*) as count')
        )
            ->where('first_visit_at', '>=', $startDate)
            ->whereNotNull('country')
            ->groupBy('country', 'country_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'visitors_over_time' => $visitorsOverTime,
            'logged_in_vs_guest' => $loggedInVsGuest,
            'top_countries' => $topCountries,
        ]);
    }

    /**
     * Export visitors to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'current');

            $query = Visitor::with('user')->latest('last_visit_at');

            // Apply filters
            if ($range === 'filtered' || $range === 'current') {
                if ($request->has('country') && $request->country) {
                    $query->where('country', $request->country);
                }
                if ($request->has('is_logged_in')) {
                    $query->where('is_logged_in', $request->is_logged_in === '1');
                }
                if ($request->has('device_type') && $request->device_type) {
                    $query->where('device_type', $request->device_type);
                }
            }

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('first_visit_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('first_visit_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $visitors = $query->paginate(50);
                $data = $visitors->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->limit($limit)->get();
            } else {
                $data = $query->get();
            }

            $exportData = Collection::make($data)->map(function ($visitor) {
                return [
                    'ID' => $visitor->id,
                    'User Name' => $visitor->is_logged_in && $visitor->user ? $visitor->user->name : 'Guest',
                    'User Email' => $visitor->is_logged_in && $visitor->user ? $visitor->user->email : '-',
                    'IP Address' => $visitor->ip_address ?? '-',
                    'Country' => $visitor->country ?? '-',
                    'Device Type' => ucfirst($visitor->device_type ?? '-'),
                    'Browser' => $visitor->browser ?? '-',
                    'OS' => $visitor->os ?? '-',
                    'Page Views' => $visitor->page_views ?? 0,
                    'Last Visit' => $visitor->last_visit_at ? $visitor->last_visit_at->format('Y-m-d H:i:s') : '-',
                ];
            });

            $headers = ['ID', 'User Name', 'User Email', 'IP Address', 'Country', 'Device Type', 'Browser', 'OS', 'Page Views', 'Last Visit'];
            $filename = 'visitors_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Visitors Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Visitors export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}
