<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\IntentClassifierService;
use App\Services\WorkflowOptimizerService;

class BrainOrchestrator
{
    /**
     * Decide if this request should be handled by orchestration ("brain").
     * Returns [content => string, trace => array] on success, or null if not applicable.
     * Note: Brain orchestration is only available for authenticated users (not guests)
     */
    public function maybeHandle(string $prompt, ?string $attachmentUrl, ?User $user): ?array
    {
        // Skip brain orchestration for guest users
        if (!$user) {
            return null;
        }
        
        $startTime = microtime(true);
        
        // Use LLM-powered intent classification for complex scenarios
        $classifier = app(IntentClassifierService::class);
        $intent = $classifier->classify($prompt, $attachmentUrl);
        
        Log::info('[Brain] Intent classified', $intent);
        
        // Route based on intent
        switch ($intent['intent']) {
            case 'youtube':
                $url = $intent['entities']['url'] ?? $this->extractYouTubeUrl($prompt);
                if ($url) {
                    $result = $this->processYouTube($url, $user);
                    if ($result) {
                        $result['intent'] = $intent;
                        $result['orchestration_time'] = round(microtime(true) - $startTime, 2) . 's';
                        
                        // Track for optimization
                        app(WorkflowOptimizerService::class)->trackExecution($user, 'youtube', $result);
                    }
                    return $result;
                }
                break;
                
            case 'document':
                if ($attachmentUrl && $this->isDocumentUrl($attachmentUrl)) {
                    $result = $this->processDocument($attachmentUrl, $user, $prompt);
                    if ($result) {
                        $result['intent'] = $intent;
                        $result['orchestration_time'] = round(microtime(true) - $startTime, 2) . 's';
                        
                        // Track for optimization
                        app(WorkflowOptimizerService::class)->trackExecution($user, 'document', $result);
                    }
                    return $result;
                }
                break;
                
            case 'diagram':
                // Future: route to diagram service
                Log::info('[Brain] Diagram intent detected - not yet implemented');
                break;
                
            case 'multi_doc':
                // Future: multi-document orchestration
                Log::info('[Brain] Multi-doc intent detected - not yet implemented');
                break;
                
            case 'general':
            default:
                // Let regular chat flow handle it
                return null;
        }
        
        return null;
    }

    private function extractYouTubeUrl(string $text): ?string
    {
        $pattern = '/(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=[^\s&]+|youtu\.be\/[A-Za-z0-9_-]+))/i';
        if (preg_match($pattern, $text, $m)) {
            return $m[1];
        }
        return null;
    }

    public function processYouTube(string $url, User $user, ?string $instructions = null): ?array
    {
        $startTime = microtime(true);
        
        // Check cache first
        $cacheKey = 'brain:youtube:' . md5($url);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            Log::info('[Brain] YouTube cache hit', ['url' => $url]);
            if ($instructions) {
                try {
                    $refined = app(\App\Services\AIChatSummaryService::class)->summarize($cached['content'], [
                        'user_id' => $user->id,
                        'instructions' => $instructions,
                    ]);
                    $cached['content'] = $refined;
                    $cached['trace'][] = ['service' => 'summary', 'action' => 'refine', 'engine' => 'AIChatSummaryService'];
                } catch (\Throwable $e) {}
            }
            $cached['trace'][] = ['service' => 'cache', 'action' => 'hit', 'saved_time' => '~20s'];
            return $cached;
        }
        
        $base = rtrim((string) config('services.brain.transcriber_url'), '/');
        if (!$base) {
            Log::warning('[Brain] TRANSCRIBER_URL not configured');
            return null;
        }

        // Use BrightData scrape pattern (same as zooysbackend YouTubeTranscriberService)
        $datasetId = (string) config('services.brain.brightdata_dataset_id');
        $clientKey = (string) config('services.brain.transcriber_client_key');
        if (!$datasetId || !$clientKey) {
            Log::warning('[Brain] BrightData dataset or transcriber client key not configured');
            return null;
        }

        $queryParams = [
            'dataset_id' => $datasetId,
            'format' => 'bundle',
            'headings' => 1,
            'max_paragraph_sentences' => 7,
            'include_meta' => 1,
        ];
        $fullUrl = $base . '/brightdata/scrape?' . http_build_query($queryParams);

        try {
            Log::info('[Brain] BrightData scrape request', ['url' => $url, 'full_url' => $fullUrl]);
            $payload = [
                'input' => [
                    ['url' => $url],
                ],
            ];
            $response = Http::timeout(600)
                ->connectTimeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Client-Key' => $clientKey,
                ])
                ->post($fullUrl, $payload);
        } catch (\Throwable $e) {
            Log::error('[Brain] BrightData scrape request failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('[Brain] BrightData scrape non-success', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $data = $response->json();
        $transcriptText = '';
        if (!empty($data['article_text'])) {
            $transcriptText = $data['article_text'];
        } elseif (!empty($data['subtitle_text'])) {
            $transcriptText = $data['subtitle_text'];
        } elseif (!empty($data['content'])) {
            $transcriptText = $data['content'];
        } elseif (!empty($data['text'])) {
            $transcriptText = $data['text'];
        }

        if (!is_string($transcriptText) || trim($transcriptText) === '') {
            Log::warning('[Brain] BrightData scrape returned empty content', [
                'keys' => is_array($data) ? array_keys($data) : [],
            ]);
            return null;
        }

        // 3) Summarize using existing summary service
        $summary = null;
        try {
            $summary = app(\App\Services\AIChatSummaryService::class)->summarize($transcriptText, [
                'user_id' => $user->id,
                'instructions' => $instructions ? $instructions : 'Summarize this YouTube content clearly with key points and structure.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Brain] Summary service failed, returning truncated transcript', ['error' => $e->getMessage()]);
            $summary = Str::limit($transcriptText, 4000);
        }

        $duration = round(microtime(true) - $startTime, 2);
        
        $result = [
            'content' => $summary,
            'trace' => [
                ['service' => 'transcriber', 'action' => 'brightdata_scrape_youtube', 'input' => $url, 'duration' => $duration . 's'],
                ['service' => 'summary', 'action' => 'summarize', 'engine' => 'AIChatSummaryService'],
            ],
        ];
        
        // Cache for 1 hour
        Cache::put($cacheKey, $result, 3600);
        
        return $result;
    }

    private function isDocumentUrl(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['pdf','doc','docx','rtf','ppt','pptx','xls','xlsx','txt']);
    }

    public function processDocument(string $attachmentUrl, User $user, string $query): ?array
    {
        $startTime = microtime(true);
        
        $base = rtrim((string) config('services.brain.doc_service_url'), '/');
        $secret = (string) config('services.brain.hmac_secret');
        if (!$base || !$secret) {
            Log::warning('[Brain] DOC_SERVICE_URL or HMAC secret not configured');
            return null;
        }

        // Check cache by URL hash (doc content might change, but URL typically stable for session)
        $docHash = md5($attachmentUrl);
        $cacheKey = 'brain:doc:' . $docHash;
        $cachedDocId = Cache::get($cacheKey);
        
        // Fetch bytes
        try {
            $fileRes = Http::timeout(30)->get($attachmentUrl);
            if ($fileRes->failed()) { return null; }
        } catch (\Throwable $e) {
            Log::warning('[Brain] Failed to fetch attachment', ['error' => $e->getMessage()]);
            return null;
        }
        $bytes = $fileRes->body();
        $contentHash = md5($bytes);
        $filename = basename(parse_url($attachmentUrl, PHP_URL_PATH) ?? 'document.pdf');
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
        ];
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';

        // HMAC headers for ingest
        $ts = (string) time();
        $cid = 'akili-backend';
        $kid = 'default';
        $path = '/v1/ingest';
        $sig = hash_hmac('sha256', "POST|$path||$ts|$cid|$kid", $secret);
        $headers = [
            'X-Client-Id' => $cid,
            'X-Key-Id' => $kid,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
            'X-Tenant-Id' => (string) $user->id,
        ];

        try {
            $ing = Http::withHeaders($headers)
                ->attach('file', $bytes, $filename, ['Content-Type' => $mime])
                ->asMultipart()
                ->post($base.$path, [
                    'ocr' => 'auto',
                    'lang' => 'eng',
                ]);
        } catch (\Throwable $e) {
            Log::error('[Brain] Ingest request failed', ['error' => $e->getMessage()]);
            return null;
        }
        if ($ing->failed()) { return null; }
        $docId = $ing->json()['doc_id'] ?? null;
        $jobId = $ing->json()['job_id'] ?? null;
        if (!$docId || !$jobId) { return null; }
        
        $ingestTime = microtime(true);

        // Poll job status until completed with retry backoff
        $tries = 0; $maxTries = 40; // ~32s
        $jobPath = '/v1/jobs/'.$jobId;
        $backoff = 500000; // Start with 0.5s
        while ($tries++ < $maxTries) {
            $ts = (string) time();
            $sig = hash_hmac('sha256', "GET|$jobPath||$ts|$cid|$kid", $secret);
            $h = [
                'X-Client-Id' => $cid,
                'X-Key-Id' => $kid,
                'X-Timestamp' => $ts,
                'X-Signature' => $sig,
                'X-Tenant-Id' => (string) $user->id,
            ];
            try {
                $jr = Http::withHeaders($h)->get($base.$jobPath);
            } catch (\Throwable $e) {
                break;
            }
            if ($jr->failed()) { 
                usleep($backoff);
                $backoff = min($backoff * 1.5, 3000000); // Max 3s
                continue;
            }
            $status = $jr->json()['status'] ?? null;
            if (in_array($status, ['completed','failed','aborted'])) { break; }
            usleep($backoff);
            $backoff = min($backoff * 1.2, 2000000); // Gradual increase, max 2s
        }

        // Ask for an answer based on this doc
        $ansPath = '/v1/answer';
        $ts = (string) time();
        $sig = hash_hmac('sha256', "POST|$ansPath||$ts|$cid|$kid", $secret);
        $h = [
            'X-Client-Id' => $cid,
            'X-Key-Id' => $kid,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
            'X-Tenant-Id' => (string) $user->id,
            'Content-Type' => 'application/json',
        ];
        $payload = [
            'query' => $query,
            'doc_ids' => [$docId],
            'top_k' => 4,
            'max_tokens' => 512,
        ];
        try {
            $ar = Http::withHeaders($h)->post($base.$ansPath, ['payload' => $payload]);
        } catch (\Throwable $e) {
            return null;
        }
        if ($ar->failed()) { return null; }
        $answer = $ar->json()['answer'] ?? null;
        if (!$answer) { return null; }
        
        $totalDuration = round(microtime(true) - $startTime, 2);
        $ingestDuration = round($ingestTime - $startTime, 2);
        
        // Cache doc_id for this content hash (24h)
        Cache::put('brain:doc_content:' . $contentHash, $docId, 86400);
        Cache::put($cacheKey, $docId, 86400);

        return [
            'content' => $answer,
            'trace' => [
                ['service' => 'doc-service', 'action' => 'ingest', 'doc_id' => $docId, 'job_id' => $jobId, 'duration' => $ingestDuration . 's'],
                ['service' => 'doc-service', 'action' => 'answer', 'doc_id' => $docId, 'duration' => ($totalDuration - $ingestDuration) . 's'],
                ['service' => 'brain', 'total_duration' => $totalDuration . 's'],
            ],
        ];
    }
}
