<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_session_id',
        'chat_session_id', // ✅ replace chat_id
        'role',
        'content',
        'is_attachment',
    ];

    /**
     * Belongs to session
     */
    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    /**
     * Belongs to user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}