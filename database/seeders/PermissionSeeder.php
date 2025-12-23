<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // php artisan db:seed --class=PermissionSeeder
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Clear all permission-related data and demo users
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        Permission::truncate();
        Role::truncate();
        User::truncate();
        Company::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Define the modules
        $modules = [
            'roles',
            'users',
            'permission',
            'settings',
            'companies',
            'drivers',
            'policy-pdf',
        ];

        // Define basic actions
        $basicActions = ['create', 'view', 'edit', 'delete'];

        // Define special actions for specific modules
        $specialActions = [
            'drivers' => ['hire'],

        ];

        // Create permissions dynamically
        foreach ($modules as $module) {
            // Create basic permissions
            foreach ($basicActions as $action) {
                Permission::create(['name' => "$module.$action", 'guard_name' => 'web']);
            }

            // Create special permissions if defined for this module
            if (isset($specialActions[$module])) {
                foreach ($specialActions[$module] as $action) {
                    Permission::create(['name' => "$module.$action", 'guard_name' => 'web']);
                }
            }
        }

        // Create roles
        $roles = Role::insert([
            ['name' => 'super-admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'manager', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'user', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $roleSuperAdmin = Role::where('name', 'super-admin')->first();
        $roleSuperAdmin->givePermissionTo(Permission::all());

        // Create demo users
        $superAdmin = User::factory()->create([
            'name' => 'Super-Admin',
            'email' => 'superadmin@gmail.com',
            'password' => Hash::make('12345678'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $superAdmin->assignRole($roleSuperAdmin);

        // Create company with user_id
        Company::create([
            'user_id' => $superAdmin->id,
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
            'logo' => 'test-logo.png',
            'status' => 'active',
        ]);
    }
}
