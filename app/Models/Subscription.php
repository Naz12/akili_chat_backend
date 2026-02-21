<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'start_date',
        'end_date',
        'tokens_used',
        'is_active',     // NEW: to easily query active subscriptions
        'auto_renew',    // Optional: for future automatic renewal logic
        'metadata',      // Optional: for storing JSON details (e.g. transaction ID, source)
        'grace_period_ends_at',
        'payment_failure_count',
        'tx_ref',        // Payment reference (required by schema; use placeholder for manual/admin grants)
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
        'is_active'  => 'boolean',
        'auto_renew' => 'boolean',
        'metadata'   => 'array',
        'grace_period_ends_at' => 'datetime',
        'payment_failure_count' => 'integer',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    // Scope: Active subscription
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->whereDate('start_date', '<=', now())
                     ->whereDate('end_date', '>=', now());
    }

    // Helper: Check if subscription is currently valid
    public function isValid()
    {
        return $this->is_active && now()->between($this->start_date, $this->end_date);
    }

    /**
     * Check if subscription is in grace period
     */
    public function isInGracePeriod(): bool
    {
        return $this->grace_period_ends_at && now()->lte($this->grace_period_ends_at);
    }

    /**
     * Check if grace period has expired
     */
    public function hasGracePeriodExpired(): bool
    {
        return $this->grace_period_ends_at && now()->gt($this->grace_period_ends_at);
    }
}