<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class APILog extends Model
{
    protected $table = 'api_logs'; // Or 'api_logs' if you prefer

    protected $fillable = [
        'method',
        'endpoint',
        'request',
        'response',
        'tokens_used',
    ];

    protected $casts = [
        'request'  => 'array',
        'response' => 'array',
    ];
}