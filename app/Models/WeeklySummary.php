<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklySummary extends Model
{
    protected $fillable = [
        'user_id',
        'week_number',
        'week_start',
        'week_end',
        'year',
        'summary_text',
        'generated_by',
        'tokens_used',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}