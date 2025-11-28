<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatSessionDoc extends Model
{
    use HasFactory;

    protected $table = 'chat_session_docs';

    protected $fillable = [
        'chat_session_id',
        'doc_id',
        'provider',
    ];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
