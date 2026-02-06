<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkflowAnalytic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Self-optimizing workflow analyzer
 * Learns from execution patterns and suggests optimizations
 */
class WorkflowOptimizerService
{
    // Pricing per 1M tokens (USD)
    private const MODEL_COSTS = [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
    ];
    
    /**
     * Track workflow execution for analytics and learning
     */
    public function trackExecution(User $user, string $workflowType, array $result): void
    {
        $duration = 0;
        $tokensUsed = 0;
        $servicesUsed = [];
        $cacheHit = false;
        
        // Parse trace for metrics
        foreach ($result['trace'] ?? [] as $step) {
            if (isset($step['service'])) {
                $servicesUsed[] = $step['service'];
            }
            if (isset($step['duration'])) {
                $duration += (float) str_replace('s', '', $step['duration']);
            }
            if ($step['action'] ?? '' === 'hit') {
                $cacheHit = true;
            }
        }
        
        // Estimate cost based on services used
        $costEstimate = $this->estimateCost($servicesUsed, $tokensUsed, $result);
        
        try {
            WorkflowAnalytic::create([
                'user_id' => $user->id,
                'workflow_type' => $workflowType,
                'intent' => $result['intent']['intent'] ?? null,
                'duration' => $duration,
                'tokens_used' => $tokensUsed,
                'cost_estimate' => $costEstimate,
                'services_used' => array_values(array_unique($servicesUsed)),
                'cache_hit' => $cacheHit,
                'trace' => $result['trace'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('[WorkflowOptimizer] Failed to track execution', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Get optimization recommendations based on historical data
     */
    public function getRecommendations(User $user): array
    {
        $recommendations = [];
        
        // Analyze slow workflows
        $slowWorkflows = WorkflowAnalytic::where('user_id', $user->id)
            ->where('duration', '>', 15)
            ->where('cache_hit', false)
            ->select('workflow_type', DB::raw('AVG(duration) as avg_duration'), DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_type')
            ->having('count', '>=', 3)
            ->get();
        
        foreach ($slowWorkflows as $wf) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => "Your {$wf->workflow_type} workflows average " . round($wf->avg_duration) . "s. Results are now cached for instant retrieval.",
                'potential_savings' => round($wf->avg_duration * $wf->count) . 's saved on repeated queries',
            ];
        }
        
        // Identify cost-heavy workflows
        $costlyWorkflows = WorkflowAnalytic::where('user_id', $user->id)
            ->select('workflow_type', DB::raw('SUM(cost_estimate) as total_cost'), DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_type')
            ->having('total_cost', '>', 0.10)
            ->orderByDesc('total_cost')
            ->get();
        
        foreach ($costlyWorkflows as $wf) {
            $recommendations[] = [
                'type' => 'cost',
                'priority' => 'high',
                'message' => "{$wf->workflow_type} workflows have cost \$" . number_format($wf->total_cost, 4) . " across {$wf->count} uses.",
                'suggestion' => 'Consider using gpt-4o-mini for summaries to reduce costs by ~85%',
            ];
        }
        
        // Find optimal service combinations
        $bestCombos = $this->findBestServiceCombinations($user);
        if (!empty($bestCombos)) {
            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'low',
                'message' => 'We learned that ' . $bestCombos['insight'],
                'stats' => $bestCombos['stats'],
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Find which service combinations work best
     */
    private function findBestServiceCombinations(User $user): ?array
    {
        // Find workflows with multiple service attempts
        $workflowStats = WorkflowAnalytic::where('user_id', $user->id)
            ->whereNotNull('services_used')
            ->get()
            ->groupBy('workflow_type');
        
        foreach ($workflowStats as $type => $executions) {
            if ($executions->count() < 5) continue; // Need enough data
            
            $avgDuration = $executions->avg('duration');
            $cacheHitRate = $executions->where('cache_hit', true)->count() / $executions->count();
            
            if ($cacheHitRate > 0.3) {
                return [
                    'insight' => "$type workflows hit cache " . round($cacheHitRate * 100) . "% of the time, saving ~" . round($avgDuration * $cacheHitRate * $executions->count()) . "s total",
                    'stats' => [
                        'workflow' => $type,
                        'cache_hit_rate' => round($cacheHitRate, 2),
                        'avg_duration' => round($avgDuration, 1),
                        'executions' => $executions->count(),
                    ],
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Estimate cost of workflow execution
     */
    private function estimateCost(array $services, int $tokens, array $result): float
    {
        $cost = 0;
        
        // Rough estimates per service
        foreach ($services as $service) {
            switch ($service) {
                case 'summary':
                case 'openai':
                    // Assume gpt-4o for now, ~1000 input + 500 output tokens
                    $cost += (1000 / 1000000 * 2.50) + (500 / 1000000 * 10.00);
                    break;
                case 'workflow':
                    // Comparison uses gpt-4o, ~2000 input + 1000 output
                    $cost += (2000 / 1000000 * 2.50) + (1000 / 1000000 * 10.00);
                    break;
                case 'transcriber':
                    // Transcriber is free/self-hosted
                    break;
                case 'doc-service':
                    // Doc-service internal processing
                    $cost += 0.001; // Minimal compute cost
                    break;
            }
        }
        
        return round($cost, 4);
    }
    
    /**
     * Get workflow performance summary for user
     */
    public function getPerformanceSummary(User $user, int $days = 7): array
    {
        $since = now()->subDays($days);
        
        $stats = WorkflowAnalytic::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_workflows,
                SUM(CASE WHEN cache_hit THEN 1 ELSE 0 END) as cache_hits,
                AVG(duration) as avg_duration,
                SUM(cost_estimate) as total_cost,
                SUM(CASE WHEN duration > 20 THEN 1 ELSE 0 END) as slow_workflows
            ')
            ->first();
        
        $topWorkflows = WorkflowAnalytic::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->select('workflow_type', DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_type')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        
        return [
            'period' => "{$days} days",
            'total_workflows' => $stats->total_workflows ?? 0,
            'cache_hit_rate' => $stats->total_workflows > 0 ? round(($stats->cache_hits / $stats->total_workflows) * 100, 1) . '%' : '0%',
            'avg_duration' => round($stats->avg_duration ?? 0, 1) . 's',
            'total_cost' => '$' . number_format($stats->total_cost ?? 0, 4),
            'slow_workflows' => $stats->slow_workflows ?? 0,
            'top_workflows' => $topWorkflows->map(fn($w) => ['type' => $w->workflow_type, 'count' => $w->count])->toArray(),
        ];
    }
}
