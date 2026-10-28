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
        // 08 §9 (req 7.1–7.6): events recorded during a trip, with the accident-register columns
        // (C9). Evidence records: RESTRICT everywhere, voided instead of deleted.
        Schema::create('trip_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trip_id');
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->enum('type', ['stop', 'police_stop', 'weigh_station', 'accident', 'breakdown', 'fuel']);
            $table->timestamp('occurred_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state_code', 2)->nullable();
            $table->text('reason')->nullable();
            $table->boolean('ticket_issued')->nullable();
            $table->boolean('weight_ok')->nullable();
            $table->json('details')->nullable();
            $table->unsignedSmallInteger('injuries_count')->nullable();
            $table->unsignedSmallInteger('fatalities_count')->nullable();
            $table->boolean('hazmat_released')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'trip_id', 'occurred_at']);
            $table->index(['company_id', 'type', 'occurred_at']);
            $table->foreign(['company_id', 'trip_id'])->references(['company_id', 'id'])->on('trips')->restrictOnDelete();
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['company_id', 'vehicle_id'])->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
        });

        CheckConstraints::add('trip_events', [
            'trip_events_police_stop_check' => "type <> 'police_stop' OR (ticket_issued IS NOT NULL AND (ticket_issued = 1 OR reason IS NOT NULL))",
            'trip_events_weigh_station_check' => "type <> 'weigh_station' OR weight_ok IS NOT NULL",
            'trip_events_stop_reason_check' => "type <> 'stop' OR reason IS NOT NULL",
            'trip_events_accident_register_check' => "type <> 'accident' OR (injuries_count IS NOT NULL AND fatalities_count IS NOT NULL AND hazmat_released IS NOT NULL)",
            'trip_events_accident_only_check' => "type = 'accident' OR (injuries_count IS NULL AND fatalities_count IS NULL AND hazmat_released IS NULL)",
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_events');
    }
};
