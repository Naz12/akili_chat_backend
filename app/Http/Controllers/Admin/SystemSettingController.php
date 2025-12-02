<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    /**
     * Display system settings grouped by category
     */
    public function index()
    {
        $categories = [
            'payment' => 'Payment & Billing',
            'subscription' => 'Subscription Management',
            'usage' => 'Usage & Quotas',
            'guest' => 'Guest Users',
            'cache' => 'Cache Settings',
            'dashboard' => 'Dashboard & Display',
            'audit' => 'Audit Logs',
        ];

        $settingsByCategory = [];
        foreach ($categories as $category => $label) {
            $settingsByCategory[$category] = [
                'label' => $label,
                'settings' => SystemSetting::getByCategory($category),
            ];
        }

        return view('admin.system_settings.index', compact('settingsByCategory', 'categories'));
    }

    /**
     * Update system settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable',
        ]);

        $updated = 0;
        $errors = [];

        foreach ($request->settings as $key => $value) {
            $setting = SystemSetting::where('key', $key)->first();

            if (!$setting) {
                $errors[] = "Setting '{$key}' not found";
                continue;
            }

            // Validate value based on type
            if ($setting->type === 'integer' || $setting->type === 'float') {
                $value = $setting->type === 'integer' ? (int) $value : (float) $value;
                
                if ($setting->min_value !== null && $value < $setting->min_value) {
                    $errors[] = "{$setting->label} must be at least {$setting->min_value}";
                    continue;
                }
                
                if ($setting->max_value !== null && $value > $setting->max_value) {
                    $errors[] = "{$setting->label} must be at most {$setting->max_value}";
                    continue;
                }
            } elseif ($setting->type === 'boolean') {
                $value = $request->has("settings.{$key}") ? '1' : '0';
            }

            $setting->value = $value;
            $setting->save();

            // Clear cache for this setting
            SystemSetting::clearCache();

            $updated++;
        }

        if (!empty($errors)) {
            return back()->withErrors(['settings' => $errors])
                ->with('warning', "Updated {$updated} setting(s), but some had errors.");
        }

        return back()->with('success', "✅ Successfully updated {$updated} setting(s).");
    }

    /**
     * Reset a setting to its default value
     */
    public function reset(string $key)
    {
        $setting = SystemSetting::where('key', $key)->first();

        if (!$setting) {
            return back()->with('error', 'Setting not found.');
        }

        $setting->value = $setting->default_value;
        $setting->save();

        SystemSetting::clearCache();

        return back()->with('success', "✅ {$setting->label} reset to default value.");
    }
}
