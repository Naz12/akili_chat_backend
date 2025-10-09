<?php

namespace App\Jobs;

use App\Models\AIEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TestAIEngineConnection implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $engine;

    public function __construct(AIEngine $engine)
    {
        $this->engine = $engine;
    }

    public function handle()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->engine->api_key,
                'Content-Type' => 'application/json',
            ])->post($this->engine->api_url, [
                'model' => $this->engine->name,
                'messages' => [['role' => 'user', 'content' => 'Hello']],
            ]);

            $status = $response->status();
            if ($response->successful()) {
                Log::info("✅ Test success [{$this->engine->name}] - {$status}");
                $this->engine->update(['last_test_success' => now()]);
            } else {
                Log::warning("⚠️ Test failed [{$this->engine->name}] - {$status}");
            }
        } catch (\Throwable $e) {
            Log::error("❌ Test error [{$this->engine->name}]: {$e->getMessage()}");
        }
    }
}