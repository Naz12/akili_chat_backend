<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Generates proactive suggestions based on user input and workflow results
 */
class ProactiveSuggestionService
{
    /**
     * Generate contextual suggestions for the user
     */
    public function generateSuggestions(string $prompt, ?string $attachmentUrl, array $workflowResult, User $user): array
    {
        $suggestions = [];
        
        // Detect YouTube content
        if ($this->hasYouTubeUrl($prompt)) {
            $suggestions[] = [
                'type' => 'feature',
                'icon' => '⏱️',
                'text' => 'Want timestamps for key moments in the video?',
                'action' => 'extract_timestamps',
            ];
            $suggestions[] = [
                'type' => 'export',
                'icon' => '📄',
                'text' => 'Export summary as PDF',
                'action' => 'export_pdf',
            ];
        }
        
        // Detect document upload
        if ($attachmentUrl && $this->isDocument($attachmentUrl)) {
            $suggestions[] = [
                'type' => 'feature',
                'icon' => '💡',
                'text' => 'Generate flashcards from this document?',
                'action' => 'generate_flashcards',
            ];
            $suggestions[] = [
                'type' => 'feature',
                'icon' => '🎯',
                'text' => 'Create a quiz to test understanding',
                'action' => 'generate_quiz',
            ];
            $suggestions[] = [
                'type' => 'feature',
                'icon' => '🗂️',
                'text' => 'Extract and organize key facts',
                'action' => 'extract_facts',
            ];
        }
        
        // Detect comparison workflow
        if (isset($workflowResult['workflow']['type']) && str_contains($workflowResult['workflow']['type'], 'compare')) {
            $suggestions[] = [
                'type' => 'insight',
                'icon' => '📊',
                'text' => 'Visualize comparison as a table or chart',
                'action' => 'visualize_comparison',
            ];
            $suggestions[] = [
                'type' => 'feature',
                'icon' => '✅',
                'text' => 'Create decision matrix',
                'action' => 'create_matrix',
            ];
        }
        
        // Context-aware follow-ups
        if (preg_match('/research|academic|study/i', $prompt)) {
            $suggestions[] = [
                'type' => 'followup',
                'icon' => '🔍',
                'text' => 'Find related academic papers',
                'action' => 'search_papers',
            ];
        }
        
        // Cost optimization suggestions
        $trace = $workflowResult['trace'] ?? [];
        $duration = 0;
        foreach ($trace as $t) {
            if (isset($t['duration'])) {
                $duration += (float) str_replace('s', '', $t['duration']);
            }
        }
        
        if ($duration > 20) {
            $suggestions[] = [
                'type' => 'optimization',
                'icon' => '⚡',
                'text' => 'This query took ' . round($duration) . 's. Next time it will be instant (cached).',
                'action' => 'info',
            ];
        }
        
        return $suggestions;
    }
    
    private function hasYouTubeUrl(string $text): bool
    {
        return (bool) preg_match('/(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)[^\s&]+)/i', $text);
    }
    
    private function isDocument(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['pdf','doc','docx','rtf','ppt','pptx','xls','xlsx','txt']);
    }
}
