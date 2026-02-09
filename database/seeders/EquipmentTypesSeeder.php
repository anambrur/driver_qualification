<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EquipmentTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $equipmentTypes = [
            ['name' => 'GPS Tracker'],
            ['name' => 'Dash Camera'],
            ['name' => 'Two-Way Radio'],
            ['name' => 'Refrigeration Unit'],
            ['name' => 'Crane'],
            ['name' => 'Forklift'],
            ['name' => 'Generator'],
            ['name' => 'Compressor'],
            ['name' => 'Welding Machine'],
            ['name' => 'Tool Box'],
            ['name' => 'Safety Equipment'],
            ['name' => 'Trailer Hitch'],
            ['name' => 'Snow Plow'],
            ['name' => 'Aerial Lift'],
            ['name' => 'Diagnostic Scanner'],
            ['name' => 'Tow Equipment'],
            ['name' => 'Loading Dock'],
            ['name' => 'Pressure Washer'],
        ];

        foreach ($equipmentTypes as $type) {
            DB::table('equipment_types')->insert([
                'name' => $type['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}