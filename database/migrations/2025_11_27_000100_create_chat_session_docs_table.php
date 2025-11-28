<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_session_docs', function (Blueprint $table) {
            $table->id();
            $table->uuid('chat_session_id');
            $table->string('doc_id');
            $table->string('provider')->default('doc-service');
            $table->timestamps();
            $table->unique(['chat_session_id', 'doc_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_session_docs');
    }
};
