<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FuelTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fuelTypes = [
            ['name' => 'Gasoline'],
            ['name' => 'Diesel'],
            ['name' => 'Electric'],
            ['name' => 'Hybrid'],
            ['name' => 'Plug-in Hybrid'],
            ['name' => 'Compressed Natural Gas (CNG)'],
            ['name' => 'Liquefied Petroleum Gas (LPG)'],
            ['name' => 'Bio-diesel'],
            ['name' => 'Ethanol'],
            ['name' => 'Hydrogen'],
            ['name' => 'Flex Fuel'],
        ];

        foreach ($fuelTypes as $type) {
            DB::table('fuel_types')->insert([
                'name' => $type['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}