<?php

namespace App\Http\Controllers\Admin;

use App\Models\Preference;
use App\Services\ExportService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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

    /**
     * Export preferences to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'current');

            $query = Preference::with('user');

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $preferences = $query->paginate(20);
                $data = $preferences->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->limit($limit)->get();
            } else {
                $data = $query->get();
            }

            $exportData = Collection::make($data)->map(function ($pref) {
                return [
                    'ID' => $pref->id,
                    'User Name' => $pref->user->name ?? 'N/A',
                    'User Email' => $pref->user->email ?? 'N/A',
                    'Marketing Email' => $pref->allow_marketing_email ? 'Yes' : 'No',
                    'Push Notifications' => $pref->allow_push_notifications ? 'Yes' : 'No',
                    'SMS' => $pref->allow_sms ? 'Yes' : 'No',
                    'Updated' => $pref->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            $headers = ['ID', 'User Name', 'User Email', 'Marketing Email', 'Push Notifications', 'SMS', 'Updated'];
            $filename = 'preferences_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'User Preferences Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Preferences export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}