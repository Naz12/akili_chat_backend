<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersistentSummary extends Model
{
    protected $fillable = [
        'user_id',
        'topic',
        'summary_type',
        'summary_text',
        'source',
        'tokens_used',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}