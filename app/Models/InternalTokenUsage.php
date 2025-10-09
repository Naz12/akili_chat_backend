<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalTokenUsage extends Model
{
    protected $fillable = [
        'engine_id',
        'user_id',
        'purpose',
        'tokens_used',
        'cost',
        'input_text',
        'output_text',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function engine()
    {
        return $this->belongsTo(AIEngine::class, 'engine_id');
    }
}