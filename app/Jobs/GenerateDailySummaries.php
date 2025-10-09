<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\DailySummary;
use App\Services\AIChatSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDailySummaries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $today = Carbon::today()->toDateString();
        \Log::info("✅ [DailySummary] Running for date: $today");
    
        $ai = app(AIChatSummaryService::class);
    
        User::chunk(50, function ($users) use ($today, $ai) {
            foreach ($users as $user) {
                $messages = $user->chatMessages()
                    ->whereDate('created_at', $today)
                    ->orderBy('created_at')
                    ->pluck('content')
                    ->implode("\n");
    
                if (trim($messages) === '') {
                    \Log::info("⛔ No messages for user #{$user->id}");
                    continue;
                }
    
                \Log::info("🧠 Summarizing messages for user #{$user->id}");
    
                // ✅ Expect summary + token usage
                $result = $ai->summarize($messages, [
                    'type' => 'daily',
                    'date' => $today,
                    'user_id' => $user->id,
                    'instructions' => 'Summarize what the user talked about today in a concise way.',
                ]);
    
                $summaryText = is_array($result) ? $result['text'] ?? null : $result;
                $tokensUsed = is_array($result) ? $result['tokens_used'] ?? null : null;
    
                \Log::info("💾 Saving summary for user #{$user->id}");
    
                DailySummary::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'summary_date' => $today,
                    ],
                    [
                        'summary_text' => $summaryText,
                        'generated_by' => 'ai',
                        'tokens_used'  => $tokensUsed,
                    ]
                );
            }
        });
    }
    
}