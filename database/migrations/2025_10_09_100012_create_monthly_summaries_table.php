<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->longText('summary_text');
            $table->string('generated_by', 55);
            $table->integer('tokens_used');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->unique(['user_id', 'month', 'year'], 'monthly_summaries_user_id_month_year_unique');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_summaries');
    }
};