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
        Schema::create('asset_groups', function (Blueprint $table) {
            $table->id();
            // Basic Information (from screenshot)
            $table->string('group_name');

            // Primary Driver Information (from screenshot)
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->string('primary_driver_name')->nullable();
            $table->string('primary_driver_phone')->nullable();
            $table->string('primary_driver_email')->nullable();

            // Second Driver Information - Optional (from screenshot)
            $table->string('second_driver_name')->nullable();
            $table->string('second_driver_phone')->nullable();
            $table->string('second_driver_email')->nullable();

            // Vehicle Assignment (from screenshot)
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('trailer_id')->nullable()->constrained('trailers')->onDelete('cascade');

            // Status and metadata
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('group_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_groups');
    }
};
