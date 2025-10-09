<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_resets_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 255);
            $table->string('code', 255);
            $table->string('status', 255);
            $table->string('ip', 255);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets_log');
    }
};