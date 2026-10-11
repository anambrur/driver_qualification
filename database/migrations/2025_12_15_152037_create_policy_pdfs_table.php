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
        Schema::create('policy_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->unique()->constrained('companies')->cascadeOnDelete();
            $table->string('alcohol_drug_test_policy_pdf')->nullable();
            $table->string('general_work_policy_pdf')->nullable();
            $table->timestamps();
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
