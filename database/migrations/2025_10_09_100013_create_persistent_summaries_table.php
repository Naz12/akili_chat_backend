<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('persistent_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('topic', 255);
            $table->longText('summary_type');
            $table->longText('summary_text');
            $table->integer('tokens_used');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('user_id', 'persistent_summaries_user_id_foreign');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persistent_summaries');
    }
};