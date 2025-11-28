<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkflowOptimizerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkflowInsightsController extends Controller
{
    private WorkflowOptimizerService $optimizer;
    
    public function __construct(WorkflowOptimizerService $optimizer)
    {
        $this->optimizer = $optimizer;
    }
    
    /**
     * Get performance summary for authenticated user
     */
    public function summary(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $user = $request->user();
        
        $summary = $this->optimizer->getPerformanceSummary($user, $days);
        
        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
    
    /**
     * Get optimization recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $recommendations = $this->optimizer->getRecommendations($user);
        
        return response()->json([
            'success' => true,
            'data' => $recommendations,
            'count' => count($recommendations),
        ]);
    }
}
