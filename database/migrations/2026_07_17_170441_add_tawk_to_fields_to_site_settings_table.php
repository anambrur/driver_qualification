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
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('tawk_enabled')->default(false)->after('google_analytics_id');
            $table->string('tawk_property_id')->nullable()->after('tawk_enabled');
            $table->string('tawk_widget_id')->nullable()->after('tawk_property_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['tawk_enabled', 'tawk_property_id', 'tawk_widget_id']);
        });
    }
};
