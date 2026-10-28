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
        // 08 §9 (req 7.6): a fuel stop's purchase, written with its fuel trip event. "Done" is
        // confirmed_at, set once a receipt is uploaded.
        Schema::create('fuel_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->unsignedBigInteger('trip_event_id')->nullable()->unique();
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->foreignId('fuel_type_id')->nullable()->constrained('fuel_types')->restrictOnDelete();
            $table->timestamp('purchased_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_name')->nullable();
            $table->char('state_code', 2)->nullable();
            $table->decimal('gallons', 10, 3);
            $table->decimal('price_per_gallon', 8, 3);
            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('odometer')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'purchased_at']);
            $table->index(['company_id', 'vehicle_id', 'purchased_at']);
            $table->foreign(['company_id', 'trip_id'])->references(['company_id', 'id'])->on('trips')->restrictOnDelete();
            $table->foreign(['company_id', 'trip_event_id'])->references(['company_id', 'id'])->on('trip_events')->restrictOnDelete();
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['company_id', 'vehicle_id'])->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_purchases');
    }
};
