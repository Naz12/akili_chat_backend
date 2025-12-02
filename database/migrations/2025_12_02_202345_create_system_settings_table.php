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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category')->index(); // payment, subscription, usage, guest, cache, dashboard, audit
            $table->string('label'); // Human-readable label
            $table->text('description')->nullable(); // Help text
            $table->string('type')->default('string'); // string, integer, float, boolean, json
            $table->text('value')->nullable(); // The actual value
            $table->text('default_value')->nullable(); // Default value
            $table->string('unit')->nullable(); // days, hours, minutes, %, etc.
            $table->integer('min_value')->nullable(); // For numeric values
            $table->integer('max_value')->nullable(); // For numeric values
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
