<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'monthly_price',
        'max_tokens',
        'daily_message_limit',
        'engine_id', 
        'ads_enabled',
        'is_active',
        'is_default',
        'region',
        'currency',
        'image_url',
        'description',
        'tag',
        'trial_days',
        'billing_cycle',
    ];

    protected $casts = [
        'ads_enabled' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function aiEngine() // renamed from engine()
    {
        return $this->belongsTo(AIEngine::class, 'engine_id');
    }
    

}