<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('persona_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('key', 255);
            $table->text('previous_value');
            $table->text('new_value');
            $table->string('changed_by', 255);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('user_id', 'persona_history_user_id_foreign');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_history');
    }
};