<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('web_push_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('endpoint', 500);
            $table->string('p256dh_key', 255);
            $table->string('auth_key', 255);
            $table->boolean('active')->default(true);
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            
            $table->index('user_id');
            $table->index('endpoint');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_push_subscriptions');
    }
};
