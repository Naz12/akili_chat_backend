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
        Schema::table('chat_sessions', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['user_id']);
            
            // Make user_id nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Add guest session support
            $table->unsignedBigInteger('guest_session_id')->nullable()->after('user_id');
            $table->boolean('is_guest')->default(false)->after('guest_session_id');
            
            // Add foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('guest_session_id')->references('id')->on('guest_sessions')->onDelete('cascade');
            
            // Add indexes
            $table->index('guest_session_id', 'chat_sessions_guest_session_id_index');
            $table->index('is_guest', 'chat_sessions_is_guest_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // Drop new foreign keys and indexes
            $table->dropForeign(['guest_session_id']);
            $table->dropIndex('chat_sessions_guest_session_id_index');
            $table->dropIndex('chat_sessions_is_guest_index');
            
            // Drop new columns
            $table->dropColumn(['guest_session_id', 'is_guest']);
            
            // Restore user_id to not nullable
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
