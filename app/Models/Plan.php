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
        'region',
        'currency',
        'image_url',
        'description',
        'tag',
    ];

    protected $casts = [
        'ads_enabled' => 'boolean',
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