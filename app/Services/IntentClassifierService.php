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
        
        // When user attaches a document, only use "document" intent if they're NOT asking to convert/merge/split/extract.
        // Otherwise we'd never route to doc_converter (e.g. "convert this PDF to JPG" + attachment).
        if ($attachmentUrl && $this->isDocument($attachmentUrl)) {
            if ($this->hasDocConverterKeywords($prompt)) {
                // Let LLM classify so we get operation + target_format (e.g. convert + jpg)
                Log::info('[IntentClassifier] Document attachment with convert/merge/split/extract keywords, using LLM');
            } else {
                return ['intent' => 'document', 'confidence' => 1.0, 'entities' => ['url' => $attachmentUrl]];
            }
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

    /** Message suggests user wants convert, extract, merge, split, or PDF operations (doc-converter tool). */
    private function hasDocConverterKeywords(string $message): bool
    {
        $lower = strtolower($message);
        if (preg_match('/\b(convert|merge|split|extract|compress|watermark|page\s*numbers?|protect|unlock|preview|edit\s*pdf|reorder\s*pages?|reverse\s*pages?)\b/i', $lower)) {
            return true;
        }
        return preg_match('/\b(to|into)\s+(jpg|jpeg|png|pdf|docx?|md|text|word)\b/i', $lower) !== 0
            || str_contains($lower, 'convert this')
            || str_contains($lower, 'convert the')
            || str_contains($lower, 'turn this into')
            || str_contains($lower, 'as jpg')
            || str_contains($lower, 'as png')
            || str_contains($lower, 'to image')
            || str_contains($lower, 'add watermark')
            || str_contains($lower, 'protect with password')
            || str_contains($lower, 'make this pdf smaller');
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

1. "youtube" - User wants to transcribe, summarize, or work with Yoube video content
2. "document" - User wants to analyze, summarize, or chat about a document (PDF, DOC, etc.)
3. "presentation" - User wants to create or generate a presentation, slides, or PPT (PowerPoint). Any request to "make a presentation", "create slides", "generate a PPT", "outline for a talk" etc.
4. "diagram" - User wants to create or explain diagrams, flowcharts, charts, or visual representations. Includes: flowchart, mermaid, draw a diagram, visualize, pie chart, bar chart, "chart of X", "graph of Y". When intent is diagram, set entities.diagram_type to one of: flowchart, sequence, er, state, gantt, mindmap, pie, user_journey (or flowchart if unclear). For "pie chart of population", "chart of ethiopian population" use diagram_type "pie".
5. "doc_converter" - User wants to convert, merge, split, extract, or run PDF operations on documents. Set entities.operation to one of: convert, extract, merge, split, compress, watermark, page_numbers, protect, unlock, preview, edit_pdf. For "convert" set entities.target_format when clear (e.g. jpg, png, pdf, docx, md). For "convert to text" or "extract text" use operation "extract". For "compress this PDF" use operation "compress". For "add watermark" or "watermark with X" use operation "watermark" and entities.watermark_content if stated. For "add page numbers" use operation "page_numbers". For "protect with password" use operation "protect" and entities.password if stated. For "unlock" or "remove password" use operation "unlock" and entities.password if stated. For "preview" or "thumbnails" use operation "preview". For "reorder pages" or "reverse pages" use operation "edit_pdf" and entities.page_order if stated (e.g. "reverse", "1,3,2").
6. "multi_doc" - User wants to compare, contrast, or work with multiple documents
7. "general" - General conversation, questions, coding help, or anything that does not fit above

Respond ONLY with valid JSON in this exact format:
{
  "intent": "youtube|document|presentation|diagram|doc_converter|multi_doc|general",
  "confidence": 0.0-1.0,
  "reasoning": "brief explanation",
  "entities": {"key": "value"}
}

Examples:
Input: "Can you summarize this video for me? https://youtube.com/watch?v=abc"
Output: {"intent":"youtube","confidence":0.95,"reasoning":"YouTube URL detected","entities":{"url":"https://youtube.com/watch?v=abc"}}

Input: "Generate a presentation about climate change with 10 slides"
Output: {"intent":"presentation","confidence":0.95,"reasoning":"User wants to create a presentation","entities":{"topic":"climate change"}}

Input: "Draw a flowchart for user login process"
Output: {"intent":"diagram","confidence":0.9,"reasoning":"User wants a flowchart","entities":{"diagram_type":"flowchart"}}

Input: "Draw a sequence diagram for API auth"
Output: {"intent":"diagram","confidence":0.9,"reasoning":"User wants a sequence diagram","entities":{"diagram_type":"sequence"}}

Input: "Generate a pie chart of Ethiopian population" or "pie chart of population by region"
Output: {"intent":"diagram","confidence":0.9,"reasoning":"User wants a chart/diagram","entities":{"diagram_type":"pie"}}

Input: "Convert this PDF to text" or "Extract text from this document"
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to extract text","entities":{"operation":"extract"}}

Input: "Convert this PDF to JPG" or "Convert to jpg" (with attachment)
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to convert to image","entities":{"operation":"convert","target_format":"jpg"}}

Input: "Convert to PNG" or "I need this as png"
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to convert to PNG","entities":{"operation":"convert","target_format":"png"}}

Input: "Merge these files into one PDF"
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to merge documents","entities":{"operation":"merge"}}

Input: "Split this PDF at pages 3 and 7"
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to split document","entities":{"operation":"split","split_points":"3,7"}}

Input: "Compress this PDF" or "Make this PDF smaller"
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to compress PDF","entities":{"operation":"compress"}}

Input: "Add watermark CONFIDENTIAL to this PDF"
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to add watermark","entities":{"operation":"watermark","watermark_type":"text","watermark_content":"CONFIDENTIAL"}}

Input: "Add page numbers to this PDF"
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants page numbers","entities":{"operation":"page_numbers"}}

Input: "Protect this PDF with password secret123"
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to protect PDF","entities":{"operation":"protect","password":"secret123"}}

Input: "Unlock this PDF" (with attachment)
Output: {"intent":"doc_converter","confidence":0.9,"reasoning":"User wants to unlock PDF","entities":{"operation":"unlock"}}

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
                    $valid = ['youtube', 'document', 'presentation', 'diagram', 'doc_converter', 'multi_doc', 'general'];
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
