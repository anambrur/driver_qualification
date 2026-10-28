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
        // 08 §9 (req 5.1–5.3): a pre-trip inspection of a trip's driver and equipment, copied
        // from the trip when it starts, on the active template version.
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('trailer_id')->nullable();
            $table->foreignId('template_id')->constrained('inspection_templates')->restrictOnDelete();
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
            $table->enum('result', ['no_defects', 'defects_found'])->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'trip_id']);
            $table->index(['company_id', 'vehicle_id', 'completed_at']);
            $table->foreign(['company_id', 'trip_id'])->references(['company_id', 'id'])->on('trips')->restrictOnDelete();
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['company_id', 'vehicle_id'])->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
            $table->foreign(['company_id', 'trailer_id'])->references(['company_id', 'id'])->on('trailers')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
