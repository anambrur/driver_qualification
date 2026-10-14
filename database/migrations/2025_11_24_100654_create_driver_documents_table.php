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
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->unique()->constrained()->cascadeOnDelete();

            // Document uploads
            $table->foreignId('license_front_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('license_back_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('medical_card_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('forfeiture_document_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();

            // Declarations: accident and violation rows exist only for real events (F19c)
            $table->boolean('has_accidents')->nullable();
            $table->boolean('has_violations')->nullable();

            // Violation Record Section
            $table->string('violation_record_signature')->nullable();
            $table->date('violation_record_date_signed')->nullable();

            // Alcohol & Drug Test Section
            $table->enum('drug_test_question_1', ['yes', 'no'])->nullable();
            $table->enum('drug_test_question_2', ['yes', 'no', 'n/a'])->nullable();
            $table->string('drug_test_signature')->nullable();
            $table->date('drug_test_date_signed')->nullable();

            // PSP Authorization fields
            $table->boolean('psp_authorization')->default(false);
            $table->dateTime('psp_authorization_date')->nullable();
            $table->string('psp_authorization_signature')->nullable();
            $table->boolean('psp_authorization_agreement')->default(false);

            // FMCSA Clearinghouse Consent
            $table->boolean('fmcsa_consent')->default(false);
            $table->dateTime('fmcsa_consent_date')->nullable();
            $table->string('fmcsa_consent_signature')->nullable();
            $table->boolean('fmcsa_consent_agreement')->default(false);
            $table->date('fmcsa_date_signed')->nullable();

            // Alcohol & Drug Test Policy Agreement
            $table->string('alcohol_drug_test_policy_signature')->nullable();
            $table->date('alcohol_drug_test_policy_date')->nullable();

            // General Work Policy Agreement
            $table->string('general_work_policy_signature')->nullable();
            $table->date('general_work_policy_date')->nullable();

            // General signature fields (for all documents)
            $table->string('applicant_signature')->nullable();
            $table->date('date_signed')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};
