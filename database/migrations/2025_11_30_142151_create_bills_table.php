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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('type')->default('renewal'); // renewal, renewal_failed, upgrade, downgrade
            $table->string('status')->default('pending'); // pending, paid, overdue, cancelled
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->dateTime('due_date');
            $table->dateTime('paid_at')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            
            $table->index(['user_id', 'status']);
            $table->index(['subscription_id']);
            $table->index(['due_date']);
            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
