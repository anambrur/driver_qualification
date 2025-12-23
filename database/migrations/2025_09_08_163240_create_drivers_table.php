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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // Personal Information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->date('date_of_birth');
            $table->string('ssn'); // Encrypted in real application
            $table->string('main_phone');
            $table->string('alt_phone')->nullable();
            $table->string('email');
            $table->date('medical_certificate_expiration_date')->nullable();
            $table->text('photo')->nullable();

            // Business Information
            $table->string('business_name')->nullable();
            $table->string('employer_identification_number')->nullable();
            $table->enum('federal_tax_classification', [
                'individual_sole_proprietor',
                'c_corporation',
                's_corporation',
                'llc'
            ])->nullable();

            // Address Information
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('country')->default('US');
            $table->string('postal_code');
            $table->boolean('twic_card')->default(false);
            $table->boolean('passport')->default(false);


            // Status
            $table->enum('status', ['draft', 'active', 'inactive', 'pending', 'submitted', 'under_review', 'approved', 'rejected'])->default('draft');


            $table->enum('hazmat', ['yes', 'no'])->nullable();
            $table->enum('lcv_certificate', ['yes', 'no'])->nullable();

            // Add fields for rejection details
            $table->enum('rejection_reason', [
                'not_good_fit',
                'failed_drug_test',
                'background_check_issues',
                'cdl_issues',
                'mvr_issues',
                'psp_issues',
                'other'
            ])->nullable();
            $table->text('rejection_notes')->nullable();
            $table->date('rejection_date')->nullable();

            // Optional: Add timestamps for hire actions
            $table->timestamp('hired_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('action_by')->nullable()->constrained('users')->after('rejected_at');

            $table->timestamps();

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
