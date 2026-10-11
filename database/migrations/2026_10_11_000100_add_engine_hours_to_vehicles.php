<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PO-05: a vehicle's engine hours become a column on vehicles, editable on the vehicle form and
 * raised by service logs like the odometer. Until now they were read as the highest
 * current_engine_hours on the vehicle's service logs, so the column is backfilled from that.
 *
 * The backfill only reads service_logs and only fills vehicles whose engine_hours is still
 * empty, so the migration is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vehicles', 'engine_hours')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->unsignedInteger('engine_hours')->nullable()->after('odometer');
            });
        }

        DB::table('vehicles')
            ->whereNull('engine_hours')
            ->update([
                'engine_hours' => DB::table('service_logs')
                    ->selectRaw('MAX(service_logs.current_engine_hours)')
                    ->whereColumn('service_logs.vehicle_id', 'vehicles.id')
                    ->whereNull('service_logs.deleted_at'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicles', 'engine_hours')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('engine_hours');
            });
        }
    }
};
