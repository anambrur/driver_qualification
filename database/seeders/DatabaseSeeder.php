<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call(PermissionSeeder::class);
        $this->call(CompanySeeder::class);
        $this->call(CountryStateSeeder::class);
        $this->call(DriverSeeder::class);
        $this->call(DocumentTypeSeeder::class);
        $this->call(VehicleTypesSeeder::class);
        $this->call(FuelTypesSeeder::class);
        $this->call(VehicleGroupsSeeder::class);
        $this->call(EquipmentTypesSeeder::class);
        $this->call(VehiclesSeeder::class);
        $this->call(TrailersSeeder::class);
    }
}
