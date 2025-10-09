<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Models\TokenUsageLog;
use App\Models\TokenAdjustment;
use App\Http\Controllers\Controller;

class BillAdminController extends Controller
{
    /**
     * Display a paginated list of all subscriptions.
     * Optional filters (user_id, status) can be added for future refinement.
     */
    public function index(Request $request)
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->latest()
            ->paginate(20);

        return view('admin.billing.index', compact('subscriptions'));
    }

    /**
     * Show a specific user's billing and subscription history.
     */
    public function userHistory(User $user)
    {
        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->orderByDesc('start_date')
            ->paginate(10);
    
        $adjustments = TokenAdjustment::whereIn('subscription_id', $subscriptions->pluck('id'))
            ->with('admin', 'subscription.plan')
            ->latest()
            ->get();
    
        return view('admin.billing.user_history', compact('user', 'subscriptions', 'adjustments'));
    }

    /**
     * Show the form to manually adjust a subscription’s token usage.
     */
    public function editUsage(Subscription $subscription)
    {
        return view('admin.billing.adjust', compact('subscription'));
    }

    /**
     * Process token adjustment for a subscription.
     */
        public function updateUsage(Request $request, Subscription $subscription)
    {
        $request->validate([
            'tokens_used' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255'
        ]);

        // Only log if the tokens are actually being changed
        if ($subscription->tokens_used !== (int) $request->tokens_used) {
            TokenUsageLog::create([
                'subscription_id'  => $subscription->id,
                'admin_id'         => auth()->id(), // assumes admin is authenticated via guard
                'previous_tokens'  => $subscription->tokens_used,
                'new_tokens'       => (int) $request->tokens_used,
                'reason'           => $request->reason,
            ]);
        }

        $subscription->tokens_used = (int) $request->tokens_used;
        $subscription->save();

        return redirect()
            ->route('admin.billing.userHistory', $subscription->user_id)
            ->with('success', 'Subscription usage updated and logged successfully.');
    }
}