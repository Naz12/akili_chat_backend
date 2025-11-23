<?php

namespace App\Http\Controllers\Admin;

use App\Models\Preference;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PreferenceController extends Controller
{
    /**
     * Display a listing of all user preferences.
     */
    public function index()
    {
        $preferences = Preference::with('user')->paginate(20);

        return view('admin.preferences.index', compact('preferences'));
    }

    /**
     * Update the specified preference settings.
     */
    public function update(Request $request, Preference $preference)
    {
        // Validate checkbox fields
        $request->validate([
            'allow_marketing_email' => 'nullable|boolean',
            'allow_push_notifications' => 'nullable|boolean',
            'allow_sms' => 'nullable|boolean',
        ]);

        // Save based on checkbox presence
        $preference->update([
            'allow_marketing_email' => $request->has('allow_marketing_email'),
            'allow_push_notifications' => $request->has('allow_push_notifications'),
            'allow_sms' => $request->has('allow_sms'),
        ]);

        return back()->with('success', '✅ Preferences for ' . $preference->user->name . ' updated successfully.');
    }
}