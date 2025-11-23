<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name', 'key', 'description', 'is_enabled', 'config'
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
    ];
}