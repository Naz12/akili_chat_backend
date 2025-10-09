<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPersona extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'data_type',
        'source',
        'confidence',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}