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
        // Replies to outgoing mail go to the owner's own inbox; pending waits for its verification link
        Schema::table('companies', function (Blueprint $table) {
            $table->string('reply_to_email')->nullable();
            $table->string('pending_reply_to_email')->nullable();
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('reply_to_email')->nullable();
            $table->string('pending_reply_to_email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['reply_to_email', 'pending_reply_to_email']);
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['reply_to_email', 'pending_reply_to_email']);
        });
    }
};
