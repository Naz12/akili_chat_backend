<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('image_url', 255);
            $table->text('description');
            $table->string('tag', 20);
            $table->integer('monthly_price');
            $table->unsignedInteger('trial_days');
            $table->integer('max_tokens');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->unsignedInteger('daily_message_limit')->default(20);
            $table->boolean('ads_enabled');
            $table->boolean('is_active');
            $table->boolean('is_default');
            $table->unsignedBigInteger('engine_id');
            $table->enum('region', ["local", "intl", "local"]);
            $table->string('currency', 255);
            $table->index('engine_id', 'plans_engine_id_foreign');
            $table->foreign(['engine_id'])->references(['id'])->on('ai_engines')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};