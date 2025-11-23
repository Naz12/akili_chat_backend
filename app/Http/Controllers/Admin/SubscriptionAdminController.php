<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class SubscriptionAdminController extends Controller
{
    public function index()
    {
        $subscriptions = Subscription::with('user', 'plan')->latest()->paginate(20);
        return view('admin.subscriptions.index', compact('subscriptions'));
    }

    public function create()
    {
        $users = User::all();
        $plans = Plan::where('is_active', true)->get();
        return view('admin.subscriptions.create', compact('users', 'plans'));
    }

        public function edit(Subscription $subscription)
    {
        $users = User::all();
        $plans = Plan::where('is_active', true)->get();

        return view('admin.subscriptions.edit', compact('subscription', 'users', 'plans'));
    }

    public function update(Request $request, Subscription $subscription)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $subscription->update([
            'user_id'    => $request->user_id,
            'plan_id'    => $request->plan_id,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
        ]);

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'Subscription updated successfully.');
    }


    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:plans,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $plan = Plan::findOrFail($request->plan_id);

        // End previous active subscriptions
        Subscription::where('user_id', $user->id)
            ->whereNull('end_date')
            ->update(['end_date' => now()]);

        // Create new subscription
        Subscription::create([
            'user_id'     => $user->id,
            'plan_id'     => $plan->id,
            'start_date'  => now(),
            'end_date'    => now()->addDays(30),
            'tokens_used' => 0,
        ]);

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'New subscription assigned successfully.');
    }

    public function destroy(Subscription $subscription)
    {
        $subscription->delete();

        return back()->with('success', 'Subscription deleted.');
    }
}