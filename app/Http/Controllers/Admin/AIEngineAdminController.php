<?php

namespace App\Http\Controllers\Admin;

use App\Models\AIEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Services\ModelPricingService;
use Illuminate\Support\Facades\Crypt;

class AIEngineAdminController extends Controller
{
    public function index()
    {
        $engines = AIEngine::orderBy('priority_order')->get();
    
        $engineStatuses = $engines->map(function ($engine) {
            return [
                'id' => $engine->id, // ✅ Add this line
                'name' => $engine->name,
                'provider' => $engine->provider,
                'online' => $engine->last_test_success && now()->diffInMinutes($engine->last_test_success) < 60,
                'last_test_success' => $engine->last_test_success,
                'avg_cost_per_request' => $engine->avg_cost_per_request,
                'failure_count' => $engine->failure_count,
                'is_vision_support'    => $engine->is_vision_support,  // ← add this

            ];
        });
        
    
        return view('admin.ai_engines.index', compact('engines', 'engineStatuses'));
    }
    

    public function create(ModelPricingService $modelPricingService)
    {
        $models = $modelPricingService->getModels();
        $ai_engine = new AIEngine(); // 🔁 Provide an empty model to avoid null

        return view('admin.ai_engines.create', compact('models', 'ai_engine'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'provider' => 'required|string|max:100',
            'api_url' => 'required|url',
            'api_key' => 'nullable|string|max:255',
            'max_tokens' => 'required|integer|min:1',
            'price_per_1k' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'model_type' => 'required|string|in:chat,embedding,vision',
            'version' => 'nullable|string|max:50',
            'priority_order' => 'nullable|integer|min:1',
            'is_fallback' => 'nullable|boolean',
            'readonly_api_key' => 'nullable|boolean',
            'is_vision_support' => 'nullable|boolean',
        ]);

        // Encrypt API Key if provided
        if (!empty($validated['api_key'])) {
            $validated['api_key'] = Crypt::encryptString($validated['api_key']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_fallback'] = $request->boolean('is_fallback');
        $validated['readonly_api_key'] = $request->boolean('readonly_api_key');
        $validated['priority_order'] = $validated['priority_order'] ?? 1;
        $validated['is_vision_support'] = $request->boolean('is_vision_support');  // ← add


        AIEngine::create($validated);

        return redirect()->route('admin.ai-engines.index')->with('success', 'AI engine added successfully.');
    }

    public function edit(AIEngine $ai_engine, ModelPricingService $modelPricingService)
    {
        $models = $modelPricingService->getModels();
        return view('admin.ai_engines.edit', compact('ai_engine', 'models'));
    }

    public function update(Request $request, AIEngine $ai_engine)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'provider' => 'required|string|max:100',
            'api_url' => 'required|url',
            'api_key' => 'nullable|string|max:255',
            'max_tokens' => 'required|integer|min:1',
            'price_per_1k' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'model_type' => 'required|string|in:chat,embedding,vision',
            'version' => 'nullable|string|max:50',
            'priority_order' => 'nullable|integer|min:1',
            'is_fallback' => 'nullable|boolean',
            'readonly_api_key' => 'nullable|boolean',
            'is_vision_support' => 'nullable|boolean',
        ]);

        if ($ai_engine->readonly_api_key) {
            unset($validated['api_key']); // 🔐 prevent override
        } elseif (!empty($validated['api_key'])) {
            $validated['api_key'] = Crypt::encryptString($validated['api_key']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_fallback'] = $request->boolean('is_fallback');
        $validated['readonly_api_key'] = $request->boolean('readonly_api_key');
        $validated['priority_order'] = $validated['priority_order'] ?? 1;
        $validated['is_vision_support'] = $request->boolean('is_vision_support');  // ← add


        $ai_engine->update($validated);

        return redirect()->route('admin.ai-engines.index')->with('success', 'AI engine updated successfully.');
    }

    public function destroy(AIEngine $engine)
    {
        if ($engine->plans()->exists()) {
            return back()->with('error', 'This engine is assigned to one or more plans and cannot be deleted. Please reassign or delete those plans first.');
        }
    
        $engine->delete();
    
        return back()->with('success', 'Engine deleted successfully.');
    }
    

    public function test(AIEngine $ai_engine)
    {
        try {
            $apiKey = Crypt::decryptString($ai_engine->api_key);
        } catch (\Exception $e) {
            return response()->json(['error' => '🔐 Failed to decrypt API key'], 500);
        }
    
        $payload = [
            'model' => $ai_engine->version,
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ];
    
        $ch = curl_init($ai_engine->api_url);
    
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$apiKey}",
                'Content-Type: application/json',
            ],
        ]);
    
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
    
        if ($error) {
            logger()->error("❌ AI engine test failed: $error");
            // mark engine as offline
            $ai_engine->update([
                'online' => false,
            ]);
            return response()->json(['error' => $error], 500);
        }
    
        // ✅ Successful response – mark as online & save timestamp
        $ai_engine->update([
            'online' => true,
            'last_test_success' => now(),
        ]);
    
        return response()->json([
            'message' => '✅ AI engine is online',
            'response' => json_decode($response, true),
        ]);
    }
    
    
    public function stats(AIEngine $engine)
    {
        $logs = $engine->usages()
            ->selectRaw('COUNT(*) as total_requests, SUM(tokens_used) as total_tokens, SUM(cost) as total_cost')
            ->first();

        return view('admin.ai_engines.stats', compact('engine', 'logs'));
    }
}