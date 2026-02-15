<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrailersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get reference IDs from previously seeded tables
        $equipmentTypes = DB::table('equipment_types')->pluck('id', 'name')->toArray();
        $vehicleGroups = DB::table('vehicle_groups')->pluck('id', 'name')->toArray();

        // Get company IDs
        $companies = DB::table('companies')->pluck('id');

        // Default to first company if specific companies not found
        $defaultCompanyId = !empty($companies) ? reset($companies) : null;

        $trailers = [
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'T001',
                'vin' => '1UYVS2533AM123461',
                'year' => 2022,
                'make' => 'Great Dane',
                'model' => 'EverDry',
                'equipment_types_id' => $equipmentTypes['Trailer Hitch'] ?? null,
                'owned_by' => 'Company Owned',
                'color' => 'White',
                'title_no' => 'TT1234567',
                'tire_size' => '295/75R22.5',
                'gvw' => 34000,
                'vehicle_group_id' => $vehicleGroups['Heavy Machinery'] ?? null,
                'notes' => '53ft dry van trailer. Excellent condition.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'T002',
                'vin' => '1UYVS2533AM123462',
                'year' => 2021,
                'make' => 'Utility',
                'model' => '4000D',
                'equipment_types_id' => $equipmentTypes['Trailer Hitch'] ?? null,
                'owned_by' => 'Company Owned',
                'color' => 'Black',
                'title_no' => 'TT2345678',
                'tire_size' => '235/80R17.5',
                'gvw' => 14000,
                'vehicle_group_id' => $vehicleGroups['Service Vehicles'] ?? null,
                'notes' => 'Equipment trailer with ramp gate.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'T003',
                'vin' => '1UYVS2533AM123463',
                'year' => 2023,
                'make' => 'Wabash',
                'model' => 'National',
                'equipment_types_id' => $equipmentTypes['Refrigeration Unit'] ?? null,
                'owned_by' => 'Leased',
                'color' => 'Blue',
                'title_no' => 'TT3456789',
                'tire_size' => '275/70R22.5',
                'gvw' => 42000,
                'vehicle_group_id' => $vehicleGroups['Delivery Vehicles'] ?? null,
                'notes' => 'Refrigerated trailer for perishable goods.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'T004',
                'vin' => '1UYVS2533AM123464',
                'year' => 2020,
                'make' => 'MAC',
                'model' => 'Platform',
                'equipment_types_id' => $equipmentTypes['Trailer Hitch'] ?? null,
                'owned_by' => 'Company Owned',
                'color' => 'Silver',
                'title_no' => 'TT4567890',
                'tire_size' => '265/70R19.5',
                'gvw' => 48000,
                'vehicle_group_id' => $vehicleGroups['Heavy Machinery'] ?? null,
                'notes' => 'Flatbed trailer for heavy equipment transport.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'T005',
                'vin' => '1UYVS2533AM123465',
                'year' => 2022,
                'make' => 'East',
                'model' => 'End Dump',
                'equipment_types_id' => $equipmentTypes['Trailer Hitch'] ?? null,
                'owned_by' => 'Company Owned',
                'color' => 'Red',
                'title_no' => 'TT5678901',
                'tire_size' => '11R22.5',
                'gvw' => 52000,
                'vehicle_group_id' => $vehicleGroups['Field Operations'] ?? null,
                'notes' => 'Dump trailer for construction materials.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'T006',
                'vin' => '1UYVS2533AM123466',
                'year' => 2024,
                'make' => 'Stoughton',
                'model' => 'Contour',
                'equipment_types_id' => $equipmentTypes['Refrigeration Unit'] ?? null,
                'owned_by' => 'Leased',
                'color' => 'White',
                'title_no' => 'TT6789012',
                'tire_size' => '285/75R24.5',
                'gvw' => 36000,
                'vehicle_group_id' => $vehicleGroups['Delivery Vehicles'] ?? null,
                'notes' => 'New refrigerated trailer for cold chain logistics.',
            ],
        ];

        foreach ($trailers as $trailer) {
            DB::table('trailers')->insert(array_merge($trailer, [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
        }

        $this->command->info('Trailers seeded successfully!');
    }
}
