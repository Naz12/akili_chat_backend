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
        // Note: MySQL doesn't support partial unique indexes directly
        // We'll use a unique index on (user_id, is_active) and handle the constraint in application logic
        // For databases that support it (PostgreSQL), we could use: unique(['user_id'])->where('is_active', true)
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add composite index for faster lookups
            $table->index(['user_id', 'is_active'], 'subscriptions_user_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_user_active_index');
        });
    }
};
