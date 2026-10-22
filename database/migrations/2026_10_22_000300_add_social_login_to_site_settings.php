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
        // Social login is a platform capability (05 §2): off until the platform turns it on
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('google_login_enabled')->default(false);
            $table->boolean('facebook_login_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['google_login_enabled', 'facebook_login_enabled']);
        });
    }
};
