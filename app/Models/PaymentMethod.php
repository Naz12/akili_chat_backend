<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name', 'key', 'description', 'is_enabled', 'regions'
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'regions' => 'array',
    ];

    /**
     * Check if payment method is enabled for a specific region
     */
    public function isEnabledForRegion(string $region): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        $regions = $this->regions ?? [];
        return in_array($region, $regions);
    }

    /**
     * Scope to get payment methods enabled for a specific region
     */
    public function scopeForRegion($query, string $region)
    {
        return $query->where('is_enabled', true)
            ->whereJsonContains('regions', $region);
    }
}