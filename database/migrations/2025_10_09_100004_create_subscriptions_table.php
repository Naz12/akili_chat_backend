<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('tokens_used');
            $table->boolean('is_active');
            $table->boolean('auto_renew');
            $table->string('tx_ref', 255);
            $table->longText('metadata');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('user_id', 'subscriptions_user_id_foreign');
            $table->index('plan_id', 'subscriptions_plan_id_foreign');
            $table->foreign(['plan_id'])->references(['id'])->on('plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};