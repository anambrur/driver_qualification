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
        // employer_* describe an external employer as declared by the applicant (snapshot).
        Schema::create('employment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->string('employer_name')->nullable();
            $table->string('employer_record_address')->nullable();
            $table->string('employer_record_city')->nullable();
            $table->char('employer_record_country_code', 2)->nullable();
            $table->char('employer_record_state_code', 2)->nullable();
            $table->string('employer_record_postal_code')->nullable();
            $table->string('employer_record_phone')->nullable();
            $table->string('employer_record_fax')->nullable();
            $table->string('employer_record_email')->nullable();
            $table->string('employer_record_position')->nullable();
            $table->date('employer_record_date_from')->nullable();
            $table->date('employer_record_date_to')->nullable();
            $table->text('employer_record_reason_for_leaving')->nullable();
            $table->boolean('employed_regulations')->default(false);
            $table->boolean('safety_sensitive_function')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_records');
    }
};
