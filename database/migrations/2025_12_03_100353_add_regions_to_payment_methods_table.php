<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->json('regions')->nullable()->after('is_enabled');
        });
        
        // Set default regions for existing payment methods
        // If is_enabled is true, enable for both regions by default
        DB::table('payment_methods')->where('is_enabled', true)->update([
            'regions' => json_encode(['local', 'intl'])
        ]);
        
        // If is_enabled is false, set empty array
        DB::table('payment_methods')->where('is_enabled', false)->update([
            'regions' => json_encode([])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('regions');
        });
    }
};
