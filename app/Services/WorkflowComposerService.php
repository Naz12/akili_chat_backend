<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Composes and executes multi-service workflows
 */
class WorkflowComposerService
{
    private BrainOrchestrator $brain;
    private IntentClassifierService $classifier;
    
    public function __construct(BrainOrchestrator $brain, IntentClassifierService $classifier)
    {
        $this->brain = $brain;
        $this->classifier = $classifier;
    }
    
    /**
     * Detect and execute complex multi-service workflows
     * Returns workflow result or null if not a multi-service workflow
     * Note: Workflows are only available for authenticated users (not guests)
     */
    public function maybeCompose(string $prompt, ?string $attachmentUrl, ?User $user, ?string $sessionId): ?array
    {
        // Skip workflow composition for guest users
        if (!$user) {
            return null;
        }
        
        $startTime = microtime(true);
        
        // Detect if this requires multiple services
        $workflow = $this->detectWorkflow($prompt, $attachmentUrl, $sessionId);
        
        if (!$workflow) {
            return null;
        }
        
        Log::info('[Workflow] Composing multi-service workflow', ['type' => $workflow['type']]);
        
        $result = match($workflow['type']) {
            'youtube_doc_compare' => $this->executeYouTubeDocCompare($workflow, $user),
            'multi_doc_compare' => $this->executeMultiDocCompare($workflow, $user, $sessionId),
            'doc_diagram' => $this->executeDocDiagram($workflow, $user),
            default => null,
        };
        
        if ($result) {
            $result['workflow'] = [
                'type' => $workflow['type'],
                'steps' => count($result['trace'] ?? []),
                'total_time' => round(microtime(true) - $startTime, 2) . 's',
            ];
        }
        
        return $result;
    }
    
    /**
     * Detect if prompt requires multi-service workflow
     */
    private function detectWorkflow(string $prompt, ?string $attachmentUrl, ?string $sessionId): ?array
    {
        $lowerPrompt = strtolower($prompt);
        
        // Pattern: Compare YouTube + Document
        if (preg_match('/compar.*video|video.*compar|youtube.*(?:pdf|doc)|(?:pdf|doc).*youtube/i', $prompt)) {
            $youtubeUrl = $this->extractYouTubeUrl($prompt);
            if ($youtubeUrl && $attachmentUrl) {
                return [
                    'type' => 'youtube_doc_compare',
                    'youtube_url' => $youtubeUrl,
                    'doc_url' => $attachmentUrl,
                    'query' => $prompt,
                ];
            }
        }
        
        // Pattern: Compare multiple documents in session
        if (preg_match('/compar.*(?:document|pdf|file)|contrast.*(?:document|pdf)/i', $prompt) && $sessionId) {
            // Check if session has multiple docs
            $docCount = \App\Models\ChatSessionDoc::where('chat_session_id', $sessionId)->count();
            if ($docCount >= 2) {
                return [
                    'type' => 'multi_doc_compare',
                    'session_id' => $sessionId,
                    'query' => $prompt,
                ];
            }
        }
        
        // Pattern: Document → Diagram
        if (preg_match('/(?:draw|create|generate|make).*(?:diagram|flowchart|chart)|diagram.*(?:from|of)/i', $prompt)) {
            if ($attachmentUrl || $sessionId) {
                return [
                    'type' => 'doc_diagram',
                    'doc_url' => $attachmentUrl,
                    'session_id' => $sessionId,
                    'query' => $prompt,
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Execute YouTube + Document comparison workflow
     */
    private function executeYouTubeDocCompare(array $workflow, User $user): ?array
    {
        $trace = [];
        
        // Step 1: Transcribe & summarize YouTube
        $ytResult = $this->brain->processYouTube($workflow['youtube_url'], $user);
        if (!$ytResult) {
            return null;
        }
        $trace = array_merge($trace, $ytResult['trace'] ?? []);
        $videoContent = $ytResult['content'];
        
        // Step 2: Ingest & extract from document
        $docResult = $this->brain->processDocument($workflow['doc_url'], $user, "Extract key points and main topics from this document");
        if (!$docResult) {
            return null;
        }
        $trace = array_merge($trace, $docResult['trace'] ?? []);
        $docContent = $docResult['content'];
        
        // Step 3: Use LLM to compare
        $comparison = $this->compareContents($videoContent, $docContent, $workflow['query'], $user);
        $trace[] = ['service' => 'workflow', 'action' => 'compare', 'sources' => 2];
        
        return [
            'content' => $comparison,
            'trace' => $trace,
            'sources' => [
                'youtube' => $workflow['youtube_url'],
                'document' => $workflow['doc_url'],
            ],
        ];
    }
    
    /**
     * Execute multi-document comparison
     */
    private function executeMultiDocCompare(array $workflow, User $user, string $sessionId): ?array
    {
        $docIds = \App\Models\ChatSessionDoc::where('chat_session_id', $sessionId)
            ->pluck('doc_id')
            ->all();
        
        if (count($docIds) < 2) {
            return null;
        }
        
        // Query all docs via doc-service
        $base = rtrim((string) config('services.brain.doc_service_url'), '/');
        $secret = (string) config('services.brain.hmac_secret');
        
        $ts = (string) time();
        $cid = 'akili-backend';
        $kid = 'default';
        $path = '/v1/answer';
        $sig = hash_hmac('sha256', "POST|{$path}||{$ts}|{$cid}|{$kid}", $secret);
        
        $headers = [
            'X-Client-Id' => $cid,
            'X-Key-Id' => $kid,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
            'X-Tenant-Id' => (string) $user->id,
            'Content-Type' => 'application/json',
        ];
        
        $payload = [
            'query' => $workflow['query'] . " (Compare across all documents)",
            'doc_ids' => $docIds,
            'top_k' => 10,
            'max_tokens' => 1024,
        ];
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
                ->post($base . $path, ['payload' => $payload]);
            
            $answer = $response->json()['answer'] ?? null;
            
            if ($answer) {
                return [
                    'content' => $answer,
                    'trace' => [
                        ['service' => 'doc-service', 'action' => 'multi_doc_compare', 'doc_count' => count($docIds)],
                    ],
                    'sources' => ['doc_ids' => $docIds],
                ];
            }
        } catch (\Throwable $e) {
            Log::error('[Workflow] Multi-doc compare failed', ['error' => $e->getMessage()]);
        }
        
        return null;
    }
    
    /**
     * Execute document → diagram workflow
     */
    private function executeDocDiagram(array $workflow, User $user): ?array
    {
        // For now, extract text and suggest diagram (diagram service integration pending)
        Log::info('[Workflow] Doc→Diagram workflow - diagram service not yet integrated');
        
        return [
            'content' => "📊 Diagram generation workflow detected. This feature will be available once the diagram service is integrated. For now, I can help you describe the structure you'd like to visualize.",
            'trace' => [
                ['service' => 'workflow', 'action' => 'doc_diagram_pending', 'status' => 'not_implemented'],
            ],
        ];
    }
    
    /**
     * Compare two content pieces using LLM
     */
    private function compareContents(string $content1, string $content2, string $userQuery, User $user): string
    {
        $apiKey = env('OPENAI_KEY');
        $apiUrl = env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions');
        
        $systemPrompt = "You are an expert analyst comparing different content sources. Provide a detailed comparison highlighting similarities, differences, and unique insights from each source.";
        
        $userPrompt = <<<PROMPT
User query: {$userQuery}

Source 1 (Video):
{$content1}

Source 2 (Document):
{$content2}

Please compare these sources based on the user's query, highlighting:
1. Common themes and agreements
2. Contrasting viewpoints or differences
3. Unique insights from each source
4. Synthesis and overall conclusion
PROMPT;
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($apiUrl, [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);
            
            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'] ?? 'Comparison failed';
            }
        } catch (\Throwable $e) {
            Log::error('[Workflow] Comparison LLM failed', ['error' => $e->getMessage()]);
        }
        
        return "I encountered an issue comparing these sources. Please try again.";
    }
    
    private function extractYouTubeUrl(string $text): ?string
    {
        if (preg_match('/(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=[^\s&]+|youtu\.be\/[A-Za-z0-9_-]+))/i', $text, $m)) {
            return $m[1];
        }
        return null;
    }
}
