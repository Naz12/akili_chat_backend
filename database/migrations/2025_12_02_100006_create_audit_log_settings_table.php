<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('retention_days')->default(90);
            $table->boolean('auto_cleanup_enabled')->default(true);
            $table->timestamp('last_cleanup_at')->nullable();
            $table->timestamps();
        });
        
        // Insert default settings
        DB::table('audit_log_settings')->insert([
            'retention_days' => 90,
            'auto_cleanup_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_settings');
    }
};

