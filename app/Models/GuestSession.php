<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestSession extends Model
{
    protected $fillable = [
        'device_fingerprint',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get chat sessions for this guest session
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class, 'guest_session_id');
    }

    /**
     * Check if guest session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
