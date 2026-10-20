<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Every environment: reference data, the permission catalogue, platform roles and plans.
        $this->call(CountryStateSeeder::class);
        $this->call(DocumentTypeSeeder::class);
        $this->call(VehicleTypesSeeder::class);
        $this->call(FuelTypesSeeder::class);
        $this->call(VehicleGroupsSeeder::class);
        $this->call(MaintenaneCategoySeeder::class);
        $this->call(EquipmentTypesSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(PlatformRoleSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(LandingContentSeeder::class);

        if (app()->environment(DemoSeeder::ENVIRONMENTS)) {
            $this->call(DemoSeeder::class);
            $this->call(VehiclesSeeder::class);
            $this->call(TrailersSeeder::class);
        }
    }
}
