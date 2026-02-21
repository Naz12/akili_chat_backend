<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class IntentClassifierService
{
    /**
     * Classify user intent (brain) using fast-path rules and AI manager (deepseek-chat).
     * Returns: ['intent' => 'youtube'|'document'|'presentation'|'diagram'|'multi_doc'|'general', 'confidence' => float, 'entities' => array]
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
        $baseUrl = rtrim(config('services.ai_manager.url') ?? env('AI_MANAGER_URL'), '/');
        $key = config('services.ai_manager.key') ?? env('AI_MANAGER_KEY');
        $model = config('services.ai_manager.model') ?? env('AI_MANAGER_MODEL', 'deepseek-chat');

        if (! $baseUrl || ! $key) {
            Log::warning('[IntentClassifier] AI manager not configured, falling back to general intent');
            return ['intent' => 'general', 'confidence' => 0.5, 'entities' => []];
        }

        // Use same endpoint and auth as AIChatApiController::callAi so classification succeeds
        $url = $baseUrl . '/api/custom-prompt';

        $systemPrompt = <<<SYSTEM
You are an intent classifier for a smart AI assistant. Analyze the user's message and classify into ONE of these intents:

1. "youtube" - User wants to transcribe, summarize, or work with YouTube video content
2. "document" - User wants to analyze, summarize, or chat about a document (PDF, DOC, etc.)
3. "presentation" - User wants to create or generate a presentation, slides, or PPT (PowerPoint). Any request to "make a presentation", "create slides", "generate a PPT", "outline for a talk" etc.
4. "diagram" - User wants to create or explain diagrams, flowcharts, or visual representations (flowchart, mermaid, draw a diagram, visualize)
5. "multi_doc" - User wants to compare, contrast, or work with multiple documents
6. "general" - General conversation, questions, coding help, or anything that does not fit above

Respond ONLY with valid JSON in this exact format:
{
  "intent": "youtube|document|presentation|diagram|multi_doc|general",
  "confidence": 0.0-1.0,
  "reasoning": "brief explanation",
  "entities": {"key": "value"}
}

Examples:
Input: "Can you summarize this video for me? https://youtube.com/watch?v=abc"
Output: {"intent":"youtube","confidence":0.95,"reasoning":"YouTube URL detected","entities":{"url":"https://youtube.com/watch?v=abc"}}

Input: "Generate a presentation about climate change with 10 slides"
Output: {"intent":"presentation","confidence":0.95,"reasoning":"User wants to create a presentation with a topic and slide count","entities":{"topic":"climate change"}}

Input: "Draw a flowchart for user login process"
Output: {"intent":"diagram","confidence":0.9,"reasoning":"User wants to create a diagram/flowchart","entities":{}}

Input: "What's the weather like today?"
Output: {"intent":"general","confidence":0.9,"reasoning":"General question","entities":{}}
SYSTEM;

        $userMessage = "User message: " . $prompt;
        if ($attachmentUrl) {
            $userMessage .= "\nAttachment: " . $attachmentUrl;
        }
        $fullPrompt = $systemPrompt . "\n\n" . $userMessage;

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-KEY' => $key,
            ])->timeout(15)->post($url, [
                'prompt' => $fullPrompt,
                'model' => $model,
                'response_format' => 'text',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // AI manager may return { status, data: { reply } } or { reply }
                $payload = $data['data'] ?? $data;
                $content = $payload['reply'] ?? $payload['response'] ?? $payload['content'] ?? $data['reply'] ?? $data['response'] ?? $data['content'] ?? $data['text'] ?? '';
                $parsed = is_string($content) ? json_decode($content, true) : null;

                if ($parsed && isset($parsed['intent'])) {
                    $intent = strtolower(trim((string) $parsed['intent']));
                    $valid = ['youtube', 'document', 'presentation', 'diagram', 'multi_doc', 'general'];
                    if (! in_array($intent, $valid, true)) {
                        $intent = 'general';
                    }
                    return [
                        'intent' => $intent,
                        'confidence' => (float) ($parsed['confidence'] ?? 0.7),
                        'entities' => $parsed['entities'] ?? [],
                        'reasoning' => $parsed['reasoning'] ?? '',
                    ];
                }
            }

            Log::warning('[IntentClassifier] AI manager classification failed', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error('[IntentClassifier] AI manager request failed', ['error' => $e->getMessage()]);
        }

        return ['intent' => 'general', 'confidence' => 0.5, 'entities' => []];
    }
}
