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
        // One row per company. The platform defaults live in site_settings (F12, PO-14).
        Schema::create('policy_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('alcohol_drug_test_policy_file_id')->nullable();
            $table->unsignedBigInteger('general_work_policy_file_id')->nullable();
            $table->timestamps();

            $table->foreign(['company_id', 'alcohol_drug_test_policy_file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
            $table->foreign(['company_id', 'general_work_policy_file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_pdfs');
    }
};
