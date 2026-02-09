<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicleGroups = [
            ['name' => 'Company Fleet'],
            ['name' => 'Executive Vehicles'],
            ['name' => 'Service Vehicles'],
            ['name' => 'Delivery Vehicles'],
            ['name' => 'Sales Department'],
            ['name' => 'Maintenance Department'],
            ['name' => 'Field Operations'],
            ['name' => 'Security Vehicles'],
            ['name' => 'Rental Fleet'],
            ['name' => 'Emergency Response'],
            ['name' => 'Heavy Machinery'],
            ['name' => 'Light Duty Vehicles'],
        ];

        foreach ($vehicleGroups as $group) {
            DB::table('vehicle_groups')->insert([
                'name' => $group['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}