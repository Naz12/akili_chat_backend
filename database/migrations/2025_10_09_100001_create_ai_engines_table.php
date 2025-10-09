<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_engines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('provider', 255);
            $table->string('api_url', 255);
            $table->text('api_key');
            $table->text('readonly_api_key');
            $table->integer('max_tokens')->default(4096);
            $table->decimal('price_per_1k', 8, 4);
            $table->string('model_type', 255);
            $table->boolean('is_vision_support');
            $table->string('version', 255);
            $table->integer('priority_order');
            $table->boolean('is_fallback');
            $table->boolean('is_active');
            $table->boolean('online');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->timestamp('last_test_success');
            $table->decimal('avg_cost_per_request', 8, 5);
            $table->integer('failure_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_engines');
    }
};