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
        // The default pairing of a driver with equipment. Every reference is a same-company
        // composite FK with RESTRICT: they are also bases of generated columns or a CHECK.
        Schema::create('asset_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('group_name');
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('co_driver_id')->nullable(); // team driving is still PENDING (14 §3)
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('trailer_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            // One active group per vehicle and per trailer (F7).
            $table->unsignedBigInteger('active_vehicle_id')->nullable()
                ->storedAs("CASE WHEN status = 'active' AND deleted_at IS NULL THEN vehicle_id END")->unique();
            $table->unsignedBigInteger('active_trailer_id')->nullable()
                ->storedAs("CASE WHEN status = 'active' AND deleted_at IS NULL THEN trailer_id END")->unique();

            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'group_name']);
            $table->index(['company_id', 'status']);
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['company_id', 'co_driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['company_id', 'vehicle_id'])->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
            $table->foreign(['company_id', 'trailer_id'])->references(['company_id', 'id'])->on('trailers')->restrictOnDelete();
        });

        CheckConstraints::add('asset_groups', [
            'asset_groups_co_driver_check' => 'co_driver_id IS NULL OR co_driver_id <> driver_id',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_groups');
    }
};
