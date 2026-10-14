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
        // One row per real violation; "no violations" is driver_documents.has_violations (F19c).
        // The signature lives once, in driver_documents (F13b).
        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->date('violation_date')->nullable();
            $table->string('violation_location')->nullable();
            $table->string('offense')->nullable();
            $table->string('vehicle_type')->nullable(); // applicant's own wording
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violations');
    }
};
