<?php

namespace Tests\Support;

use App\Authorization\RoleTemplates;
use App\Models\Company;
use App\Models\Driver;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Database\Factories\PlanPriceFactory;
use Database\Factories\PlatformUserFactory;
use Database\Factories\SubscriptionFactory;
use Database\Factories\UserFactory;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Test actors per docs/architecture/11-migration-strategy.md §5.
 *
 * Companies are built through CompanyProvisioner (via CompanyFactory) with the seven system
 * roles of 06 §6 and their template permissions.
 */
final class Actors
{
    /** Role name => system_key, from 06 §6. */
    public const SYSTEM_ROLES = RoleTemplates::SYSTEM_ROLES;

    public static function platformOwner(array $attributes = []): PlatformUser
    {
        return self::platformUser('platform_owner', $attributes);
    }

    public static function platformSupport(array $attributes = []): PlatformUser
    {
        return self::platformUser('platform_support', $attributes);
    }

    /**
     * An active company with an owner, settings, the system roles and, unless $subscribed is
     * false, an open, active subscription on a plan with every feature.
     */
    public static function company(array $attributes = [], bool $subscribed = true): Company
    {
        $company = CompanyFactory::new()->create($attributes);

        if ($subscribed) {
            SubscriptionFactory::new()->forCompany($company)->create();
        }

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
     * A user holding one custom role with exactly these permissions.
     *
     * @param  list<string>  $permissions
     */
    public static function memberWithPermissions(?Company $company, array $permissions, array $attributes = []): User
    {
        $company ??= self::company();
        $role = Role::create(['name' => 'Custom '.Str::random(8), 'guard_name' => 'web', 'team_id' => $company->id]);
        $role->syncPermissions($permissions);

        return self::member($company, $role->name, $attributes);
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

    public static function companyOf(User $user): Company
    {
        return $user->company;
    }

    /**
     * A company member without any role or permission.
     */
    public static function plainUser(?Company $company = null, array $attributes = []): User
    {
        return UserFactory::new()->create(['company_id' => ($company ?? self::company())->id] + $attributes);
    }

    public static function driverOf(User $driverUser): Driver
    {
        return Driver::withoutTenancy()->where('user_id', $driverUser->id)->firstOrFail();
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
        app(TenantContext::class)->set($user->company_id);
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

    private static function platformUser(string $systemKey, array $attributes): PlatformUser
    {
        (new PlatformRoleSeeder)->run();
        $user = PlatformUserFactory::new()->create($attributes);
        $role = Role::query()->where('team_id', 0)->where('system_key', $systemKey)->firstOrFail();

        self::withTeam(0, fn () => $user->assignRole($role));

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
