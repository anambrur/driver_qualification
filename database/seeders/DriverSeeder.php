<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Company;
use Carbon\Carbon;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create users
        $driverUser1 = User::where('email', 'driver1@example.com')->first();
        if (!$driverUser1) {
            $driverUser1 = User::factory()->create([
                'name' => 'John Smith',
                'email' => 'driver1@example.com',
            ]);
        }

        $driverUser2 = User::where('email', 'driver2@example.com')->first();
        if (!$driverUser2) {
            $driverUser2 = User::factory()->create([
                'name' => 'Maria Garcia',
                'email' => 'driver2@example.com',
            ]);
        }

        // Get admin user for action_by
        $adminUser = User::where('email', 'admin@example.com')->first();
        if (!$adminUser) {
            $adminUser = User::first();
        }

        // Get or create company
        $company = Company::first();
        if (!$company) {
            $company = Company::create([
                'name' => 'Transport Solutions Inc.',
                'address' => '789 Business Blvd',
                'city' => 'Dallas',
                'state' => 'TX',
                'country' => 'US',
                'postal_code' => '75201',
            ]);
        }

        // Clear existing drivers
        // DB::table('drivers')->truncate();

        $drivers = [
            [
                'user_id' => $driverUser1->id,
                'company_id' => $company->id,
                'first_name' => 'John',
                'middle_name' => 'A',
                'last_name' => 'Smith',
                'suffix' => 'Jr.',
                'date_of_birth' => '1980-05-15',
                'ssn' => '123-45-6789',
                'main_phone' => '(555) 123-4567',
                'alt_phone' => '(555) 987-6543',
                'email' => 'john.smith@example.com',
                'medical_certificate_expiration_date' => '2024-12-31',
                'photo' => null,
                'business_name' => 'Smith Trucking LLC',
                'employer_identification_number' => '12-3456789',
                'federal_tax_classification' => 'llc',
                'address' => '123 Main St',
                'city' => 'Chicago',
                'state' => 'IL',
                'country' => 'US',
                'postal_code' => '60601',
                'twic_card' => true,
                'passport' => false,
                'status' => 'active',
                'hazmat' => 'yes',
                'lcv_certificate' => 'no',
                'rejection_reason' => null,
                'rejection_notes' => null,
                'rejection_date' => null,
                'hired_at' => Carbon::now()->subMonths(6),
                'rejected_at' => null,
                'action_by' => $adminUser ? $adminUser->id : null,
                'created_at' => Carbon::now()->subYear(),
                'updated_at' => Carbon::now()->subMonth(),
            ],
            [
                'user_id' => $driverUser2->id,
                'company_id' => $company->id,
                'first_name' => 'Maria',
                'middle_name' => null,
                'last_name' => 'Garcia',
                'suffix' => null,
                'date_of_birth' => '1975-08-22',
                'ssn' => '987-65-4321',
                'main_phone' => '(555) 234-5678',
                'alt_phone' => null,
                'email' => 'maria.garcia@example.com',
                'medical_certificate_expiration_date' => '2024-06-30',
                'photo' => null,
                'business_name' => null,
                'employer_identification_number' => null,
                'federal_tax_classification' => 'individual_sole_proprietor',
                'address' => '456 Oak Ave',
                'city' => 'Houston',
                'state' => 'TX',
                'country' => 'US',
                'postal_code' => '77001',
                'twic_card' => false,
                'passport' => true,
                'status' => 'pending',
                'hazmat' => 'no',
                'lcv_certificate' => 'yes',
                'rejection_reason' => null,
                'rejection_notes' => null,
                'rejection_date' => null,
                'hired_at' => null,
                'rejected_at' => null,
                'action_by' => null,
                'created_at' => Carbon::now()->subDays(15),
                'updated_at' => Carbon::now()->subDays(5),
            ],
        ];

        DB::table('drivers')->insert($drivers);
    }
}
