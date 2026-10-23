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

            // Unit no and VIN stay reserved by retired assets (PO-10), except one transferred out
            // (04 §7): it keeps its real identifiers as history but no longer holds them, so the
            // asset can be transferred back or added again.
            $table->timestamp('transferred_out_at')->nullable();
            $table->string('held_unit_no')->nullable()->storedAs('CASE WHEN transferred_out_at IS NULL THEN unit_no END');
            $table->string('held_vin', 17)->nullable()->storedAs('CASE WHEN transferred_out_at IS NULL THEN vin END');

            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'held_unit_no']);
            $table->unique(['company_id', 'held_vin']);
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
