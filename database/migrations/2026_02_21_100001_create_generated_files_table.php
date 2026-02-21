<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('path');
            $table->string('filename')->nullable();
            $table->string('type'); // presentation, diagram
            $table->char('chat_session_id', 36)->nullable();
            $table->unsignedBigInteger('chat_message_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('guest_session_id')->nullable();
            $table->timestamps();
            $table->index(['chat_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_files');
    }
};
