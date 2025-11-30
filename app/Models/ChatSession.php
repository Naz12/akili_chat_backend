<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatSession extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string'; // UUID

    protected $fillable = [
        'id',
        'user_id',
        'guest_session_id',
        'is_guest',
        'title',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guestSession()
    {
        return $this->belongsTo(GuestSession::class, 'guest_session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id');
    }
    public function docs()
    {
        return $this->hasMany(ChatSessionDoc::class, 'chat_session_id');
    }

    /**
     * Shares where this session is the original
     */
    public function sharesAsOriginal()
    {
        return $this->hasMany(ChatSessionShare::class, 'original_chat_session_id');
    }

    /**
     * Shares where this session is the duplicated copy
     */
    public function sharesAsDuplicated()
    {
        return $this->hasMany(ChatSessionShare::class, 'duplicated_chat_session_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}

    