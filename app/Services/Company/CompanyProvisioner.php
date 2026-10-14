<?php

namespace App\Services\Company;

use App\Authorization\RoleTemplates;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The only code path that creates a company (10 §1, 11 §4): registration, platform
 * "create company", seeders and factories all come through here, so every company gets the
 * same owner, settings, trip sequence, system roles and support user.
 */
class CompanyProvisioner
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $company  companies columns; company_name required
     * @param  array<string, mixed>  $owner  users columns; name, email, password required
     */
    public function provision(array $company, array $owner): Company
    {
        return DB::transaction(function () use ($company, $owner) {
            $model = new Company;
            $model->forceFill(Arr::except($company, ['id', 'owner_user_id']) + ['status' => 'active']);
            $model->slug ??= $this->uniqueSlug((string) $company['company_name']);
            $model->save();

            $ownerUser = new User;
            $ownerUser->forceFill(['user_type' => 'member'] + $owner + ['status' => 'active']);
            $ownerUser->company_id = $model->id;
            $ownerUser->save();

            $model->forceFill(['owner_user_id' => $ownerUser->id])->save();

            $model->settings()->create([]);
            DB::table('trip_number_sequences')->insert([
                'company_id' => $model->id,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->tenant->run($model->id, function () use ($ownerUser) {
                $permissions = Permission::query()->where('guard_name', 'web')->pluck('id', 'name');

                foreach (RoleTemplates::SYSTEM_ROLES as $name => $key) {
                    $expected = RoleTemplates::permissionsFor($name);
                    $grants = $permissions->only($expected);

                    if ($grants->count() !== count($expected)) {
                        throw new RuntimeException('The permission catalogue is not seeded (PermissionSeeder).');
                    }

                    Role::create(['name' => $name, 'guard_name' => 'web', 'system_key' => $key, 'is_system' => true])
                        ->permissions()->attach($grants->values()->all());
                }

                $ownerUser->assignRole(RoleTemplates::OWNER);
            });

            $this->createSupportUser($model);

            return $model->setRelation('owner', $ownerUser);
        });
    }

    /**
     * Platform staff log in as this user during a support session (04 §4.3). It has no usable
     * password and stays inactive outside sessions.
     */
    private function createSupportUser(Company $company): void
    {
        $support = new User;
        $support->forceFill([
            'company_id' => $company->id,
            'user_type' => 'support',
            'name' => 'Support',
            'email' => "support-{$company->id}-".Str::lower(Str::random(12)).'@support.invalid',
            'password' => Hash::make(Str::random(64)),
            'status' => 'inactive',
        ])->save();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';

        do {
            $slug = $base.'-'.Str::lower(Str::random(4));
        } while (Company::where('slug', $slug)->exists());

        return $slug;
    }
}
