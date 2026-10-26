<?php

use App\Support\CheckConstraints;
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
        // 08 §9 (req 4.1–4.3). Driver, vehicle, trailer and shipper are same-company composite FKs
        // with RESTRICT: driver and vehicle are in the CHECK and are bases of the generated
        // one-active columns. The number comes from trip_number_sequences under a row lock.
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trip_number');
            $table->date('trip_date');
            $table->timestamp('number_confirmed_at')->nullable();
            $table->foreignId('number_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'planned', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('trailer_id')->nullable();
            $table->unsignedBigInteger('shipper_id')->nullable();
            $table->string('product', 150)->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('weight_unit', 2)->default('lb');
            $table->decimal('load_pay', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('equipment_confirmed_at')->nullable();
            $table->foreignId('equipment_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('planned_distance_miles', 8, 1)->nullable();
            $table->decimal('actual_distance_miles', 8, 1)->nullable();
            $table->decimal('paid_miles', 8, 1)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->string('vehicle_unit_no_snapshot')->nullable();
            $table->string('trailer_unit_no_snapshot')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One in-progress trip per driver and per vehicle
            $table->unsignedBigInteger('active_driver_id')->nullable()
                ->storedAs("CASE WHEN status = 'in_progress' THEN driver_id END")->unique();
            $table->unsignedBigInteger('active_vehicle_id')->nullable()
                ->storedAs("CASE WHEN status = 'in_progress' THEN vehicle_id END")->unique();

            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'trip_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'driver_id', 'status']);
            $table->index(['company_id', 'trip_date']);
            $table->index(['company_id', 'completed_at']);
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['company_id', 'vehicle_id'])->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
            $table->foreign(['company_id', 'trailer_id'])->references(['company_id', 'id'])->on('trailers')->restrictOnDelete();
            $table->foreign(['company_id', 'shipper_id'])->references(['company_id', 'id'])->on('shippers')->restrictOnDelete();
        });

        CheckConstraints::add('trips', [
            'trips_assigned_has_driver_vehicle_check' => "status NOT IN ('assigned', 'in_progress', 'completed') OR (driver_id IS NOT NULL AND vehicle_id IS NOT NULL)",
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
