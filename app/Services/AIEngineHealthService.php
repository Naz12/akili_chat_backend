<?php

namespace App\Services;

use App\Models\AIEngine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AIEngineHealthService
{
    /**
     * Check health of all active AI engines
     */
    public function checkAllEngines(): array
    {
        $results = [];
        
        $engines = AIEngine::where('is_active', true)->get();
        
        foreach ($engines as $engine) {
            $health = $this->checkEngineHealth($engine);
            $results[$engine->id] = $health;
            
            // Cache health status (configurable via system settings)
            $cacheHours = \App\Models\SystemSetting::getValue('cache.ai_engine_health_hours', 1);
            Cache::put("engine_health_{$engine->id}", $health, $cacheHours * 3600);
        }
        
        return $results;
    }

    /**
     * Check health of a single engine
     */
    public function checkEngineHealth(AIEngine $engine): array
    {
        $isHealthy = false;
        $responseTime = null;
        $error = null;
        
        try {
            $startTime = microtime(true);
            
            // For OpenAI-compatible APIs, try a simple test request
            if (stripos($engine->provider, 'OpenAI') !== false || stripos($engine->provider, 'AI Manager') !== false) {
                // For AI Manager, skip health endpoint check and go straight to test request
                // AI Manager uses a custom API format, so we need to test with actual request
                try {
                    $apiKey = \Illuminate\Support\Facades\Crypt::decryptString($engine->api_key);
                    
                    // Build the test payload based on provider
                    if (stripos($engine->provider, 'AI Manager') !== false) {
                        // AI Manager format
                        $testPayload = [
                            'model' => $engine->version,
                            'messages' => [['role' => 'user', 'content' => 'hi']],
                            'max_tokens' => 10,
                        ];
                        
                        // AI Manager uses X-API-KEY header, not Authorization Bearer
                        $headers = [
                            'X-API-KEY' => $apiKey,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ];
                    } else {
                        // OpenAI format
                        $testPayload = [
                            'model' => $engine->version,
                            'messages' => [['role' => 'user', 'content' => 'test']],
                            'max_tokens' => 5,
                        ];
                        
                        // OpenAI uses Authorization Bearer header
                        $headers = [
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                        ];
                    }
                    
                    $testResponse = Http::timeout(10) // Increased timeout for reliability
                        ->withHeaders($headers)
                        ->post($engine->api_url, $testPayload);
                    
                    $isHealthy = $testResponse->successful();
                    if (!$isHealthy) {
                        $error = $testResponse->body();
                        Log::warning('Engine health check test request failed', [
                            'engine_id' => $engine->id,
                            'engine_name' => $engine->name,
                            'status' => $testResponse->status(),
                            'error' => substr($error, 0, 200), // Limit error length
                        ]);
                    }
                } catch (\Exception $e) {
                    // If decryption fails or other exception, mark as unhealthy
                    $isHealthy = false;
                    $error = $e->getMessage();
                    Log::error('Engine health check exception', [
                        'engine_id' => $engine->id,
                        'engine_name' => $engine->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // For other providers, just check if URL is reachable
                try {
                    $response = Http::timeout(5)->get($engine->api_url);
                    $isHealthy = $response->status() < 500;
                } catch (\Exception $e) {
                    $isHealthy = false;
                    $error = $e->getMessage();
                }
            }
            
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
        } catch (\Exception $e) {
            $isHealthy = false;
            $error = $e->getMessage();
            Log::error('Engine health check failed', [
                'engine_id' => $engine->id,
                'engine_name' => $engine->name,
                'error' => $e->getMessage(),
            ]);
        }
        
        // Update engine status in cache
        $status = $isHealthy ? 'healthy' : 'unhealthy';
        Cache::put("engine_status_{$engine->id}", $status, 3600);
        
        return [
            'engine_id' => $engine->id,
            'engine_name' => $engine->name,
            'is_healthy' => $isHealthy,
            'response_time_ms' => $responseTime,
            'error' => $error,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get cached health status for an engine
     */
    public function getEngineHealthStatus(int $engineId): ?string
    {
        return Cache::get("engine_status_{$engineId}");
    }

    /**
     * Check if engine is healthy (from cache or fresh check)
     */
    public function isEngineHealthy(AIEngine $engine, bool $freshCheck = false): bool
    {
        if (!$freshCheck) {
            $cachedStatus = Cache::get("engine_status_{$engine->id}");
            if ($cachedStatus !== null) {
                return $cachedStatus === 'healthy';
            }
        }
        
        $health = $this->checkEngineHealth($engine);
        return $health['is_healthy'];
    }
}

