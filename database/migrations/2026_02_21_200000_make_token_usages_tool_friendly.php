<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow token_usages to store tool usage (presentation, diagram, doc_converter)
     * without engine_id, prompt, or response.
     */
    public function up(): void
    {
        Schema::table('token_usages', function (Blueprint $table) {
            $table->dropForeign(['engine_id']);
        });
        Schema::table('token_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('engine_id')->nullable()->change();
            $table->text('prompt')->nullable()->change();
            $table->longText('response')->nullable()->change();
            $table->foreign('engine_id')->references('id')->on('ai_engines')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('token_usages', function (Blueprint $table) {
            $table->dropForeign(['engine_id']);
        });
        Schema::table('token_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('engine_id')->nullable(false)->change();
            $table->text('prompt')->nullable(false)->change();
            $table->longText('response')->nullable(false)->change();
            $table->foreign('engine_id')->references('id')->on('ai_engines')->onDelete('cascade');
        });
    }
};
