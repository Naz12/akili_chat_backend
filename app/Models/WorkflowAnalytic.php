<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkflowAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workflow_type',
        'intent',
        'duration',
        'tokens_used',
        'cost_estimate',
        'services_used',
        'cache_hit',
        'trace',
    ];

    protected $casts = [
        'duration' => 'decimal:2',
        'cost_estimate' => 'decimal:4',
        'services_used' => 'array',
        'trace' => 'array',
        'cache_hit' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
