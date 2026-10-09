<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo super-admin login for local development. Needs the roles from PermissionSeeder.
 * Refuses to run anywhere else, because the password is well known.
 *
 * php artisan db:seed --class=DemoUserSeeder
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DemoUserSeeder creates a super-admin with a known password and only runs in local environments.');
        }

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super-Admin',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $superAdmin->assignRole('super-admin');

        Company::firstOrCreate(
            ['user_id' => $superAdmin->id],
            [
                'company_name' => 'Super Admin Company',
                'slug' => 'super-admin-company',
                'email' => 'superadmin@gmail.com',
                'address' => 'Test Address',
                'city' => 'Test City',
                'state' => 'Test State',
                'zip' => '12345',
                'description' => 'Test Description',
                'phone' => '1234567890',
                'fax' => '1234567890',
                'logo' => '',
                'status' => 'active',
            ]
        );
    }
}
