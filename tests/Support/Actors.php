<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Driver;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Database\Factories\PlanPriceFactory;
use Database\Factories\PlatformUserFactory;
use Database\Factories\SubscriptionFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Test actors per docs/architecture/11-migration-strategy.md §5.
 *
 * Interim (WP1): companies are built with the factories and get the seven system roles of
 * 06 §6 as bare rows, without permission grants. CompanyProvisioner (WP2) and RoleTemplates
 * (WP3) replace that, and actingAsTenant() will also set TenantContext (WP2).
 */
final class Actors
{
    /** Role name => system_key (null = system role without a key), from 06 §6. */
    public const SYSTEM_ROLES = [
        'Owner' => 'owner',
        'Administrator' => null,
        'Dispatcher' => null,
        'Office / Compliance' => null,
        'Driver' => 'driver',
        'Support (read)' => 'support_read',
        'Support (read/write)' => 'support_read_write',
    ];

    public static function platformOwner(array $attributes = []): PlatformUser
    {
        return self::platformUser('Platform Owner', 'platform_owner', $attributes);
    }

    public static function platformSupport(array $attributes = []): PlatformUser
    {
        return self::platformUser('Support Agent', 'platform_support', $attributes);
    }

    /**
     * An active company with an owner, settings, the system roles and an open, active
     * subscription. No feature rows exist until WP7, so the plan has no plan_features yet.
     */
    public static function company(array $attributes = []): Company
    {
        $company = CompanyFactory::new()->create($attributes);

        self::withTeam($company->id, function () use ($company) {
            foreach (self::SYSTEM_ROLES as $name => $key) {
                Role::create(['name' => $name, 'guard_name' => 'web', 'system_key' => $key, 'is_system' => true]);
            }

            $company->owner->assignRole('Owner');
        });

        SubscriptionFactory::new()->forCompany($company)->create();

        return $company->fresh();
    }

    public static function companyOwner(?Company $company = null): User
    {
        return ($company ?? self::company())->owner;
    }

    public static function companyAdmin(?Company $company = null, array $attributes = []): User
    {
        return self::member($company, 'Administrator', $attributes);
    }

    public static function dispatcher(?Company $company = null, array $attributes = []): User
    {
        return self::member($company, 'Dispatcher', $attributes);
    }

    public static function officeUser(?Company $company = null, array $attributes = []): User
    {
        return self::member($company, 'Office / Compliance', $attributes);
    }

    /**
     * A driver record linked to its own login, which holds the Driver role.
     */
    public static function driverUser(?Company $company = null, array $attributes = []): User
    {
        $company ??= self::company();
        $user = self::member($company, 'Driver', $attributes);

        DriverFactory::new()->forCompany($company)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->name,
        ]);

        return $user;
    }

    public static function driverOf(User $driverUser): Driver
    {
        return Driver::where('user_id', $driverUser->id)->firstOrFail();
    }

    /**
     * Moves the company to a new open subscription on a fresh plan with exactly these
     * features enabled. Feature rows are created as modules when missing.
     *
     * @param  list<string>  $features
     */
    public static function withPlan(Company $company, array $features): Company
    {
        $price = PlanPriceFactory::new()->create();

        foreach ($features as $key) {
            DB::table('features')->insertOrIgnore([
                'key' => $key, 'name' => $key, 'group' => 'test', 'kind' => 'module', 'availability' => 'general',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('plan_features')->insert([
                'plan_id' => $price->plan_id, 'feature_key' => $key, 'enabled' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $company->subscriptions()->whereNull('ended_at')->update(['ended_at' => now()]);
        SubscriptionFactory::new()->forCompany($company)->create(['plan_price_id' => $price->id]);

        return $company->fresh();
    }

    /**
     * Subscription canceled and past the 7-day grace period (Q2), so the company is read-only.
     */
    public static function readOnly(Company $company): Company
    {
        $company->subscriptions()->whereNull('ended_at')->update([
            'status' => 'canceled',
            'current_period_end' => now()->subDays(8),
            'ends_at' => now()->subDays(8),
            'ended_at' => now()->subDays(8),
        ]);

        return $company->fresh();
    }

    public static function suspended(Company $company): Company
    {
        $company->forceFill(['status' => 'suspended', 'status_changed_at' => now(), 'status_reason' => 'test'])->save();

        return $company->fresh();
    }

    /**
     * Acts as the user inside their own company's team.
     */
    public static function actingAsTenant(User $user): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);
        Auth::guard('web')->setUser($user);
        Auth::shouldUse('web');

        return $user;
    }

    public static function openSubscription(Company $company): ?Subscription
    {
        return $company->subscriptions()->whereNull('ended_at')->first();
    }

    private static function member(?Company $company, string $role, array $attributes): User
    {
        $company ??= self::company();
        $user = UserFactory::new()->create(['company_id' => $company->id] + $attributes);

        self::withTeam($company->id, fn () => $user->assignRole($role));

        return $user;
    }

    private static function platformUser(string $role, string $key, array $attributes): PlatformUser
    {
        $user = PlatformUserFactory::new()->create($attributes);

        self::withTeam(0, function () use ($user, $role, $key) {
            $model = Role::findOrCreate($role, 'platform');
            $model->forceFill(['system_key' => $key, 'is_system' => true])->save();
            $user->assignRole($model);
        });

        return $user;
    }

    /**
     * Runs $callback with the Spatie team set, then restores the previous team.
     */
    private static function withTeam(int $teamId, callable $callback): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($teamId);

        try {
            $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
