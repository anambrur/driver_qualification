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
        Schema::create('employment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->string('employer_name')->nullable();
            $table->string('employer_record_address')->nullable();
            $table->string('employer_record_city')->nullable();
            $table->string('employer_record_country')->nullable();
            $table->string('employer_record_state')->nullable();
            $table->string('employer_record_postal_code')->nullable();
            $table->string('employer_record_phone')->nullable();
            $table->string('employer_record_fax')->nullable();
            $table->string('employer_record_email')->nullable();
            $table->string('employer_record_position')->nullable();
            $table->date('employer_record_date_from')->nullable();
            $table->date('employer_record_date_to')->nullable();
            $table->text('employer_record_reason_for_leaving')->nullable();
            $table->enum('employed_regulations', ['no', 'yes'])->default('no');
            $table->enum('safety_sensitive_function', ['no', 'yes'])->default('no');
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
