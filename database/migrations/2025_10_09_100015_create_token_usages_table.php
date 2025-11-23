<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('token_usages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('engine_id');
            $table->text('prompt');
            $table->longText('response');
            $table->integer('tokens_used');
            $table->decimal('cost', 10, 6)->default(0.000000);
            $table->string('source', 255);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('user_id', 'token_usages_user_id_foreign');
            $table->index('subscription_id', 'token_usages_subscription_id_foreign');
            $table->index('engine_id', 'token_usages_engine_id_foreign');
            $table->foreign(['engine_id'])->references(['id'])->on('ai_engines')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_usages');
    }
};