<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Preference extends Model
{
    protected $fillable = [
        'user_id',
        'allow_marketing_email',
        'allow_push_notifications',
        'allow_sms',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}