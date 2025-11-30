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
        Schema::table('token_usages', function (Blueprint $table) {
            $table->char('chat_session_id', 36)->nullable()->after('user_id');
            $table->index('chat_session_id', 'token_usages_chat_session_id_index');
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('token_usages', function (Blueprint $table) {
            $table->dropForeign(['chat_session_id']);
            $table->dropIndex('token_usages_chat_session_id_index');
            $table->dropColumn('chat_session_id');
        });
    }
};
