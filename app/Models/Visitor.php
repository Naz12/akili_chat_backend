<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'country',
        'country_name',
        'region',
        'city',
        'latitude',
        'longitude',
        'timezone',
        'language',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'screen_resolution',
        'is_mobile',
        'is_tablet',
        'is_desktop',
        'is_bot',
        'is_logged_in',
        'page_views',
        'session_duration',
        'first_visit_at',
        'last_visit_at',
        'last_activity_at',
        'metadata',
    ];

    protected $casts = [
        'is_mobile' => 'boolean',
        'is_tablet' => 'boolean',
        'is_desktop' => 'boolean',
        'is_bot' => 'boolean',
        'is_logged_in' => 'boolean',
        'page_views' => 'integer',
        'session_duration' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'first_visit_at' => 'datetime',
        'last_visit_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user associated with this visitor (if logged in)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Filter by logged in status
     */
    public function scopeLoggedIn($query)
    {
        return $query->where('is_logged_in', true);
    }

    /**
     * Scope: Filter by guest (not logged in)
     */
    public function scopeGuests($query)
    {
        return $query->where('is_logged_in', false);
    }

    /**
     * Scope: Filter by country
     */
    public function scopeCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope: Filter by device type
     */
    public function scopeDeviceType($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('first_visit_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Recent visitors (last 24 hours)
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('last_visit_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Unique visitors (by IP or user_id)
     */
    public function scopeUnique($query)
    {
        return $query->selectRaw('
            CASE 
                WHEN user_id IS NOT NULL THEN user_id 
                ELSE ip_address 
            END as unique_identifier,
            COUNT(*) as visit_count
        ')->groupBy('unique_identifier');
    }

    /**
     * Get device icon class
     */
    public function getDeviceIconAttribute(): string
    {
        if ($this->is_mobile) return 'fas fa-mobile-alt';
        if ($this->is_tablet) return 'fas fa-tablet-alt';
        return 'fas fa-desktop';
    }

    /**
     * Get formatted session duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->session_duration < 60) {
            return $this->session_duration . 's';
        } elseif ($this->session_duration < 3600) {
            return round($this->session_duration / 60) . 'm';
        } else {
            return round($this->session_duration / 3600, 1) . 'h';
        }
    }
}
