<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 255);
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->longText('payload');
            $table->integer('last_activity');
            $table->primary(['id']);
            $table->index('user_id', 'sessions_user_id_index');
            $table->index('last_activity', 'sessions_last_activity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};