<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('chat_session_id', 36);
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ["user", "assistant"]);
            $table->text('content');
            $table->boolean('is_attachment');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('user_id', 'chat_messages_user_id_foreign');
            $table->index('chat_session_id', 'chat_messages_chat_id_index');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};