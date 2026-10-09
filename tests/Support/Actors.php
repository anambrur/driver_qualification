<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Database\Factories\SubscriptionFactory;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Builds the users the app actually has, wired the way production wires them:
 * roles/permissions from PermissionSeeder, a Company owned by the user, and an
 * accessible subscription (the `Subscribed` middleware guards most /admin routes).
 *
 * PermissionSeeder itself cannot run on SQLite (SET FOREIGN_KEY_CHECKS, truncates users),
 * so seedAccessControl() replays its constants instead.
 */
final class Actors
{
    public static function seedAccessControl(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (Role::query()->where('name', 'super-admin')->exists()) {
            return;
        }

        foreach (PermissionSeeder::permissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('super-admin', 'web')->givePermissionTo(Permission::all());

        $companyPermissions = Permission::query()
            ->where(function ($query) {
                foreach (PermissionSeeder::COMPANY_MODULES as $module) {
                    $query->orWhere('name', 'like', $module.'.%');
                }
            })
            ->pluck('name')
            ->merge(PermissionSeeder::COMPANY_EXTRA_PERMISSIONS)
            ->all();

        Role::findOrCreate('company', 'web')->givePermissionTo($companyPermissions);
        Role::findOrCreate('user', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Super-admin with their own company, as created by PermissionSeeder.
     * Super-admins bypass the `Subscribed` middleware, so no subscription is created.
     */
    public static function superAdmin(array $attributes = []): User
    {
        self::seedAccessControl();

        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $user->assignRole('super-admin');
        CompanyFactory::new()->create(['user_id' => $user->id, 'email' => $user->email]);

        return $user->fresh();
    }

    /**
     * A tenant: a "company" role user who owns a Company and has an active subscription.
     */
    public static function companyOwner(array $attributes = [], array $companyAttributes = [], bool $subscribed = true): User
    {
        self::seedAccessControl();

        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $user->assignRole('company');
        CompanyFactory::new()->create(array_merge(['user_id' => $user->id, 'email' => $user->email], $companyAttributes));

        if ($subscribed) {
            self::subscribe($user);
        }

        return $user->fresh();
    }

    /**
     * A tenant whose subscription has expired (should be redirected to pricing by `Subscribed`).
     */
    public static function expiredCompanyOwner(array $attributes = []): User
    {
        $user = self::companyOwner($attributes, subscribed: false);
        SubscriptionFactory::new()->expired()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    /**
     * A subscribed user with the "user" role (no permissions by default) and no company.
     * Use this to prove routes that forget a `permission:` middleware.
     *
     * @param  list<string>  $permissions
     */
    public static function plainUser(array $permissions = [], bool $subscribed = true): User
    {
        self::seedAccessControl();

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        if ($subscribed) {
            self::subscribe($user);
        }

        return $user->fresh();
    }

    public static function subscribe(User $user, array $attributes = []): Subscription
    {
        return SubscriptionFactory::new()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    public static function companyOf(User $user): Company
    {
        return $user->company()->firstOrFail();
    }
}
