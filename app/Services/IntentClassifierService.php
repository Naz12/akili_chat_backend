<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class IntentClassifierService
{
    /**
     * Classify user intent using LLM
     * Returns: ['intent' => 'youtube'|'document'|'general', 'confidence' => float, 'entities' => array]
     */
    public function classify(string $prompt, ?string $attachmentUrl): array
    {
        Log::info('[IntentClassifier] Classifying prompt', ['prompt' => substr($prompt, 0, 100), 'hasAttachment' => !is_null($attachmentUrl)]);
        
        // Quick regex pre-filters (fast path)
        if ($this->hasYouTubeUrl($prompt)) {
            $url = $this->extractYouTubeUrl($prompt);
            Log::info('[IntentClassifier] YouTube URL detected (fast path)', ['url' => $url]);
            return ['intent' => 'youtube', 'confidence' => 1.0, 'entities' => ['url' => $url]];
        }
        
        if ($attachmentUrl && $this->isDocument($attachmentUrl)) {
            return ['intent' => 'document', 'confidence' => 1.0, 'entities' => ['url' => $attachmentUrl]];
        }
        
        // LLM classification for complex intents
        $cacheKey = 'intent:' . md5($prompt . ($attachmentUrl ?? ''));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $classification = $this->classifyWithLLM($prompt, $attachmentUrl);
        
        // Cache for 5 minutes
        Cache::put($cacheKey, $classification, 300);
        
        return $classification;
    }
    
    private function hasYouTubeUrl(string $text): bool
    {
        return (bool) preg_match('/(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)[^\s&]+)/i', $text);
    }
    
    private function extractYouTubeUrl(string $text): ?string
    {
        if (preg_match('/(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=[^\s&]+|youtu\.be\/[A-Za-z0-9_-]+))/i', $text, $m)) {
            return $m[1];
        }
        return null;
    }
    
    private function isDocument(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['pdf','doc','docx','rtf','ppt','pptx','xls','xlsx','txt']);
    }
    
    private function classifyWithLLM(string $prompt, ?string $attachmentUrl): array
    {
        $apiKey = config('services.openai.key') ?? env('OPENAI_KEY');
        $apiUrl = config('services.openai.url') ?? env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions');
        
        if (!$apiKey) {
            Log::warning('[IntentClassifier] OpenAI key not configured, falling back to general intent');
            return ['intent' => 'general', 'confidence' => 0.5, 'entities' => []];
        }
        
        $systemPrompt = <<<SYSTEM
You are an intent classifier for a smart AI assistant. Analyze the user's message and classify into ONE of these intents:

1. "youtube" - User wants to transcribe, summarize, or work with YouTube video content
2. "document" - User wants to analyze, summarize, or chat about a document (PDF, DOC, etc.)
3. "diagram" - User wants to create or explain diagrams, flowcharts, or visual representations
4. "multi_doc" - User wants to compare, contrast, or work with multiple documents
5. "general" - General conversation, questions, coding help, etc.

Respond ONLY with valid JSON in this exact format:
{
  "intent": "youtube|document|diagram|multi_doc|general",
  "confidence": 0.0-1.0,
  "reasoning": "brief explanation",
  "entities": {"key": "value"}
}

Examples:
Input: "Can you summarize this video for me? https://youtube.com/watch?v=abc"
Output: {"intent":"youtube","confidence":0.95,"reasoning":"YouTube URL detected","entities":{"url":"https://youtube.com/watch?v=abc"}}

Input: "Compare the methodology in these two research papers"
Output: {"intent":"multi_doc","confidence":0.85,"reasoning":"User wants to compare multiple documents","entities":{"doc_count":2}}

Input: "What's the weather like today?"
Output: {"intent":"general","confidence":0.9,"reasoning":"General question","entities":{}}
SYSTEM;
        
        $userMessage = "User message: " . $prompt;
        if ($attachmentUrl) {
            $userMessage .= "\nAttachment: " . $attachmentUrl;
        }
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($apiUrl, [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'temperature' => 0.3,
                'max_tokens' => 200,
            ]);
            
            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';
                $parsed = json_decode($content, true);
                
                if ($parsed && isset($parsed['intent'])) {
                    return [
                        'intent' => $parsed['intent'],
                        'confidence' => $parsed['confidence'] ?? 0.7,
                        'entities' => $parsed['entities'] ?? [],
                        'reasoning' => $parsed['reasoning'] ?? '',
                    ];
                }
            }
            
            Log::warning('[IntentClassifier] LLM classification failed', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error('[IntentClassifier] LLM request failed', ['error' => $e->getMessage()]);
        }
        
        // Fallback
        return ['intent' => 'general', 'confidence' => 0.5, 'entities' => []];
    }
}
