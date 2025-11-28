<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatSessionShare extends Model
{
    protected $fillable = [
        'shared_by_user_id',
        'shared_to_user_id',
        'original_chat_session_id',
        'duplicated_chat_session_id',
        'status',
        'message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * User who shared the session
     */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    /**
     * User who received the share
     */
    public function sharedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_to_user_id');
    }

    /**
     * Original chat session that was shared
     */
    public function originalSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'original_chat_session_id');
    }

    /**
     * Duplicated chat session created for the recipient
     */
    public function duplicatedSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'duplicated_chat_session_id');
    }

    /**
     * Check if share is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if share is accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if share is declined
     */
    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }
}
