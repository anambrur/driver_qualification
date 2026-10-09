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

class PermissionSeeder extends Seeder
{
    // Shared with the test suite (tests/Support/Actors.php) so tests use the real permission map.
    public const MODULES = [
        'roles',
        'users',
        'permission',
        'settings',
        'companies',
        'drivers',
        'policy-pdf',
        'fleets',
        'vehicle-types',
        'vehicle-groups',
        'vehicles',
        'fuel-types',
        'equipment-types',
        'trailers',
        'asset-groups',
        'asset-histories',
        'document-types',
        'maintenance-categories',
        'maintenance',
        'scheduled',
        'subscription',
    ];

    public const BASIC_ACTIONS = ['create', 'view', 'edit', 'delete'];

    public const SPECIAL_ACTIONS = [
        'drivers' => ['hire', 'dashboard'],
        'fleets' => ['dashboard'],
    ];

    public const COMPANY_MODULES = ['drivers', 'fleets', 'vehicles', 'trailers', 'maintenance', 'scheduled'];

    public const COMPANY_EXTRA_PERMISSIONS = ['companies.edit'];

    /**
     * All permission names, e.g. "drivers.create", "drivers.hire".
     *
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        $names = [];

        foreach (self::MODULES as $module) {
            foreach (self::BASIC_ACTIONS as $action) {
                $names[] = "$module.$action";
            }

            foreach (self::SPECIAL_ACTIONS[$module] ?? [] as $action) {
                $names[] = "$module.$action";
            }
        }

        return $names;
    }

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

        // Create permissions dynamically
        foreach (self::permissionNames() as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }

        // Create roles
        $roles = Role::insert([
            ['name' => 'super-admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'company', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'user', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $roleSuperAdmin = Role::where('name', 'super-admin')->first();
        $roleSuperAdmin->givePermissionTo(Permission::all());

        $roleCompany = Role::where('name', 'company')->first();
        $roleCompany->givePermissionTo(
            Permission::where(function ($query) {
                foreach (self::COMPANY_MODULES as $module) {
                    $query->orWhere('name', 'like', $module . '.%');
                }
            })->get()
        );

        // Allow company users to edit their own company profile (My Account)
        $roleCompany->givePermissionTo(self::COMPANY_EXTRA_PERMISSIONS);

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
            'logo' => '',
            'status' => 'active',
        ]);
    }
}
