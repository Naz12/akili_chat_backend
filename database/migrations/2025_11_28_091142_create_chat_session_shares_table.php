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
        Schema::create('chat_session_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shared_by_user_id');
            $table->unsignedBigInteger('shared_to_user_id');
            $table->char('original_chat_session_id', 36);
            $table->char('duplicated_chat_session_id', 36)->nullable();
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            
            // Foreign keys
            $table->foreign('shared_by_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('shared_to_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('original_chat_session_id')->references('id')->on('chat_sessions')->onDelete('cascade');
            $table->foreign('duplicated_chat_session_id')->references('id')->on('chat_sessions')->onDelete('set null');
            
            // Indexes
            $table->index('shared_to_user_id', 'chat_session_shares_shared_to_user_id_index');
            $table->index('status', 'chat_session_shares_status_index');
            $table->index('original_chat_session_id', 'chat_session_shares_original_session_id_index');
            
            // Prevent duplicate pending shares (but allow new share if previous was declined)
            $table->unique(['original_chat_session_id', 'shared_to_user_id', 'status'], 'chat_session_shares_unique_pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_session_shares');
    }
};
