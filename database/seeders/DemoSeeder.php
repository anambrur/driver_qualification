<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Driver;
use App\Models\License;
use App\Models\PlanPrice;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Services\Company\CompanyProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Demo data with known passwords: two Platform Owners and two companies. Replaces
 * DemoUserSeeder, CompanySeeder and DriverSeeder.
 *
 * Companies are built through CompanyProvisioner; permission grants on the roles follow in WP3.
 */
class DemoSeeder extends Seeder
{
    public const ENVIRONMENTS = ['local', 'testing', 'staging'];

    public const PASSWORD = '12345678';

    public function run(): void
    {
        if (! app()->environment(self::ENVIRONMENTS)) {
            throw new RuntimeException('DemoSeeder creates logins with a known password and only runs in local, testing and staging.');
        }

        DB::transaction(function () {
            $this->platformOwner('superadmin@gmail.com', 'Platform Owner');
            $this->platformOwner('platform2@gmail.com', 'Second Platform Owner');

            $this->company('company1@gmail.com', 'Transport Solutions Inc', 'transport-solutions-inc', 'CA', 'Los Angeles');
            $this->company('company2@gmail.com', 'Lone Star Freight LLC', 'lone-star-freight', 'TX', 'Dallas');
        });

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function platformOwner(string $email, string $name): void
    {
        $owner = PlatformUser::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD), 'status' => 'active']
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
        $role = Role::findOrCreate('Platform Owner', 'platform');
        $role->forceFill(['system_key' => 'platform_owner', 'is_system' => true])->save();
        $owner->assignRole($role);
    }

    private function company(string $email, string $name, string $slug, string $state, string $city): void
    {
        if (Company::where('slug', $slug)->exists()) {
            return;
        }

        $company = app(CompanyProvisioner::class)->provision([
            'company_name' => $name,
            'slug' => $slug,
            'email' => $email,
            'billing_email' => $email,
            'city' => $city,
            'state_code' => $state,
            'phone' => '555-0101',
        ], [
            'name' => "{$name} Owner",
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);
        $owner = $company->owner;

        $price = PlanPrice::query()->current()->where('interval', 'month')
            ->whereHas('plan', fn ($q) => $q->where('slug', 'company'))
            ->firstOrFail();

        Subscription::create([
            'company_id' => $company->id,
            'actor_user_id' => $owner->id,
            'plan_price_id' => $price->id,
            'interval' => $price->interval,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'status' => 'active',
            'source' => 'admin',
            'current_period_start' => now(),
            'current_period_end' => now()->addYear(),
        ]);

        foreach ([['John', 'Smith'], ['Maria', 'Garcia'], ['David', 'Lee']] as $i => [$first, $last]) {
            $driver = Driver::create([
                'company_id' => $company->id,
                'created_by_user_id' => $owner->id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower("{$first}.{$last}@{$slug}.test"),
                'main_phone' => '+120255501'.$i.$company->id,
                'state_code' => $state,
                'country_code' => 'US',
                'employment_status' => 'active',
                'source' => 'admin',
                'hired_at' => now(),
                'hire_date' => now()->toDateString(),
            ]);

            License::create([
                'driver_id' => $driver->id,
                'first_name' => $first,
                'last_name' => $last,
                'issued' => now()->subYears(3)->toDateString(),
                'expires' => now()->addYears(2)->toDateString(),
                'country_code' => 'US',
                'state_code' => $state,
                'class' => 'A',
                'license_number' => strtoupper($state).str_pad((string) ($company->id * 100 + $i), 8, '0', STR_PAD_LEFT),
                'is_current' => true,
            ]);
        }

        Driver::create([
            'company_id' => $company->id,
            'first_name' => 'Alex',
            'last_name' => 'Applicant',
            'email' => "alex.applicant@{$slug}.test",
            'main_phone' => '+1202555019'.$company->id,
            'application_status' => 'pending',
            'employment_status' => 'applicant',
            'source' => 'public_application',
        ]);
    }
}
