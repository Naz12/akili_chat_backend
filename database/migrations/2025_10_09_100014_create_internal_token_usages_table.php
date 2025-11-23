<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internal_token_usages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('engine_id');
            $table->unsignedBigInteger('user_id');
            $table->string('purpose', 255);
            $table->text('input_text');
            $table->text('output_text');
            $table->unsignedInteger('tokens_used');
            $table->decimal('cost', 8, 6)->default(0.000000);
            $table->longText('metadata');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('engine_id', 'internal_token_usages_engine_id_foreign');
            $table->index('user_id', 'internal_token_usages_user_id_foreign');
            $table->foreign(['engine_id'])->references(['id'])->on('ai_engines')->onDelete('set');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_token_usages');
    }
};