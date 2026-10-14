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
        // One row per real accident; "no accidents" is driver_documents.has_accidents (F19c).
        Schema::create('accidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->date('accident_date')->nullable();
            $table->string('accident_location')->nullable();
            $table->unsignedSmallInteger('number_of_injuries')->default(0);
            $table->unsignedSmallInteger('number_of_fatalities')->default(0);
            $table->boolean('hazmat_spill')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accidents');
    }
};
