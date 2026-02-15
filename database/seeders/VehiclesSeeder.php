<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VehiclesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get reference IDs from previously seeded tables
        $vehicleTypes = DB::table('vehicle_types')->pluck('id', 'name')->toArray();
        $fuelTypes = DB::table('fuel_types')->pluck('id', 'name')->toArray();
        $vehicleGroups = DB::table('vehicle_groups')->pluck('id', 'name')->toArray();

        // Get company IDs
        $companies = DB::table('companies')->pluck('id');

        // Default to first company if specific companies not found
        $defaultCompanyId = !empty($companies) ? reset($companies) : null;

        $vehicles = [
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'V001',
                'vin' => '1HGCM82633A123456',
                'year' => 2022,
                'make' => 'Ford',
                'model' => 'F-150',
                'vehicle_type_id' => $vehicleTypes['Pickup Truck'] ?? null,
                'owned_by' => 'Company Owned',
                'color' => 'Black',
                'title_no' => 'T12345678',
                'tire_size' => '275/55R20',
                'odometer' => 24500,
                'gvw' => 7000,
                'vehicle_group_id' => $vehicleGroups['Service Vehicles'] ?? null,
                'fuel_type_id' => $fuelTypes['Gasoline'] ?? null,
                'engine_type' => '3.5L V6 EcoBoost',
                'transmission' => '10-Speed Automatic',
                'suspension' => 'Independent Front',
                'no_axles' => 2,
                'configuration' => 'conventional',
                'wheel_base' => 145,
                'size_dimension' => '231.9" L x 79.9" W',
                'notes' => 'Service truck with toolbox. Regular maintenance required.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'V002',
                'vin' => '5TDZA23C78S123457',
                'year' => 2023,
                'make' => 'Toyota',
                'model' => 'Camry',
                'vehicle_type_id' => $vehicleTypes['Sedan'] ?? null,
                'owned_by' => 'Company Owned',
                'color' => 'Silver',
                'title_no' => 'T87654321',
                'tire_size' => '215/55R17',
                'odometer' => 12000,
                'gvw' => 3500,
                'vehicle_group_id' => $vehicleGroups['Sales Department'] ?? null,
                'fuel_type_id' => $fuelTypes['Hybrid'] ?? null,
                'engine_type' => '2.5L Hybrid',
                'transmission' => 'eCVT',
                'suspension' => 'MacPherson Strut',
                'no_axles' => 2,
                'configuration' => 'conventional',
                'wheel_base' => 111,
                'size_dimension' => '192.7" L x 72.4" W',
                'notes' => 'Sales representative vehicle. Maintained regularly.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'V003',
                'vin' => '1C4RJFBG8MC123458',
                'year' => 2021,
                'make' => 'Jeep',
                'model' => 'Grand Cherokee',
                'vehicle_type_id' => $vehicleTypes['SUV'] ?? null,
                'owned_by' => 'Leased',
                'color' => 'White',
                'title_no' => 'T23456789',
                'tire_size' => '265/60R18',
                'odometer' => 38000,
                'gvw' => 6000,
                'vehicle_group_id' => $vehicleGroups['Executive Vehicles'] ?? null,
                'fuel_type_id' => $fuelTypes['Diesel'] ?? null,
                'engine_type' => '3.0L V6 Turbo Diesel',
                'transmission' => '8-Speed Automatic',
                'suspension' => 'Quadra-Lift Air',
                'no_axles' => 2,
                'configuration' => 'conventional',
                'wheel_base' => 115,
                'size_dimension' => '189.8" L x 77.9" W',
                'notes' => 'Executive vehicle with premium package.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'V004',
                'vin' => 'WBA7E2C58JG123459',
                'year' => 2024,
                'make' => 'BMW',
                'model' => 'i4',
                'vehicle_type_id' => $vehicleTypes['Electric Vehicle'] ?? null,
                'owned_by' => 'Company Owned',
                'color' => 'Blue',
                'title_no' => 'T34567890',
                'tire_size' => '245/45R19',
                'odometer' => 5000,
                'gvw' => 4700,
                'vehicle_group_id' => $vehicleGroups['Executive Vehicles'] ?? null,
                'fuel_type_id' => $fuelTypes['Electric'] ?? null,
                'engine_type' => 'Electric Motor',
                'transmission' => 'Single-Speed',
                'suspension' => 'Adaptive M Suspension',
                'no_axles' => 2,
                'configuration' => 'conventional',
                'wheel_base' => 112,
                'size_dimension' => '188.3" L x 72.9" W',
                'notes' => 'Fully electric vehicle for executive use.',
            ],
            [
                'company_id' => $companies->random() ?? $defaultCompanyId,
                'unit_no' => 'V005',
                'vin' => '1GB3G3CG3GF123460',
                'year' => 2020,
                'make' => 'Chevrolet',
                'model' => 'Silverado 3500',
                'vehicle_type_id' => $vehicleTypes['Truck'] ?? null,
                'owned_by' => 'Company Owned',
                'color' => 'Red',
                'title_no' => 'T45678901',
                'tire_size' => 'LT245/75R17',
                'odometer' => 65000,
                'gvw' => 14000,
                'vehicle_group_id' => $vehicleGroups['Field Operations'] ?? null,
                'fuel_type_id' => $fuelTypes['Diesel'] ?? null,
                'engine_type' => '6.6L V8 Turbo Diesel',
                'transmission' => '6-Speed Automatic',
                'suspension' => 'Heavy Duty',
                'no_axles' => 2,
                'configuration' => 'conventional',
                'wheel_base' => 168,
                'size_dimension' => '250.0" L x 96.0" W',
                'notes' => 'Heavy duty work truck with utility bed.',
            ],
        ];

        foreach ($vehicles as $vehicle) {
            DB::table('vehicles')->insert(array_merge($vehicle, [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
        }

        $this->command->info('Vehicles seeded successfully!');
    }
}
