<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('region', 255);
            $table->timestamp('email_verified_at');
            $table->string('password', 255);
            $table->string('remember_token', 100);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->string('role', 255);
            $table->string('fcm_token', 255);
            $table->unsignedBigInteger('country_id');
            $table->tinyInteger('is_suspended');
            $table->string('password_reset_code', 255);
            $table->unique('email', 'users_email_unique');
            $table->index('country_id', 'users_country_id_foreign');
            $table->foreign(['country_id'])->references(['id'])->on('countries')->onDelete('set');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};