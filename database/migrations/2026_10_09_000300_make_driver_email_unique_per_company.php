<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A driver's email is unique within a company, not across the platform, so someone who
 * applied to (or works for) company A can still apply to company B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['company_id', 'email']);
        });
    }

    /**
     * Fails if two companies now share a driver email; resolve those rows before rolling back.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'email']);
            $table->unique(['email']);
        });
    }
};
