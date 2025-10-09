<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppVersion;

class AppVersionApiController extends Controller
{
    public function check(Request $request)
    {
        $platform = $request->query('platform');
        $version = $request->query('version');

        if (!$platform || !$version) {
            return response()->json([
                'error' => 'Platform and version are required.'
            ], 422);
        }

        $record = AppVersion::where('platform', $platform)->first();

        if (!$record) {
            return response()->json([
                'update_required' => false,
                'force_update' => false,
                'latest_version' => null,
                'update_message' => null
            ]);
        }

        $updateRequired = version_compare($version, $record->latest_version, '<');

        return response()->json([
            'update_required' => $updateRequired,
            'force_update' => $record->force_update,
            'latest_version' => $record->latest_version,
            'update_message' => $record->update_message ?? ''
        ]);
    }
}