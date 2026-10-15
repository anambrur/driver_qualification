<?php

namespace Database\Seeders;

use App\Features\Feature;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureRegistrySync extends Seeder
{
    /**
     * Upsert the Feature enum into `features` (05 §2). Idempotent and safe in production:
     * availability is set only on insert (the platform owns it afterwards), and a key no longer
     * in the code is disabled, never deleted, because plan history refers to it.
     *
     * php artisan db:seed --class=FeatureRegistrySync
     */
    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (Feature::cases() as $order => $feature) {
            $rows[] = [
                'key' => $feature->value, 'name' => $feature->label(), 'group' => $feature->group(),
                'kind' => $feature->kind(), 'unit' => $feature->unit(), 'availability' => 'general',
                'sort_order' => $order + 1, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        DB::table('features')->upsert($rows, ['key'], ['name', 'group', 'kind', 'unit', 'sort_order', 'updated_at']);

        $removed = DB::table('features')
            ->whereNotIn('key', array_column($rows, 'key'))
            ->where('availability', '!=', 'disabled')
            ->update(['availability' => 'disabled', 'updated_at' => $now]);

        // Availability is resolved for every company: drop all cached entitlements.
        if ($removed > 0) {
            Company::query()->increment('entitlements_version');
            $this->command?->warn("{$removed} feature(s) no longer in the code were disabled.");
        }
    }
}
