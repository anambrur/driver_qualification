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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('My Application');
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('google_analytics_id')->nullable();

            // Tawk.to live chat
            $table->boolean('tawk_enabled')->default(false);
            $table->string('tawk_property_id')->nullable();
            $table->string('tawk_widget_id')->nullable();
            $table->longText('tawk_widget_code')->nullable();

            // Platform defaults and billing
            $table->string('default_alcohol_drug_test_policy_pdf')->nullable();
            $table->string('default_general_work_policy_pdf')->nullable();
            $table->foreignId('trial_plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->unsignedSmallInteger('grace_days')->default(7);
            $table->unsignedSmallInteger('support_session_max_minutes')->default(240);

            // Platform identity
            $table->string('legal_name')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('terms_url')->nullable();
            $table->string('privacy_url')->nullable();
            $table->string('timezone')->default('America/Chicago');

            // Theme
            $table->string('primary_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->enum('default_color_mode', ['light', 'dark', 'system'])->default('system');
            $table->string('logo_dark')->nullable();
            $table->string('email_logo')->nullable();

            // SEO defaults
            $table->string('og_image')->nullable();
            $table->string('twitter_card')->default('summary_large_image');
            $table->string('canonical_base_url')->nullable();
            $table->boolean('robots_noindex_default')->default(false);
            $table->string('search_console_verification')->nullable();

            // Mail sender
            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
