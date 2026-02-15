<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the super admin user to associate with companies
        $superAdmin = User::where('email', 'superadmin@gmail.com')->first();

        // Create first company with its own admin user
        $company1User = User::create([
            'name' => 'Company 1 Admin',
            'email' => 'company1@gmail.com',
            'password' => Hash::make('12345678'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $company1 = Company::create([
            'user_id' => $company1User->id,
            'company_name' => 'Transport Solutions Inc',
            'slug' => 'transport-solutions-inc',
            'email' => 'info@transportsolutions.com',
            'address' => '123 Transport Ave',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'zip' => '90001',
            'description' => 'Leading transport and logistics company specializing in freight and cargo delivery across the West Coast.',
            'phone' => '555-0101',
            'fax' => '555-0102',
            'logo' => '',
            'status' => 'active',
        ]);

        // Assign admin role to the company user
        $company1User->assignRole('company');

        // Create second company with its own admin user
        $company2User = User::create([
            'name' => 'Company 2 Admin',
            'email' => 'company2@gmail.com',
            'password' => Hash::make('12345678'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $company2 = Company::create([
            'user_id' => $company2User->id,
            'company_name' => 'City Logistics LLC',
            'slug' => 'city-logistics-llc',
            'email' => 'contact@citylogistics.com',
            'address' => '456 Commerce Blvd',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001',
            'description' => 'Urban delivery and logistics services for businesses in the metropolitan area. Specializing in last-mile delivery.',
            'phone' => '555-0201',
            'fax' => '555-0202',
            'logo' => '',
            'status' => 'active',
        ]);

        // Assign admin role to the second company user
        $company2User->assignRole('company');
    }
}
