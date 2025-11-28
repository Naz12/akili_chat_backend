<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('workflow_type'); // 'youtube', 'document', 'youtube_doc_compare', etc.
            $table->string('intent')->nullable(); // classified intent
            $table->decimal('duration', 8, 2); // seconds
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost_estimate', 10, 4)->default(0); // USD
            $table->json('services_used'); // ['transcriber', 'doc-service', 'openai']
            $table->boolean('cache_hit')->default(false);
            $table->json('trace')->nullable(); // full execution trace
            $table->timestamps();
            
            $table->index(['user_id', 'workflow_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_analytics');
    }
};
