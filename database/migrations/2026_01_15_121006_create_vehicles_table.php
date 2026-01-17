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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            // Vehicle Information - Required
            $table->string('unit_no')->unique();
            $table->string('vin', 17)->unique();
            $table->year('year');
            $table->string('make');
            $table->string('model');
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->onDelete('cascade');
            $table->string('owned_by');

            // Vehicle Details - Optional
            $table->string('color')->nullable();
            $table->string('title_no')->nullable();
            $table->string('tire_size')->nullable();
            $table->bigInteger('odometer')->default(0);
            $table->integer('gvw')->nullable()->comment('Gross Vehicle Weight in lbs');
            $table->foreignId('vehicle_group_id')->nullable()->constrained('vehicle_groups')->onDelete('cascade');

            // Engine & Drivetrain - Optional
            $table->foreignId('fuel_type_id')->nullable()->constrained('fuel_types')->onDelete('cascade');
            $table->string('engine_type')->nullable();
            $table->string('transmission')->nullable();
            $table->string('suspension')->nullable();
            $table->integer('no_axles')->nullable();
            $table->enum('configuration', ['conventional', 'cabover'])->nullable();
            $table->integer('wheel_base')->nullable()->comment('In inches');
            $table->string('size_dimension')->nullable();
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
        Schema::dropIfExists('vehicles');
    }
};
