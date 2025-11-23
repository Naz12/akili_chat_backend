<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Plan;
use App\Models\AIEngine;
use App\Models\TokenUsage;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Traits\DetectsRegion;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use App\Services\UsageValidatorService;
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
            Log::info('=== Chat Request Started ===', [
                'user_id' => $request->user()?->id,
                'user_region' => $request->user()?->region,
                'message_length' => strlen($request->input('message', '')),
                'has_session_id' => !empty($request->input('session_id')),
            ]);
            
            $user   = $request->user();

            // ✅ Prevent execution if quota failed
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

        $prompt = (string) $request->input('message', '');
        $file   = $request->input('attachment_url'); // may be null

        // 🔐 subscription / engine checks -----------------------------------
        $subscription = $user->active_subscription;
        $plan = $subscription?->plan ?? Plan::where('is_default', true)->where('is_active', true)->first();
        
        if (!$plan) {
            Log::error('No plan available for user', ['user_id' => $user->id]);
            return response()->json(['error' => 'No subscription plan available. Please contact support.'], 500);
        }
        
        $engine = $plan->aiEngine;
        if (!$engine || !$engine->is_active) {
            Log::error('No active AI engine', ['plan_id' => $plan->id, 'engine_id' => $plan->engine_id]);
            return response()->json(['error' => 'No active AI engine available'], 500);
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
            $session = ChatSession::firstOrCreate(
                ['id' => $sessionId, 'user_id' => $user->id],
                ['title' => $title]
            );

            // Force title update only if it's still the default
            if ($session->title === 'Untitled' && $title !== 'Untitled') {
                $session->update(['title' => $title]);
            }
        } else {
            $session = ChatSession::create([
                'user_id' => $user->id,
                'title'   => $title,
            ]);
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
            // minimal file‑only vision block; add text if provided
            $messages[] = [
                'role'    => 'user',
                'content' => [
                    ['type' => 'image_url', 'image_url' => ['url' => $file]],
                    ['type' => 'text',      'text'      => $prompt ?: '']
                ],
            ];
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
            
            // Try fallback engine
            $fallbackEngine = AIEngine::where('is_active', true)
                ->where('is_fallback', true)
                ->orderBy('priority_order')
                ->first();
            
            if ($fallbackEngine && $fallbackEngine->id !== $engine->id) {
                Log::info('Trying fallback engine', ['fallback' => $fallbackEngine->name]);
                
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
                        return response()->json(['error' => 'Both primary and fallback AI services failed'], 500);
                    }
                } catch (\Exception $e) {
                    Log::error('Fallback failed', ['error' => $e->getMessage()]);
                    return response()->json(['error' => 'AI service unavailable'], 500);
                }
            } else {
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
        if ($file) {
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'user_id'         => $user->id,
                'role'            => 'user',
                'content'         => $file,
                'is_vision_support'   => true,
            ]);
        }
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
            'content'         => $reply,
        ]);

        // 📊 token usage (optional)
        if (isset($data['usage'])) {
            TokenUsage::create([
                'user_id'       => $user->id,
                'chat_session_id' => $session->id,
                'engine_id'     => $engine->id,
                'subscription_id' => $subscription?->id,
                'tokens_used'   => $data['usage']['total_tokens'] ?? 0,
                'cost'          => ($engine->price_per_1k ?? 0) * ($data['usage']['total_tokens'] ?? 0) / 1000,
            ]);
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
                'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240', // Max 10MB
            ]);
    
            $user = $request->user();
            $file = $request->file('file');
    
            // Store in: storage/app/public/attachments/
            $path = $file->store('attachments', 'public');
    
            return response()->json([
                'url'         => asset('storage/' . $path), // ✅ This is your public URL
                'filename'    => $file->getClientOriginalName(),
                'extension'   => $file->getClientOriginalExtension(),
                'mime'        => $file->getMimeType(),
                'size_kb'     => round($file->getSize() / 1024, 2),
                'uploaded_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Upload failed: ' . $e->getMessage());
            return response()->json(['error' => 'Upload failed'], 500);
        }
    }
    

}