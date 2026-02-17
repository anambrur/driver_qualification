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
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('maintenance_category_id')->constrained('maintenance_categories')->onDelete('cascade');

            // Basic Information
            $table->string('title')->nullable();
            $table->enum('schedule_type', ['date', 'mileage', 'engine_hours'])->default('date');

            // Schedule settings based on type
            $table->integer('interval_days')->nullable(); // For date-based schedules
            $table->integer('interval_miles')->nullable(); // For mileage-based schedules
            $table->integer('interval_hours')->nullable(); // For engine hours-based schedules

            // Last and next due tracking
            $table->date('last_due_date')->nullable();
            $table->integer('last_due_mileage')->nullable();
            $table->integer('last_due_hours')->nullable();

            $table->date('next_due_date')->nullable();
            $table->integer('next_due_mileage')->nullable();
            $table->integer('next_due_hours')->nullable();

            // Additional Details
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('vehicle_id');
            $table->index('maintenance_category_id');
            $table->index('schedule_type');
            $table->index('status');
            $table->index('next_due_date');
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
