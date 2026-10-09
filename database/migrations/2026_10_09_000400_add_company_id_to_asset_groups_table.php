<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asset groups get their own tenant column instead of inheriting the company of their vehicle.
 * Existing rows are backfilled from vehicles.company_id (vehicle_id is a required FK, so every
 * row gets a value). ON DELETE CASCADE matches vehicles.company_id: deleting a company already
 * removed its vehicles and, through them, its asset groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_groups', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
        });

        DB::table('asset_groups')->update([
            'company_id' => DB::raw('(SELECT vehicles.company_id FROM vehicles WHERE vehicles.id = asset_groups.vehicle_id)'),
        ]);

        Schema::table('asset_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->index(['company_id', 'group_name']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_groups', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'group_name']);
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
