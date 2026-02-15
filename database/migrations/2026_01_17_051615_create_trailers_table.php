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
        Schema::create('trailers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            // Trailer Information - Required
            $table->string('unit_no')->unique();
            $table->string('vin', 17)->unique();
            $table->year('year');
            $table->string('make');
            $table->string('model');
            $table->foreignId('equipment_types_id')->constrained('equipment_types')->onDelete('cascade');
            $table->string('owned_by')->nullable();

            // Trailer Details - Optional
            $table->string('color')->nullable();
            $table->string('title_no')->nullable();
            $table->string('tire_size')->nullable();
            $table->integer('gvw')->nullable()->comment('Gross Vehicle Weight in lbs');
            $table->foreignId('vehicle_group_id')->nullable()->constrained('vehicle_groups')->onDelete('cascade');
            // Notes
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('unit_no');
            $table->index('vin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trailers');
    }
};
