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
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->unique(); // the driver's own login

            // Personal Information
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('ssn')->nullable(); // encrypted cast
            $table->string('main_phone')->nullable();
            $table->string('alt_phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('photo_file_id')->nullable();

            // Business Information
            $table->string('business_name')->nullable();
            $table->string('employer_identification_number')->nullable();
            $table->enum('federal_tax_classification', [
                'individual_sole_proprietor',
                'c_corporation',
                's_corporation',
                'llc',
            ])->nullable();

            // Address Information
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->char('state_code', 2)->nullable();
            $table->char('country_code', 2)->default('US');
            $table->string('postal_code')->nullable();
            $table->boolean('twic_card')->default(false);
            $table->boolean('passport')->default(false);
            $table->boolean('hazmat')->default(false);
            $table->boolean('lcv_certificate')->default(false);

            // Pay classification (Phase 4)
            $table->enum('worker_type', ['employee', 'contractor'])->default('employee');
            $table->boolean('overtime_exempt')->default(false);
            $table->enum('overtime_exempt_basis', ['motor_carrier_act', 'other'])->nullable();

            // Application and employment are separate lifecycles (F15).
            // application_status is null for drivers created directly by staff.
            $table->enum('application_status', ['draft', 'pending', 'submitted', 'under_review', 'approved', 'rejected', 'withdrawn'])->nullable();
            $table->enum('employment_status', ['applicant', 'active', 'inactive', 'terminated'])->default('applicant');
            $table->enum('source', ['admin', 'public_application'])->default('admin');
            $table->timestamp('withdrawn_at')->nullable();
            $table->enum('rejection_reason', [
                'not_good_fit',
                'failed_drug_test',
                'background_check_issues',
                'cdl_issues',
                'mvr_issues',
                'psp_issues',
                'other',
            ])->nullable();
            $table->text('rejection_notes')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('hired_at')->nullable(); // when the hire was recorded
            $table->date('hire_date')->nullable(); // employment start
            $table->timestamp('terminated_at')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('termination_reason', 500)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'email']);
            $table->index(['company_id', 'employment_status']);
            $table->index(['company_id', 'application_status']);
            $table->index(['company_id', 'main_phone']);
            $table->index(['company_id', 'last_name', 'first_name']);
            $table->foreign(['company_id', 'user_id'])->references(['company_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['company_id', 'photo_file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
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
