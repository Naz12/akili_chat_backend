<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;

class UpgradeUserToPlan extends Command
{
    protected $signature = 'user:upgrade-plan 
                            {email : User email (e.g. snazrawi@gmail.com)} 
                            {plan : Plan name or slug (e.g. gold, Gold)} 
                            {--months=12 : Subscription duration in months}';

    protected $description = 'Upgrade a user to a plan by email and plan name (for testing or manual grants)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $planName = $this->argument('plan');
        $months = (int) $this->option('months');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User not found: {$email}");
            return self::FAILURE;
        }

        $plan = Plan::where('is_active', true)
            ->where(function ($q) use ($planName) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($planName)])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($planName) . '%']);
            })
            ->first();

        if (! $plan) {
            $this->error("Plan not found: {$planName}. Available plans: ");
            Plan::where('is_active', true)->get(['id', 'name'])->each(fn ($p) => $this->line("  - {$p->name} (id: {$p->id})"));
            return self::FAILURE;
        }

        // Deactivate any current active subscriptions for this user
        $deactivated = Subscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'end_date' => now()]);

        if ($deactivated > 0) {
            $this->info("Deactivated {$deactivated} existing active subscription(s).");
        }

        $startDate = now();
        $endDate = now()->addMonths($months);
        $txRef = 'test-upgrade-' . $user->id . '-' . time();

        Subscription::create([
            'user_id'     => $user->id,
            'plan_id'     => $plan->id,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'tokens_used' => 0,
            'is_active'   => true,
            'auto_renew'  => false,
            'tx_ref'      => $txRef,
            'metadata'    => [
                'source' => 'artisan:user:upgrade-plan',
                'granted_at' => now()->toIso8601String(),
            ],
        ]);

        $this->info("Upgraded {$user->email} (id: {$user->id}) to plan: {$plan->name} (id: {$plan->id}) until {$endDate->toDateString()}.");
        return self::SUCCESS;
    }
}
