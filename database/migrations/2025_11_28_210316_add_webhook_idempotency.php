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
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('webhook_id', 255)->nullable()->after('provider'); // Unique identifier from provider
            $table->string('event_id', 255)->nullable()->after('webhook_id'); // Event ID from provider
            $table->boolean('processed')->default(false)->after('payload');
            $table->timestamp('processed_at')->nullable()->after('processed');
            
            $table->index(['provider', 'webhook_id'], 'webhooks_provider_webhook_id_index');
            $table->index(['provider', 'event_id'], 'webhooks_provider_event_id_index');
            $table->index('processed', 'webhooks_processed_index');
            
            // Unique constraint on provider + webhook_id to prevent duplicate processing
            $table->unique(['provider', 'webhook_id'], 'webhooks_provider_webhook_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropUnique('webhooks_provider_webhook_id_unique');
            $table->dropIndex('webhooks_processed_index');
            $table->dropIndex('webhooks_provider_event_id_index');
            $table->dropIndex('webhooks_provider_webhook_id_index');
            $table->dropColumn(['webhook_id', 'event_id', 'processed', 'processed_at']);
        });
    }
};
