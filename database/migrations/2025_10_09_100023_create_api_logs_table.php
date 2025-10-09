<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('method', 255);
            $table->string('endpoint', 255);
            $table->text('request');
            $table->text('response');
            $table->integer('tokens_used');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};