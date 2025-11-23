<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('token_adjustments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('admin_id');
            $table->unsignedInteger('old_tokens');
            $table->unsignedInteger('new_tokens');
            $table->string('reason', 255);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->index('subscription_id', 'token_adjustments_subscription_id_foreign');
            $table->index('admin_id', 'token_adjustments_admin_id_foreign');
            $table->foreign(['admin_id'])->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_adjustments');
    }
};