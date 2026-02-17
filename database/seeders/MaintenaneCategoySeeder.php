<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaintenaneCategoySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $maintenanceCategories = [
            ['name' => '180 Day Inspection'],
            ['name' => '90 Day Inspection'],
            ['name' => 'AC & Heater'],
            ['name' => 'Axles'],
            ['name' => 'Battery'],
            ['name' => 'Brakes'],
            ['name' => 'Coupling Devices'],
            ['name' => 'Coupling Pin'],
            ['name' => 'DOT Annual Inspection'],
            ['name' => 'Doors'],
            ['name' => 'Engine'],
            ['name' => 'Filters'],
            ['name' => 'Lights & Electricals'],
            ['name' => 'Oil Change'],
            ['name' => 'Others'],
            ['name' => 'Reflectors'],
            ['name' => 'Registration Expire'],
            ['name' => 'Roof'],
            ['name' => 'Seals'],
            ['name' => 'Spark Plugs'],
            ['name' => 'Springs'],
            ['name' => 'Suspension System'],
            ['name' => 'Tires'],
            ['name' => 'Wheels & Rims'],
            ['name' => 'Valve Adjustment/Engine Tune Up'],
            ['name' => 'Replace Oil Pump and O-rings'],
            ['name' => 'Alignment'],
        ];

        foreach ($maintenanceCategories as $category) {
            DB::table('maintenance_categories')->insert([
                'name' => $category['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
