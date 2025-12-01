<?php 

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use App\Models\User;
use App\Models\APILog;
use App\Models\AIEngine;
use App\Models\TokenUsage;
use App\Models\Subscription;
use App\Models\Bill;
use App\Models\Visitor;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardAdminController extends Controller
{
    public function index()
    {
        return view('admin.dashboard.index', [
            'engineCount' => AIEngine::count(),
            'userCount' => User::count(),
            'tokensToday' => TokenUsage::whereDate('created_at', today())->sum('tokens_used'),

            // Count subscriptions that are currently active
            'activePlans' => Subscription::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->count(),

            // Bill statistics
            'pendingBills' => Bill::where('status', 'pending')->count(),
            'overdueBills' => Bill::where('status', 'pending')
                ->where('due_date', '<', now())
                ->count(),
            'paidBills' => Bill::where('status', 'paid')->count(),
            'totalBillRevenue' => Bill::where('status', 'paid')->sum('amount'),

            // Grace period statistics
            'gracePeriodCount' => Subscription::whereNotNull('grace_period_ends_at')
                ->where('grace_period_ends_at', '>=', now())
                ->where('is_active', true)
                ->count(),

            // Payment failure statistics
            'paymentFailures' => Subscription::where('payment_failure_count', '>', 0)
                ->where('is_active', true)
                ->count(),

            // Expiring subscriptions (next 7 days)
            'expiringSubscriptions' => Subscription::where('is_active', true)
                ->where('auto_renew', true)
                ->whereBetween('end_date', [now(), now()->addDays(7)])
                ->count(),

            // Visitor statistics
            'todayVisitors' => Visitor::whereDate('first_visit_at', today())->count(),
            'totalVisitors' => Visitor::count(),
            'loggedInVisitors' => Visitor::where('is_logged_in', true)->count(),

            'engineStatuses' => AIEngine::all()->map(fn($e) => [
                'name' => $e->name,
                'online' => $e->is_active && method_exists($e, 'ping') ? $e->ping() : $e->is_active,
            ]),

            'recentLogs' => APILog::latest()->limit(6)->get(),
        ]);
    }
    
}