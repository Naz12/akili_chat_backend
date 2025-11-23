<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('allow_marketing_email');
            $table->boolean('allow_push_notifications');
            $table->boolean('allow_sms');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('user_id', 'preferences_user_id_foreign');
            $table->foreign(['user_id'])->references(['id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferences');
    }
};