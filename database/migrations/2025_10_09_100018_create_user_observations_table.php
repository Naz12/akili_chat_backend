<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_observations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 255);
            $table->string('key', 255);
            $table->text('content');
            $table->double('confidence')->default(0.8);
            $table->timestamp('observed_at');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('user_id', 'user_observations_user_id_foreign');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_observations');
    }
};