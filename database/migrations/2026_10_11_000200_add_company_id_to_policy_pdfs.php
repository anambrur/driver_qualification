<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PO-14: each company may upload its own policy PDFs. A row with company_id null is the
 * platform set every company falls back to; the existing row(s) keep company_id null and so
 * stay the platform default. No data is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('policy_pdfs', 'company_id')) {
            return;
        }

        Schema::table('policy_pdfs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('policy_pdfs', 'company_id')) {
            return;
        }

        Schema::table('policy_pdfs', function (Blueprint $table) {
            $table->dropUnique(['company_id']);
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
