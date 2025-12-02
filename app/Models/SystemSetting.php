<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'category',
        'label',
        'description',
        'type',
        'value',
        'default_value',
        'unit',
        'min_value',
        'max_value',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'min_value' => 'integer',
        'max_value' => 'integer',
    ];

    /**
     * Get a setting value by key with caching
     */
    public static function getValue(string $key, $default = null)
    {
        return Cache::remember("system_setting:{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)
                ->where('is_active', true)
                ->first();

            if (!$setting) {
                return $default;
            }

            return $setting->getTypedValue();
        });
    }

    /**
     * Set a setting value by key
     */
    public static function setValue(string $key, $value): bool
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return false;
        }

        $setting->value = $value;
        $setting->save();

        // Clear cache
        Cache::forget("system_setting:{$key}");

        return true;
    }

    /**
     * Get the typed value based on the setting type
     */
    public function getTypedValue()
    {
        $value = $this->value ?? $this->default_value;

        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => (string) $value,
        };
    }

    /**
     * Get settings by category
     */
    public static function getByCategory(string $category)
    {
        return static::where('category', $category)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        $keys = static::pluck('key');
        foreach ($keys as $key) {
            Cache::forget("system_setting:{$key}");
        }
    }
}
