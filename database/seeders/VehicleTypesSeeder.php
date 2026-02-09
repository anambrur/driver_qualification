<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicleTypes = [
            ['name' => 'Sedan'],
            ['name' => 'SUV'],
            ['name' => 'Truck'],
            ['name' => 'Van'],
            ['name' => 'Coupe'],
            ['name' => 'Hatchback'],
            ['name' => 'Convertible'],
            ['name' => 'Minivan'],
            ['name' => 'Pickup Truck'],
            ['name' => 'Crossover'],
            ['name' => 'Sports Car'],
            ['name' => 'Electric Vehicle'],
            ['name' => 'Hybrid'],
            ['name' => 'Motorcycle'],
            ['name' => 'Bus'],
            ['name' => 'Tractor'],
            ['name' => 'Trailer'],
            ['name' => 'Heavy Equipment'],
        ];

        foreach ($vehicleTypes as $type) {
            DB::table('vehicle_types')->insert([
                'name' => $type['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}