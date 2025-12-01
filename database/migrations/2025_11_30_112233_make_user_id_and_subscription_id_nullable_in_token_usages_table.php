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
        Schema::table('token_usages', function (Blueprint $table) {
            // Make user_id and subscription_id nullable to support guest users
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('token_usages', function (Blueprint $table) {
            // Remove guest records before making columns non-nullable
            \DB::table('token_usages')->whereNull('user_id')->delete();
            
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unsignedBigInteger('subscription_id')->nullable(false)->change();
        });
    }
};
