<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'summary_date',
        'summary_text',
        'generated_by',
        'tokens_used',
    ];

    protected $casts = [
        'summary_date' => 'date',
    ];

    /**
     * Get the user that owns the summary.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}