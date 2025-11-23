<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonaHistory extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'previous_value',
        'new_value',
        'changed_by',
        'change_reason',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}