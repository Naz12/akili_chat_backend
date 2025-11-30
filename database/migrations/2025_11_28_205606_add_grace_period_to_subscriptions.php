<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('grace_period_ends_at')->nullable()->after('end_date');
            $table->unsignedInteger('payment_failure_count')->default(0)->after('grace_period_ends_at');
            $table->index('grace_period_ends_at', 'subscriptions_grace_period_ends_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_grace_period_ends_at_index');
            $table->dropColumn(['grace_period_ends_at', 'payment_failure_count']);
        });
    }
};
