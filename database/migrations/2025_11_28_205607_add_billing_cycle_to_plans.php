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
        Schema::table('plans', function (Blueprint $table) {
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'annual'])->default('monthly')->after('monthly_price');
            $table->index('billing_cycle', 'plans_billing_cycle_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex('plans_billing_cycle_index');
            $table->dropColumn('billing_cycle');
        });
    }
};
