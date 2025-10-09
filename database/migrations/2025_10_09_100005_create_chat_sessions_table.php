<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->char('id', 36);
            $table->unsignedBigInteger('user_id');
            $table->string('title', 255);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->primary(['id']);
            $table->index('user_id', 'chat_sessions_user_id_foreign');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};