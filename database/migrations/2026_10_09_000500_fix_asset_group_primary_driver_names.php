<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The asset-group form used to post the selected driver's id as primary_driver_name, so those
 * rows hold e.g. "42" instead of a name. Replace that id with the driver's name. Only rows whose
 * primary_driver_name is exactly their own driver_id are touched; the id itself stays in driver_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('asset_groups')
            ->join('drivers', 'drivers.id', '=', 'asset_groups.driver_id')
            ->select('asset_groups.id', 'asset_groups.driver_id', 'asset_groups.primary_driver_name', 'drivers.first_name', 'drivers.last_name')
            ->orderBy('asset_groups.id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    if (trim((string) $row->primary_driver_name) !== (string) $row->driver_id) {
                        continue;
                    }

                    DB::table('asset_groups')->where('id', $row->id)->update([
                        'primary_driver_name' => trim($row->first_name.' '.$row->last_name),
                    ]);
                }
            });
    }

    /**
     * Nothing to undo: the replaced value was the driver id, which driver_id still holds.
     */
    public function down(): void
    {
        //
    }
};
