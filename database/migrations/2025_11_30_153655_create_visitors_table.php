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
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique(); // Unique session identifier
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // If logged in
            $table->string('ip_address', 45)->nullable(); // IPv6 support
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->string('browser')->nullable(); // Chrome, Firefox, Safari, etc.
            $table->string('browser_version')->nullable();
            $table->string('os')->nullable(); // Windows, macOS, Linux, iOS, Android
            $table->string('os_version')->nullable();
            $table->string('country')->nullable(); // Country code (US, ET, etc.)
            $table->string('country_name')->nullable(); // Full country name
            $table->string('region')->nullable(); // State/Province
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('timezone')->nullable();
            $table->string('language')->nullable(); // Browser language
            $table->string('referrer')->nullable(); // HTTP referrer
            $table->string('utm_source')->nullable(); // UTM parameters
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('screen_resolution')->nullable(); // e.g., "1920x1080"
            $table->boolean('is_mobile')->default(false);
            $table->boolean('is_tablet')->default(false);
            $table->boolean('is_desktop')->default(false);
            $table->boolean('is_bot')->default(false); // Detect bots/crawlers
            $table->boolean('is_logged_in')->default(false);
            $table->integer('page_views')->default(1); // Track page views per session
            $table->integer('session_duration')->default(0); // In seconds
            $table->timestamp('first_visit_at'); // First visit timestamp
            $table->timestamp('last_visit_at'); // Last visit timestamp
            $table->timestamp('last_activity_at')->nullable(); // Last activity timestamp
            $table->json('metadata')->nullable(); // Additional custom data
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('country');
            $table->index('is_logged_in');
            $table->index('first_visit_at');
            $table->index('last_visit_at');
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
