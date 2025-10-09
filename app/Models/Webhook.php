<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'signature',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}