<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use App\Models\AIEngine;
use Illuminate\Support\Str;
use App\Models\InternalTokenUsage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AIChatSummaryService
{
    public function summarize(string $conversation, array $options = []): string
    {
        $engineModel = 'gpt-3.5-turbo';
        $engineKey   = config('services.openai.key');
        $engineUrl   = 'https://api.openai.com/v1/chat/completions';
        $instruction = $options['instructions'] ?? 'Summarize the conversation clearly.';
    
        // 🛡️ Input guard
        if (trim($conversation) === '') {
            Log::warning('⚠️ Empty conversation passed to summarize()', ['user_id' => $options['user_id'] ?? null]);
            return 'No summary generated (empty input).';
        }
    
        try {
            Log::debug('📤 [AI Summary] Sending to OpenAI', [
                'instruction' => Str::limit($instruction, 120),
                'conversation' => Str::limit($conversation, 250),
                'user_id' => $options['user_id'] ?? null,
                'type' => $options['type'] ?? null,
            ]);
    
            $response = Http::withToken($engineKey)
                ->post($engineUrl, [
                    'model'    => $engineModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $instruction],
                        ['role' => 'user', 'content' => $conversation],
                    ],
                    'max_tokens' => 250,
                ]);
    
            $result  = $response->json();
    
            Log::debug('📥 [AI Summary] Response received', [
                'status' => $response->status(),
                'usage' => $result['usage'] ?? null,
                'content' => $result['choices'][0]['message']['content'] ?? '[no content]',
            ]);
    
            $content = $result['choices'][0]['message']['content'] ?? null;
    
            if (!$content) {
                Log::warning('⚠️ [AI Summary] No content returned', ['response' => $result]);
                return 'No summary generated.';
            }
    
            // ✅ Save internal token usage
            if (isset($result['usage'])) {
                $userId = $options['user_id'] ?? null;
                $engine = AIEngine::where('provider', 'openai')->where('name', 'GPT-3.5 Turbo')->first();
                $tokenUsed = $result['usage']['total_tokens'] ?? 0;
                $cost = $this->calculateCost($tokenUsed, $engineModel);
    
                InternalTokenUsage::create([
                    'engine_id'    => optional($engine)->id,
                    'user_id'      => $userId,
                    'purpose'      => $options['type'] ?? 'unknown',
                    'input_text'   => $conversation,
                    'output_text'  => $content,
                    'tokens_used'  => $tokenUsed,
                    'cost'         => $cost,
                    'metadata'     => $options,
                ]);
            }
    
            return $content;
        } catch (\Throwable $e) {
            Log::error('❌ [AI Summary] Error during request', [
                'message' => $e->getMessage(),
                'user_id' => $options['user_id'] ?? null,
            ]);
    
            return 'Summary temporarily unavailable due to an error.';
        }
    }
    

    protected function calculateCost(int $tokens, string $model): float
    {
        $pricePer1k = config("ai.engines.gpt-3.5.price_per_1k", 0.0015);
        return round(($tokens / 1000) * $pricePer1k, 6);
    }
}