<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('key', 255);
            $table->text('description');
            $table->boolean('is_enabled');
            $table->longText('config');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->unique('key', 'payment_methods_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};