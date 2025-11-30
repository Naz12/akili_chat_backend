<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Plan;
use App\Models\AIEngine;
use App\Models\TokenUsage;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ChatSessionDoc;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use App\Traits\DetectsRegion;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\UsageValidatorService;
use App\Services\BrainOrchestrator;
use App\Services\WorkflowComposerService;
use App\Services\ProactiveSuggestionService;
use App\Services\GuestUserService;
use App\Notifications\TokenQuotaWarningNotification;

class AIChatApiController extends Controller
{
    // Region Detects
    use DetectsRegion;

    /**
     * Handle chat prompt + optional attachment_url (image/pdf/docx)
     */
    public function handleChat(Request $request, UsageValidatorService $usageValidator)
    {
        try {
            // Try to authenticate user from JWT token (route is not protected by auth:api middleware)
            $user = null;
            $authHeader = $request->header('Authorization');
            
            if ($authHeader) {
                // Also check for token in other possible header formats (some proxies modify headers)
                if (stripos($authHeader, 'Bearer') !== 0) {
                    $authHeader = $request->header('X-Authorization') 
                        ?? $request->header('HTTP_AUTHORIZATION')
                        ?? $request->header('Authorization');
                }
                
                if ($authHeader && stripos($authHeader, 'Bearer') === 0) {
                    try {
                        $token = preg_replace('/^Bearer\s+/i', '', $authHeader);
                        if ($token && $token !== $authHeader && strlen($token) > 50) {
                            \Tymon\JWTAuth\Facades\JWTAuth::setToken($token);
                            $user = \Tymon\JWTAuth\Facades\JWTAuth::authenticate();
                        }
                    } catch (\Exception $e) {
                        // Token is invalid or expired, treat as guest
                        Log::debug('JWT token validation failed in handleChat', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            Log::info('=== Chat Request Started ===', [
                'user_id' => $user?->id,
                'user_region' => $user?->region,
                'message_length' => strlen($request->input('message', '')),
                'has_session_id' => !empty($request->input('session_id')),
            ]);
            
            $guestSession = null;
            $isGuest = false;
            
            // Support guest users - if no authenticated user, create/get guest session
            if (!$user) {
                $guestUserService = app(GuestUserService::class);
                $guestSession = $guestUserService->getOrCreateGuestSession($request);
                
                // Check if guest session is expired (24 hours)
                if ($guestSession->isExpired()) {
                    return response()->json([
                        'error' => 'Guest session expired. Please sign up to continue.',
                        'redirect' => '/register',
                    ], 401);
                }
                
                $isGuest = true;
                Log::info('Guest user detected', ['guest_session_id' => $guestSession->id]);
            }

            // ✅ Prevent execution if quota failed (for authenticated users only)
            // Guest users will use default free plan with 24-hour expiration
            if ($user) {
                $quotaResult = $usageValidator->checkQuota($user);   // full check
                if ($quotaResult['error']) {
                    Log::warning('Quota check failed', $quotaResult);
                    return response()->json([
                        'message'  => [
                            'role'    => 'assistant',
                            'content' => $quotaResult['message'],
                        ],
                        'redirect' => '/plans',
                    ], 200);   // 200 so chat UI treats it as a normal reply
                }
            }

            $prompt = (string) $request->input('message', '');
            $file   = $request->input('attachment_url'); // may be null

            // Brain orchestration: check for multi-service workflows first
            Log::info('[AIChatApiController] Checking brain orchestration', ['prompt' => Str::limit($prompt, 100), 'attachment' => $file]);
            
            $workflowComposer = app(WorkflowComposerService::class);
            $workflowResult = $workflowComposer->maybeCompose($prompt, $file, $user, $request->input('session_id'));
            
            Log::info('[AIChatApiController] Workflow composer result', ['hasResult' => !is_null($workflowResult)]);
            
            if ($workflowResult && isset($workflowResult['content'])) {
                // Create/update session
                $sessionId = $request->input('session_id');
                $title = Str::limit($prompt, 50);
                if ($sessionId) {
                    // Check if session exists first
                    $existingSession = ChatSession::find($sessionId);
                    
                    if ($existingSession) {
                        // Session exists - verify ownership
                        if ($existingSession->user_id !== $user->id) {
                            // Session belongs to different user - create new session
                            Log::warning('Session ID conflict in workflow - creating new session', [
                                'requested_session_id' => $sessionId,
                                'existing_user_id' => $existingSession->user_id,
                                'current_user_id' => $user->id,
                            ]);
                            $session = ChatSession::create([
                                'user_id' => $user->id,
                                'title' => $title,
                            ]);
                        } else {
                            // Session belongs to current user - use it
                            $session = $existingSession;
                            if ($session->title === 'Untitled' && $title !== 'Untitled') {
                                $session->update(['title' => $title]);
                            }
                        }
                    } else {
                        // Session doesn't exist - create it
                        $session = ChatSession::create([
                            'id' => $sessionId,
                            'user_id' => $user->id,
                            'title' => $title,
                        ]);
                    }
                } else {
                    $session = ChatSession::create([
                        'user_id' => $user->id,
                        'title'   => $title,
                    ]);
                }
                
                // Persist messages
                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'user_id' => $user->id,
                    'role' => 'user',
                    'content' => $prompt,
                ]);
                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'content' => $workflowResult['content'],
                ]);
                
                // Add proactive suggestions
                $suggestionService = app(ProactiveSuggestionService::class);
                $suggestions = $suggestionService->generateSuggestions($prompt, $file, $workflowResult, $user);
                
                return response()->json([
                    'session_id' => $session->id,
                    'message' => ['role' => 'assistant', 'content' => $workflowResult['content']],
                    'workflow' => $workflowResult['workflow'] ?? null,
                    'trace' => $workflowResult['trace'] ?? [],
                    'suggestions' => $suggestions,
                ]);
            }

            // Brain orchestration: auto-detect special inputs (e.g., YouTube links, documents)
            // Note: Brain orchestration is only available for authenticated users (not guests)
            $brain = null;
            if ($user) {
                Log::info('[AIChatApiController] Checking brain orchestrator');
                $orchestrator = app(BrainOrchestrator::class);
                
                // Priority 1: Uploaded file attachment (doc ingestion)
                if ($file) {
                    Log::info('[AIChatApiController] Processing uploaded file attachment', ['file' => $file]);
                    $brain = $orchestrator->processDocument($file, $user, $prompt);
                    if (!$brain) {
                        Log::warning('[AIChatApiController] Document processing failed', ['file' => $file]);
                        // Don't fallback to generic AI for invalid URLs - return helpful error
                        if (strpos($file, '/attachments/') === false) {
                            return response()->json([
                                'error' => 'Invalid attachment URL',
                                'message' => 'The file attachment URL is incomplete. Please try uploading again.',
                            ], 400);
                        }
                    }
                }
                // Priority 2: YouTube URL in prompt
                elseif (preg_match('/(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=[^\s&]+|youtu\.be\/[A-Za-z0-9_-]+))/i', $prompt, $m)) {
                    Log::info('[AIChatApiController] Fast-path YouTube routing', ['url' => $m[1]]);
                    $brain = $orchestrator->processYouTube($m[1], $user, $prompt);
                }
                // Priority 3: Document URL in prompt (no attachment)
                elseif (preg_match('/https?:\/\/\S+\.(pdf|docx?|rtf|pptx?|xlsx?|txt)\b/i', $prompt, $dm)) {
                    Log::info('[AIChatApiController] Fast-path Document routing', ['url' => $dm[0]]);
                    $brain = $orchestrator->processDocument($dm[0], $user, $prompt);
                }
                // Priority 4: General brain orchestrator (other intents)
                else {
                    $brain = $orchestrator->maybeHandle($prompt, null, $user);
                }
            } else {
                Log::info('[AIChatApiController] Skipping brain orchestration for guest user');
            }
            
            Log::info('[AIChatApiController] Brain orchestrator result', ['hasResult' => !is_null($brain), 'hasContent' => isset($brain['content'])]);

            if ($brain && isset($brain['content'])) {
                // Ensure session
                $sessionId = $request->input('session_id');
                $title = Str::limit(
                    trim(preg_replace('/[\s\r\n]+/', ' ', $prompt)) ?: ($file ? 'File prompt' : 'Untitled'),
                    50
                );

                if ($sessionId) {
                    // Check if session exists first
                    $existingSession = ChatSession::find($sessionId);
                    
                    if ($existingSession) {
                        // Session exists - verify ownership
                        if ($existingSession->user_id !== $user->id) {
                            // Session belongs to different user - create new session
                            Log::warning('Session ID conflict in brain - creating new session', [
                                'requested_session_id' => $sessionId,
                                'existing_user_id' => $existingSession->user_id,
                                'current_user_id' => $user->id,
                            ]);
                            $session = ChatSession::create([
                                'user_id' => $user->id,
                                'title' => $title,
                            ]);
                        } else {
                            // Session belongs to current user - use it
                            $session = $existingSession;
                            if ($session->title === 'Untitled' && $title !== 'Untitled') {
                                $session->update(['title' => $title]);
                            }
                        }
                    } else {
                        // Session doesn't exist - create it
                        $session = ChatSession::create([
                            'id' => $sessionId,
                            'user_id' => $user->id,
                            'title' => $title,
                        ]);
                    }
                } else {
                    $session = ChatSession::create([
                        'user_id' => $user->id,
                        'title'   => $title,
                    ]);
                }

                // Persist user prompt and assistant result
                if ($prompt) {
                    ChatMessage::create([
                        'chat_session_id' => $session->id,
                        'user_id'         => $user->id,
                        'role'            => 'user',
                        'content'         => $prompt,
                    ]);
                }
                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'user_id'         => $user->id,
                    'role'            => 'assistant',
                    'content'         => $brain['content'],
                ]);

                // Cache doc-session mapping if available
                $docId = null;
                foreach (($brain['trace'] ?? []) as $t) {
                    if (($t['service'] ?? '') === 'doc-service' && isset($t['doc_id'])) { $docId = $t['doc_id']; break; }
                }
                if ($docId) { ChatSessionDoc::firstOrCreate(['chat_session_id' => $session->id, 'doc_id' => $docId, 'provider' => 'doc-service']); Cache::put("chat_doc:{$session->id}", ['doc_id' => $docId], 86400); }

                if ($request->boolean('stream')) {
                    return response()->stream(function() use ($brain, $session) {
                        echo "event: phase\ndata: {\"phase\":\"ingestion\"}\n\n";
                        echo "event: phase\ndata: {\"phase\":\"answering\"}\n\n";
                        echo "event: result\ndata: ".json_encode(['session_id'=>$session->id,'message'=>['role'=>'assistant','content'=>$brain['content']]])."\n\n";
                        echo "event: end\ndata: {}\n\n";
                    }, 200, ['Content-Type'=>'text/event-stream']);
                }

                return response()->json([
                    'session_id' => $session->id,
                    'message'    => [ 'role' => 'assistant', 'content' => $brain['content'] ],
                ]);
            }

            // Doc-chat branch using doc-service if session is linked
            $sessionId = $request->input('session_id');
            if (!$file && $sessionId) {
                $docIds = ChatSessionDoc::where('chat_session_id', $sessionId)->pluck('doc_id')->all();
                if (empty($docIds)) {
                    $map = Cache::get("chat_doc:{$sessionId}");
                    if ($map && isset($map['doc_id'])) { $docIds = [$map['doc_id']]; }
                }
                if (!empty($docIds)) {
                    $base = rtrim((string) config('services.brain.doc_service_url'), '/');
                    $secret = (string) config('services.brain.hmac_secret');
                    if ($base && $secret) {
                        $ts = (string) time();
                        $cid = 'akili-backend';
                        $kid = 'default';
                        $path = '/v1/chat';
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
                            'conversation_id' => $sessionId,
                            'query' => $prompt,
                            'doc_ids' => $docIds,
                            'top_k' => 6,
                            'max_tokens' => 512,
                        ];
                        if ($request->boolean('stream')) {
                            return response()->stream(function() use ($headers, $base, $payload, $sessionId, $user, $prompt, $docIds) {
                                // phase: answering
                                echo "event: phase\ndata: {\"phase\":\"answering\"}\n\n";
                                ob_flush();
                                flush();
                                
                                // Use /v1/chat/stream for token-level streaming
                                $streamPath = '/v1/chat/stream';
                                $ts = (string) time();
                                $cid = 'akili-backend';
                                $kid = 'default';
                                $secret = (string) config('services.brain.hmac_secret');
                                $sig = hash_hmac('sha256', "POST|{$streamPath}||{$ts}|{$cid}|{$kid}", $secret);
                                $streamHeaders = [
                                    'X-Client-Id' => $cid,
                                    'X-Key-Id' => $kid,
                                    'X-Timestamp' => $ts,
                                    'X-Signature' => $sig,
                                    'X-Tenant-Id' => (string) $user->id,
                                    'Content-Type' => 'application/json',
                                ];
                                
                                try {
                                    $response = Http::withHeaders($streamHeaders)
                                        ->timeout(60)
                                        ->post($base.$streamPath, ['payload' => $payload]);
                                    
                                    $fullAnswer = '';
                                    $body = $response->body();
                                    
                                    // Parse SSE events from doc-service
                                    $lines = explode("\n", $body);
                                    foreach ($lines as $line) {
                                        if (strpos($line, 'data: ') === 0) {
                                            $json = substr($line, 6);
                                            $data = json_decode($json, true);
                                            
                                            if (isset($data['token'])) {
                                                $fullAnswer .= $data['token'];
                                                echo "event: token\ndata: {$json}\n\n";
                                                ob_flush();
                                                flush();
                                            } elseif (isset($data['answer'])) {
                                                $fullAnswer = $data['answer'];
                                            }
                                        }
                                    }
                                    
                                    // Persist user message and full answer
                                    if (!empty($prompt)) {
                                        ChatMessage::create([
                                            'chat_session_id' => $sessionId,
                                            'user_id' => $user->id,
                                            'role' => 'user',
                                            'content' => $prompt,
                                        ]);
                                    }
                                    if ($fullAnswer) {
                                        ChatMessage::create([
                                            'chat_session_id' => $sessionId,
                                            'user_id' => $user->id,
                                            'role' => 'assistant',
                                            'content' => $fullAnswer,
                                        ]);
                                    }
                                    
                                    echo "event: result\ndata: ".json_encode(['session_id' => $sessionId, 'answer' => $fullAnswer])."\n\n";
                                } catch (\Throwable $e) {
                                    Log::error('[Chat] Doc-service stream failed', ['error' => $e->getMessage()]);
                                    echo "event: error\ndata: ".json_encode(['error'=>$e->getMessage()])."\n\n";
                                }
                                echo "event: end\ndata: {}\n\n";
                            }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
                        } else {
                            $res = Http::withHeaders($headers)->post($base.'/v1/chat', ['payload' => $payload]);
                            $answer = $res->json()['answer'] ?? '[No answer]';
                            // Persist user and assistant
                            if ($prompt) {
                                ChatMessage::create([
                                    'chat_session_id' => $sessionId,
                                    'user_id' => $user->id,
                                    'role' => 'user',
                                    'content' => $prompt,
                                ]);
                            }
                            ChatMessage::create([
                                'chat_session_id' => $sessionId,
                                'user_id' => $user->id,
                                'role' => 'assistant',
                                'content' => $answer,
                            ]);
                            return response()->json([
                                'session_id' => $sessionId,
                                'message' => ['role' => 'assistant', 'content' => $answer],
                            ]);
                        }
                    }
                }
            }

            // 🔐 subscription / engine checks -----------------------------------
            // For guests, use default free plan; for authenticated users, use their subscription
            if ($isGuest) {
                $plan = Plan::where('is_default', true)
                    ->where('is_active', true)
                    ->where('region', $this->getRegion($request))
                    ->first();
                $subscription = null; // Guests don't have subscriptions
            } else {
                $subscription = $user->active_subscription;
                $plan = $subscription?->plan ?? Plan::where('is_default', true)
                    ->where('is_active', true)
                    ->where('region', $this->getRegion($request))
                    ->first();
            }
            
            if (!$plan) {
                Log::error('No plan available', [
                    'user_id' => $user?->id,
                    'is_guest' => $isGuest,
                    'guest_session_id' => $guestSession?->id,
                ]);
                return response()->json(['error' => 'No subscription plan available. Please contact support.'], 500);
            }
            
            $engine = $plan->aiEngine;
            if (!$engine || !$engine->is_active) {
                Log::error('No active AI engine', ['plan_id' => $plan->id, 'engine_id' => $plan->engine_id]);
                return response()->json(['error' => 'No active AI engine available'], 500);
            }
            
            // Check engine health before using (but be lenient - health checks can be unreliable)
            $healthService = app(\App\Services\AIEngineHealthService::class);
            $isHealthy = $healthService->isEngineHealthy($engine, false);
            
            // If health check says unhealthy, try a fresh check (health checks can be cached incorrectly)
            if (!$isHealthy) {
                Log::warning('Primary engine marked unhealthy in cache, doing fresh check', [
                    'engine_id' => $engine->id,
                    'engine_name' => $engine->name,
                ]);
                
                // Do a fresh health check (bypass cache)
                $isHealthy = $healthService->isEngineHealthy($engine, true);
            }
            
            // If still unhealthy, try fallback engines
            if (!$isHealthy) {
                Log::warning('Primary engine unhealthy after fresh check, trying fallback', [
                    'engine_id' => $engine->id,
                    'engine_name' => $engine->name,
                ]);
                
                // Try to find a healthy fallback engine
                $fallbackEngine = AIEngine::where('is_active', true)
                    ->where('is_fallback', true)
                    ->where('id', '!=', $engine->id)
                    ->get()
                    ->first(function ($fallback) use ($healthService) {
                        // Try fresh check for fallback too
                        return $healthService->isEngineHealthy($fallback, true);
                    });
                
                if ($fallbackEngine) {
                    $engine = $fallbackEngine;
                    Log::info('Using healthy fallback engine', ['engine_id' => $engine->id]);
                } else {
                    // Last resort: if engine is active, try using it anyway (health checks can be wrong)
                    // Only fail if engine is explicitly inactive
                    if ($engine->is_active) {
                        Log::warning('Health check failed but engine is active - proceeding anyway', [
                            'engine_id' => $engine->id,
                            'engine_name' => $engine->name,
                        ]);
                        // Continue with the engine despite health check failure
                    } else {
                        Log::error('No healthy engines available and primary engine is inactive', [
                            'primary_engine_id' => $engine->id,
                        ]);
                        return response()->json(['error' => 'AI service temporarily unavailable'], 503);
                    }
                }
            }
            
            try {
                $apiKey = Crypt::decryptString($engine->api_key);
            } catch (\Exception $e) {
                Log::error('Failed to decrypt key', ['engine_id' => $engine->id]);
                return response()->json(['error' => 'Engine key decrypt failed'], 500);
            }

            // 🗂️ session --------------------------------------------------------
            $sessionId = $request->input('session_id');
            $title = Str::limit(
                trim(preg_replace('/[\s\r\n]+/', ' ', $prompt)) ?: ($file ? 'File prompt' : 'Untitled'),
                50
            );
            
            if ($sessionId) {
                if ($isGuest) {
                    $session = ChatSession::firstOrCreate(
                        ['id' => $sessionId, 'guest_session_id' => $guestSession->id],
                        ['title' => $title, 'is_guest' => true]
                    );
                } else {
                    // Check if session exists first
                    $existingSession = ChatSession::find($sessionId);
                    
                    if ($existingSession) {
                        // Session exists - verify ownership
                        if ($existingSession->user_id !== $user->id) {
                            // Session belongs to different user - create new session
                            Log::warning('Session ID conflict - creating new session', [
                                'requested_session_id' => $sessionId,
                                'existing_user_id' => $existingSession->user_id,
                                'current_user_id' => $user->id,
                            ]);
                            $session = ChatSession::create([
                                'user_id' => $user->id,
                                'is_guest' => false,
                                'title' => $title,
                            ]);
                        } else {
                            // Session belongs to current user - use it
                            $session = $existingSession;
                            if ($session->title === 'Untitled' && $title !== 'Untitled') {
                                $session->update(['title' => $title]);
                            }
                        }
                    } else {
                        // Session doesn't exist - create it
                        $session = ChatSession::create([
                            'id' => $sessionId,
                            'user_id' => $user->id,
                            'is_guest' => false,
                            'title' => $title,
                        ]);
                    }
                }

                // Force title update only if it's still the default (for non-existing sessions)
                if (!isset($existingSession) && $session->title === 'Untitled' && $title !== 'Untitled') {
                    $session->update(['title' => $title]);
                }
            } else {
                if ($isGuest) {
                    $session = ChatSession::create([
                        'guest_session_id' => $guestSession->id,
                        'is_guest' => true,
                        'title'   => $title,
                    ]);
                } else {
                    $session = ChatSession::create([
                        'user_id' => $user->id,
                        'is_guest' => false,
                        'title'   => $title,
                    ]);
                }
            }
            
            // 📜 history + system prompt ---------------------------------------
            $messages = [
                [
                    'role'    => 'system',
                    'content' =>
                        'You are Akili, a helpful AI assistant. '
                        . 'When the user asks who you are or how you differ from ChatGPT, '
                        . 'begin with "I am Akili." **Then give a brief explanation of the differences '
                        . 'in at least two sentences.**',
                ],
            ];

            // append previous messages (if any)
            $history = $session->messages()
                ->orderBy('created_at')
                ->get(['role', 'content'])
                ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
                ->toArray();

            $messages = array_merge($messages, $history);

            // ➕ current user turn ----------------------------------------------
            if ($file) {
                // For AI Manager (DeepSeek), use simple text format with file info
                // For OpenAI/vision models, use image_url format
                $isAIManager = stripos($engine->provider, 'AI Manager') !== false;
                
                if ($isAIManager) {
                    // AI Manager expects simple string content
                    $fileInfo = "[Attached file: {$file}]";
                    $messages[] = [
                        'role' => 'user',
                        'content' => $prompt ? "{$fileInfo}\n{$prompt}" : $fileInfo,
                    ];
                } else {
                    // OpenAI vision format
                    $messages[] = [
                        'role'    => 'user',
                        'content' => [
                            ['type' => 'image_url', 'image_url' => ['url' => $file]],
                            ['type' => 'text',      'text'      => $prompt ?: '']
                        ],
                    ];
                }
            } else {
                $messages[] = ['role' => 'user', 'content' => $prompt];
            }

            // 🚀 send -----------------------------------------------------------
            $isAIManager = stripos($engine->provider, 'AI Manager') !== false;
            
            if ($isAIManager) {
                // AI Manager format
                $payload = [
                    'messages' => $messages,
                    'model' => $engine->version, // e.g., ollama:llama3
                    'response_format' => 'text',
                ];
                Log::info('Sending to AI Manager', ['model' => $engine->version, 'provider' => $engine->provider]);
                $res = Http::withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->timeout(60)->post($engine->api_url, $payload);
            } else {
                // OpenAI format
                $payload = [
                    'model'    => $engine->version,
                    'messages' => $messages,
                ];
                Log::info('Sending to OpenAI', ['model' => $engine->version]);
                $res = Http::withHeaders([
                    'Authorization' => "Bearer $apiKey",
                    'Content-Type'  => 'application/json',
                ])->timeout(60)->post($engine->api_url, $payload);
            }

            if ($res->failed()) {
                Log::error('AI request failed', [
                    'provider' => $engine->provider,
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
                
                // Try fallback engine (skip unhealthy ones)
                $healthService = app(\App\Services\AIEngineHealthService::class);
                $fallbackEngines = AIEngine::where('is_active', true)
                    ->where('is_fallback', true)
                    ->where('id', '!=', $engine->id)
                    ->orderBy('priority_order')
                    ->get()
                    ->filter(function ($fallback) use ($healthService) {
                        // Only use healthy fallback engines
                        return $healthService->isEngineHealthy($fallback, false);
                    });
                
                if ($fallbackEngines->isNotEmpty()) {
                    $fallbackEngine = $fallbackEngines->first();
                    Log::info('Trying healthy fallback engine', ['fallback' => $fallbackEngine->name]);
                    
                    try {
                        $fallbackKey = Crypt::decryptString($fallbackEngine->api_key);
                        $fallbackPayload = [
                            'model' => $fallbackEngine->version,
                            'messages' => $messages,
                        ];
                        
                        $fallbackRes = Http::withHeaders([
                            'Authorization' => "Bearer $fallbackKey",
                            'Content-Type' => 'application/json',
                        ])->timeout(60)->post($fallbackEngine->api_url, $fallbackPayload);
                        
                        if ($fallbackRes->successful()) {
                            $res = $fallbackRes;
                            $engine = $fallbackEngine; // Update engine reference for token tracking
                            Log::info('Fallback successful', ['engine' => $engine->name]);
                        } else {
                            Log::error('Fallback engine request failed', [
                                'provider' => $fallbackEngine->provider,
                                'status' => $fallbackRes->status(),
                                'body' => $fallbackRes->body(),
                            ]);
                            return response()->json(['error' => 'Both primary and fallback AI services failed'], 500);
                        }
                    } catch (\Exception $e) {
                        Log::error('Fallback failed with exception', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        return response()->json(['error' => 'AI service unavailable'], 500);
                    }
                } else {
                    Log::warning('No healthy fallback engine available', [
                        'current_engine_id' => $engine->id,
                    ]);
                    return response()->json(['error' => 'AI service unavailable'], 500);
                }
            }

            $data  = $res->json();
            
            // Parse response based on provider
            if ($isAIManager && isset($data['data']['content'])) {
                // AI Manager response format
                $reply = is_string($data['data']['content']) 
                    ? $data['data']['content'] 
                    : ($data['data']['raw'] ?? '[No reply]');
            } else {
                // OpenAI response format
                $reply = $data['choices'][0]['message']['content'] ?? '[No reply]';
            }

            // 💾 persist messages ---------------------------------------------
            $messageData = [
                'chat_session_id' => $session->id,
            ];
            
            if ($isGuest) {
                $messageData['guest_session_id'] = $guestSession->id;
            } else {
                $messageData['user_id'] = $user->id;
            }
            
            if ($file) {
                ChatMessage::create(array_merge($messageData, [
                    'role'            => 'user',
                    'content'         => $file,
                    'is_attachment'   => true,
                ]));
            }
            if ($prompt) {
                ChatMessage::create(array_merge($messageData, [
                    'role'            => 'user',
                    'content'         => $prompt,
                ]));
            }
            ChatMessage::create(array_merge($messageData, [
                'role'            => 'assistant',
                'content'         => $reply,
            ]));

            // 📊 token usage (optional)
            // Only track for authenticated users with subscriptions
            if (isset($data['usage']) && !$isGuest && $subscription) {
                $tokensUsed = $data['usage']['total_tokens'] ?? 0;
                $cost = ($engine->price_per_1k ?? 0) * $tokensUsed / 1000;
                
                // Use transaction with lock to prevent race conditions
                DB::transaction(function () use ($user, $session, $engine, $subscription, $tokensUsed, $cost, $prompt, $reply) {
                    // Lock the subscription row for update
                    $lockedSubscription = Subscription::where('id', $subscription->id)
                        ->lockForUpdate()
                        ->first();
                    
                    if ($lockedSubscription) {
                        // Create token usage record
                        TokenUsage::create([
                            'user_id'         => $user->id,
                            'chat_session_id' => $session->id,
                            'engine_id'       => $engine->id,
                            'subscription_id' => $lockedSubscription->id,
                            'tokens_used'     => $tokensUsed,
                            'cost'            => $cost,
                            'prompt'          => substr($prompt ?? '', 0, 65535), // Truncate if too long
                            'response'        => substr($reply ?? '', 0, 4294967295), // Truncate if too long
                            'source'          => 'chat',
                        ]);
                        
                        // Atomically increment subscription tokens_used
                        $lockedSubscription->increment('tokens_used', $tokensUsed);
                    }
                });
            } elseif (isset($data['usage']) && $isGuest) {
                // For guests, track usage in guest_session metadata (optional, for analytics)
                $tokensUsed = $data['usage']['total_tokens'] ?? 0;
                $guestSession->metadata = array_merge($guestSession->metadata ?? [], [
                    'total_tokens_used' => ($guestSession->metadata['total_tokens_used'] ?? 0) + $tokensUsed,
                    'last_usage' => now()->toIso8601String(),
                ]);
                $guestSession->save();
            }

            return response()->json([
                'session_id' => $session->id,
                'message'    => [ 'role' => 'assistant', 'content' => $reply ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Chat request failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    
    public function uploadAttachment(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,rtf,ppt,pptx,xls,xlsx,txt|max:10240', // Max 10MB
            ]);

            $user = $request->user();
            $file = $request->file('file');
            
            // Check if temp file exists before processing
            if ($file && !file_exists($file->getPathname())) {
                Log::error('[Upload] Temp file does not exist', [
                    'original_name' => $file->getClientOriginalName(),
                    'temp_path' => $file->getPathname(),
                ]);
                return response()->json(['error' => 'Upload failed: Temporary file not found'], 400);
            }
            
            Log::info('[Upload] File received', [
                'file' => $file ? 'present' : 'null',
                'is_valid' => $file ? $file->isValid() : 'null',
                'original_name' => $file ? $file->getClientOriginalName() : 'null',
                'size' => $file ? $file->getSize() : 'null',
                'mime' => $file ? $file->getMimeType() : 'null',
                'error_code' => $file ? $file->getError() : 'null',
                'error_message' => $file ? $file->getErrorMessage() : 'null',
                'temp_path' => $file ? $file->getPathname() : 'null',
            ]);
            
            // Handle specific upload errors
            if ($file) {
                $errorCode = $file->getError();
                if ($errorCode === UPLOAD_ERR_INI_SIZE) {
                    return response()->json(['error' => 'File too large (exceeds upload_max_filesize)'], 400);
                } elseif ($errorCode === UPLOAD_ERR_FORM_SIZE) {
                    return response()->json(['error' => 'File too large (exceeds MAX_FILE_SIZE directive)'], 400);
                } elseif ($errorCode === UPLOAD_ERR_PARTIAL) {
                    return response()->json(['error' => 'File was only partially uploaded'], 400);
                } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
                    return response()->json(['error' => 'No file was uploaded'], 400);
                } elseif ($errorCode === UPLOAD_ERR_NO_TMP_DIR) {
                    return response()->json(['error' => 'Missing temporary folder'], 500);
                } elseif ($errorCode === UPLOAD_ERR_CANT_WRITE) {
                    return response()->json(['error' => 'Failed to write file to disk'], 500);
                } elseif ($errorCode === UPLOAD_ERR_EXTENSION) {
                    Log::error('[Upload] PHP extension stopped the file upload - this is likely a server configuration issue', [
                        'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                    ]);
                    return response()->json(['error' => 'Server configuration prevented file upload - contact administrator'], 500);
                }
                
                // Additional check for isValid() which might reveal more issues
                if (!$file->isValid()) {
                    Log::error('[Upload] File is not valid according to isValid()', [
                        'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'error_code' => $file->getError(),
                        'error_message' => $file->getErrorMessage(),
                        'temp_path' => $file->getPathname(),
                        'move_uploaded_file_result' => function_exists('move_uploaded_file') ? 'function_exists' : 'function_missing',
                    ]);
                    return response()->json(['error' => 'File is not valid: ' . $file->getErrorMessage()], 400);
                }
                
                // Check if the file has been moved already
                if (!file_exists($file->getPathname())) {
                    Log::error('[Upload] File was moved or deleted before processing', [
                        'original_name' => $file->getClientOriginalName(),
                        'expected_path' => $file->getPathname(),
                        'realpath' => realpath($file->getPathname()),
                    ]);
                    return response()->json(['error' => 'File was moved or deleted before processing'], 500);
                }
            }
            
            if (!$file || !$file->isValid()) {
                Log::error('[Upload] Invalid file upload', [
                    'file' => $file,
                    'is_valid' => $file ? $file->isValid() : 'null',
                    'error_code' => $file ? $file->getError() : 'null',
                    'error' => $file ? $file->getErrorMessage() : 'File is null',
                ]);
                return response()->json(['error' => 'Invalid file upload: ' . ($file ? $file->getErrorMessage() : 'No file received')], 400);
            }

            // Store in: storage/app/public/attachments/
            try {
                Log::info('[Upload] Attempting to store file', [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'temp_path' => $file->getPathname(),
                    'realpath' => realpath($file->getPathname()),
                    'is_readable' => is_readable($file->getPathname()),
                    'file_exists' => file_exists($file->getPathname()),
                ]);
                
                // Try to get more information about the temp file
                if (file_exists($file->getPathname())) {
                    $stat = stat($file->getPathname());
                    Log::info('[Upload] Temp file stats', [
                        'stat' => $stat,
                        'filesize' => filesize($file->getPathname()),
                    ]);
                }
                
                // Check if file is still there right before storing
                if (!file_exists($file->getPathname())) {
                    Log::error('[Upload] File disappeared just before storing', [
                        'original_name' => $file->getClientOriginalName(),
                        'expected_path' => $file->getPathname(),
                    ]);
                    return response()->json(['error' => 'File disappeared just before storing'], 500);
                }
                
                $path = $file->store('attachments', 'public');
                Log::info('[Upload] Store result', ['path' => $path]);
                if (!$path) {
                    // Try alternative storage method to get more information
                    Log::info('[Upload] Trying alternative storage method');
                    $newFilename = uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = Storage::disk('public')->putFileAs('attachments', $file, $newFilename);
                    Log::info('[Upload] Alternative store result', ['path' => $path]);
                    
                    if (!$path) {
                        // Check if file is still there after failed attempts
                        $fileExistsAfterFailure = file_exists($file->getPathname());
                        Log::error('[Upload] File store returned false', [
                            'filename' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'temp_path' => $file->getPathname(),
                            'disk' => 'public',
                            'directory' => 'attachments',
                            'file_still_exists' => $fileExistsAfterFailure,
                        ]);
                        
                        // If file still exists, try to copy it manually
                        if ($fileExistsAfterFailure) {
                            Log::info('[Upload] Trying manual file copy');
                            $manualPath = 'attachments/' . uniqid() . '.' . $file->getClientOriginalExtension();
                            $fullManualPath = storage_path('app/public/' . $manualPath);
                            $copyResult = copy($file->getPathname(), $fullManualPath);
                            Log::info('[Upload] Manual copy result', [
                                'success' => $copyResult,
                                'manual_path' => $copyResult ? $manualPath : null,
                            ]);
                            
                            if ($copyResult) {
                                $path = $manualPath;
                            }
                        }
                        
                        if (!$path) {
                            return response()->json(['error' => 'Failed to store file'], 500);
                        }
                    }
                }
            } catch (FileNotFoundException $e) {
                Log::error('[Upload] File not found exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json(['error' => 'Upload failed: File not found - ' . $e->getMessage()], 500);
            } catch (\Throwable $e) {
                Log::error('[Upload] File store exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Return detailed error in debug mode
                $errorMessage = env('APP_DEBUG', false) ? 'File storage failed: ' . $e->getMessage() : 'Upload failed';
                return response()->json(['error' => $errorMessage], 500);
            }
            
            // Generate full URL using zooys pattern - more reliable than asset()
            $baseUrl = request()->getSchemeAndHttpHost();
            $fullUrl = $baseUrl . '/storage/' . $path;
            
            Log::info('[Upload] File uploaded successfully', [
                'path' => $path,
                'full_url' => $fullUrl,
                'filename' => $file->getClientOriginalName(),
            ]);

            return response()->json([
                'url'         => $fullUrl, // Full absolute URL
                'filename'    => $file->getClientOriginalName(),
                'extension'   => $file->getClientOriginalExtension(),
                'mime'        => $file->getMimeType(),
                'size_kb'     => round($file->getSize() / 1024, 2),
                'uploaded_at' => now()->toDateTimeString(),
            ]);
        } catch (FileNotFoundException $e) {
            Log::error('[Upload] File not found exception in main catch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $errorMessage = env('APP_DEBUG', false) ? 'Upload failed: File not found - ' . $e->getMessage() : 'Upload failed: File not found';
            return response()->json(['error' => $errorMessage], 500);
        } catch (\Throwable $e) {
            Log::error('[Upload] Upload failed with exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Return detailed error in debug mode
            $errorMessage = env('APP_DEBUG', false) ? 'Upload failed: ' . $e->getMessage() : 'Upload failed';
            return response()->json(['error' => $errorMessage], 500);
        }
    }
}
