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
        // 08 §9 (req 6.1, 6.3): the planned route. One start (also_pickup when it is the pickup),
        // an optional pickup and one drop-off, kept in order by sequence. country_code as on
        // shippers (08 §1 places); the place columns come from the address picker.
        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trip_id');
            $table->unsignedSmallInteger('sequence');
            $table->enum('stop_type', ['start', 'pickup', 'drop_off']);
            $table->boolean('also_pickup')->default(false);
            $table->unsignedBigInteger('shipper_id')->nullable();
            $table->string('address_line');
            $table->string('city', 100);
            $table->char('state_code', 2);
            $table->string('zip', 10);
            $table->char('country_code', 2);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('place_source', 20)->nullable();
            $table->string('place_ref', 64)->nullable();
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'sequence']);
            $table->unique(['trip_id', 'stop_type']);
            $table->index(['company_id', 'trip_id']);
            $table->foreign(['company_id', 'trip_id'])->references(['company_id', 'id'])->on('trips')->cascadeOnDelete();
            $table->foreign(['company_id', 'shipper_id'])->references(['company_id', 'id'])->on('shippers')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
    }
};
