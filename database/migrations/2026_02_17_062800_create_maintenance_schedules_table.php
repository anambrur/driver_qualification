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
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->foreignId('maintenance_category_id')->constrained()->restrictOnDelete();

            // Basic Information
            $table->string('title')->nullable();
            $table->enum('schedule_type', ['date', 'mileage', 'engine_hours'])->default('date');

            // Schedule settings based on type
            $table->integer('interval_days')->nullable(); // For date-based schedules
            $table->integer('interval_miles')->nullable(); // For mileage-based schedules
            $table->integer('interval_hours')->nullable(); // For engine hours-based schedules

            // Last completed service and next due (F14)
            $table->date('last_service_date')->nullable();
            $table->integer('last_service_mileage')->nullable();
            $table->integer('last_service_hours')->nullable();

            $table->date('next_due_date')->nullable();
            $table->integer('next_due_mileage')->nullable();
            $table->integer('next_due_hours')->nullable();

            // Additional Details
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['active', 'paused', 'completed'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'status', 'next_due_date']);
            $table->foreign(['company_id', 'vehicle_id'])->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
