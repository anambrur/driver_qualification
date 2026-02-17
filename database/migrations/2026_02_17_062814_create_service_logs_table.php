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
        Schema::create('service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->date('service_date');
            $table->text('maintenance_notes')->nullable();

            // Vehicle Metrics
            $table->bigInteger('odometer_at_service')->default(0);
            $table->bigInteger('current_odometer')->default(0);
            $table->integer('engine_hours_at_service')->default(0)->nullable();
            $table->integer('current_engine_hours')->default(0)->nullable();

            // Service Cost
            $table->decimal('total_cost', 10, 2)->default(0.00);

            // Status tracking
            $table->enum('status', ['completed', 'pending', 'cancelled'])->default('completed');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('vehicle_id');
            $table->index('service_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_logs');
    }
};
