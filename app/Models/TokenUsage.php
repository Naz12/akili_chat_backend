<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenUsage extends Model
{
    protected $fillable = [
        'user_id',
        'chat_session_id',
        'subscription_id',
        'engine_id',
        'prompt',
        'response',
        'tokens_used',
        'cost',
        'source',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function engine()
    {
        return $this->belongsTo(AIEngine::class, 'engine_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}