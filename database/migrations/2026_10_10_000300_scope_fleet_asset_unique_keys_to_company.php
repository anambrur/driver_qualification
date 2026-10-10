<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FLT-02: unit_no and vin were unique across every company, so one tenant's unit "101" blocked
 * every other tenant's, and the validation error told them it existed. They are now unique per
 * company. The composite indexes are added before the old ones are dropped; existing rows can't
 * conflict because the old indexes were stricter.
 *
 * down() restores the global indexes and fails if two companies now share a unit no or VIN.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['vehicles', 'trailers'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(['company_id', 'unit_no']);
                $table->unique(['company_id', 'vin']);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['unit_no']);
                $table->dropUnique(['vin']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique('unit_no');
                $table->unique('vin');
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['company_id', 'unit_no']);
                $table->dropUnique(['company_id', 'vin']);
            });
        }
    }
};
