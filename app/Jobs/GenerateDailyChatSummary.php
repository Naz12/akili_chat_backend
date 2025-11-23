<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\ChatMessage;
use App\Models\DailySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Services\AIChatSummaryService;

class GenerateDailyChatSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $date;

    public function __construct(User $user, ?string $date = null)
    {
        $this->user = $user;
        $this->date = $date ?? now()->toDateString(); // default to today
    }

    public function handle()
    {
        $from = Carbon::parse($this->date)->startOfDay();
        $to = Carbon::parse($this->date)->endOfDay();

        $messages = ChatMessage::where('user_id', $this->user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        if ($messages->isEmpty()) {
            Log::info("🕳️ No messages to summarize for user {$this->user->id} on {$this->date}");
            return;
        }

        $conversation = $messages->map(fn($msg) => "{$msg->role}: {$msg->content}")->implode("\n");

        $ai = app(AIChatSummaryService::class);
        $result = $ai->summarize($conversation);

        DailySummary::updateOrCreate(
            ['user_id' => $this->user->id, 'summary_date' => $this->date],
            [
                'summary_text' => $result['summary'],
                'generated_by' => 'ai',
                'tokens_used' => $result['tokens_used'] ?? 0,
            ]
        );

        Log::info("📝 Daily summary saved for user {$this->user->id} on {$this->date}");
    }
}