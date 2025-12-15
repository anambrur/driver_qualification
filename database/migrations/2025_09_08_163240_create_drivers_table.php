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
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected'])->default('draft');


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
