<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->char('id', 36);
            $table->string('type', 255);
            $table->string('notifiable_type', 255);
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->primary(['id']);
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};