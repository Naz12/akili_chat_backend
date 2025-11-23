<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}