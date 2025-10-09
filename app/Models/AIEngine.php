<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Jobs\TestAIEngineConnection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIEngine extends Model
{
    protected $table = 'ai_engines';

    protected $fillable = [
        'name',
        'provider',
        'api_url',
        'api_key',
        'readonly_api_key',
        'max_tokens',
        'price_per_1k',
        'model_type',
        'version',
        'priority_order',
        'is_fallback',
        'is_active',
        'online',                // ✅ Must be fillable
        'last_test_success',     // ✅ Must be fillable
        'avg_cost_per_request',
        'failure_count',
        'is_vision_support',   // vision support

    ];

    // Relationships
    public function tokenUsages()
    {
        return $this->hasMany(TokenUsage::class, 'engine_id');
    }

    public function usages()
    {
        return $this->hasMany(TokenUsage::class, 'engine_id');
    }

    // Encrypt API key on set
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    // Decrypt API key on get
    public function getApiKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Automatically run test on create/update
    protected static function booted()
    {
        static::created(fn($engine) => TestAIEngineConnection::dispatch($engine));
        static::updated(fn($engine) => TestAIEngineConnection::dispatch($engine));
    }

    // Self-test logic
    public function autoTest()
    {
        try {
            $key = $this->api_key;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ])->post($this->api_url, [
                'model' => $this->name,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello']
                ],
            ]);

            if ($response->successful()) {
                Log::info("✅ AutoTest succeeded for {$this->name} (Status: {$response->status()})");
            } else {
                Log::warning("⚠️ AutoTest failed for {$this->name} - HTTP {$response->status()}: {$response->body()}");
            }
        } catch (\Throwable $e) {
            Log::error("❌ AutoTest error for {$this->name}: " . $e->getMessage());
        }
    }

    public function plans()
{
    return $this->hasMany(Plan::class, 'engine_id');
}

}