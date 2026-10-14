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
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            // Trailer Information - Required
            $table->string('unit_no');
            $table->string('vin', 17);
            $table->year('year');
            $table->string('make');
            $table->string('model');
            $table->foreignId('equipment_type_id')->constrained('equipment_types')->restrictOnDelete();
            $table->string('owned_by')->nullable();
            $table->string('plate_number', 20)->nullable();
            $table->char('plate_state_code', 2)->nullable();
            $table->unsignedBigInteger('photo_file_id')->nullable();

            // Trailer Details - Optional
            $table->string('color')->nullable();
            $table->string('title_no')->nullable();
            $table->string('tire_size')->nullable();
            $table->integer('gvw')->nullable()->comment('Gross Vehicle Weight in lbs');
            $table->foreignId('vehicle_group_id')->nullable()->constrained('vehicle_groups')->restrictOnDelete();
            // Notes
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'unit_no']);
            $table->unique(['company_id', 'vin']);
            $table->foreign(['company_id', 'photo_file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
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
