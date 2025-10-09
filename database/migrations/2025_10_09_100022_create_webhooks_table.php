<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider', 255);
            $table->string('event_type', 255);
            $table->text('signature');
            $table->longText('payload');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};