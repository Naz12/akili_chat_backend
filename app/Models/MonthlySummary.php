<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlySummary extends Model
{
    protected $fillable = [
        'user_id',
        'month',
        'year',
        'summary_text',
        'generated_by',
        'tokens_used',
    ];

    protected $casts = [
        'month' => 'date:Y-m',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}