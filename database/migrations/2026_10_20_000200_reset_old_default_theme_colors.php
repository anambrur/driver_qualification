<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The Appearance form pre-filled the old defaults, so saving it stored them; null them so the new defaults apply.
        // A colour the admin picked themselves stays.
        DB::table('site_settings')->whereRaw('LOWER(primary_color) = ?', ['#465fff'])->update(['primary_color' => null]);
        DB::table('site_settings')->whereRaw('LOWER(accent_color) = ?', ['#0ba5ec'])->update(['accent_color' => null]);

        Cache::forget('site_settings');
    }
};
