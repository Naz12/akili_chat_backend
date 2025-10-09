<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request)
    {
        $plan = $this->plan;
        $engine = $plan->aiEngine;

        return [
            'id' => $this->id,
            'start_date' => $this->start_date->toDateTimeString(),
            'end_date' => $this->end_date->toDateTimeString(),
            'tokens_used' => $this->tokens_used,
            'is_active' => $this->is_active,
            'tokens_available' => $plan->max_tokens - $this->tokens_used,
            'days_left' => now()->diffInDays($this->end_date, false),
            'is_expired' => now()->gt($this->end_date),
            'plan' => [
                'name' => $plan->name,
                'monthly_price' => $plan->monthly_price,
                'max_tokens' => $plan->max_tokens,
                'daily_message_limit' => $plan->daily_message_limit,
                'ads_enabled' => $plan->ads_enabled,
            ],
            'engine' => [
                'name' => optional($engine)->name,
                'provider' => optional($engine)->provider,
                'max_tokens' => optional($engine)->max_tokens,
                'price_per_1k' => optional($engine)->price_per_1k,
                'is_vision_support' => optional($engine)->is_vision_support,
            ],
        ];
    }
}