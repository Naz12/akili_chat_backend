<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->comment('Unique string sent to gateway');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2)->comment('Amount requested');
            $table->string('currency', 3)->comment('ETB, USD, etc.');
            $table->enum('provider', ['stripe', 'chapa'])->comment('Payment provider');
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_id')->nullable()->comment('Gateway transaction ID');
            $table->json('metadata')->nullable()->comment('Store plan_id, subscription_id, etc.');
            $table->text('gateway_response')->nullable()->comment('Full response from gateway for debugging');
            $table->timestamps();

            $table->index('user_id');
            $table->index('reference');
            $table->index('status');
            $table->index(['provider', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
