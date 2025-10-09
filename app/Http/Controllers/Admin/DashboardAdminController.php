<?php 

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use App\Models\User;
use App\Models\APILog;
use App\Models\AIEngine;
use App\Models\TokenUsage;
use App\Models\Subscription;
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
            'activePlans' => Subscription::where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->count(),

            'engineStatuses' => AIEngine::all()->map(fn($e) => [
                'name' => $e->name,
                'online' => $e->is_active && method_exists($e, 'ping') ? $e->ping() : $e->is_active,
            ]),

            'recentLogs' => APILog::latest()->limit(6)->get(),
        ]);
    }
    
}