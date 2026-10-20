<?php

use App\Authorization\PermissionCatalogue;
use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Services\Authorization\CompanyRoleSync;
use Illuminate\Support\Facades\DB;
use Tests\Support\Actors;

/*
| Built-in company roles can be edited and reset on any plan; custom roles need the Custom roles
| feature. CompanyRoleSync brings built-in roles up to the templates one company at a time and
| keeps edited ones. Nothing done for company A ever changes company B.
*/

function builtInRole(Company $company, string $name): Role
{
    return Role::query()->where('team_id', $company->id)->where('name', $name)->firstOrFail();
}

/**
 * Every role row and grant of the company, to prove another company is untouched.
 */
function roleSnapshot(Company $company): array
{
    return Role::query()->where('team_id', $company->id)->orderBy('id')->get()
        ->map(fn (Role $role) => [$role->id, $role->name, $role->customized_at?->toIso8601String(), $role->permissions()->pluck('name')->sort()->values()->all()])
        ->all();
}

it('marks an edited built-in role in its own company only', function () {
    $a = Actors::company();
    $b = Actors::company();
    $before = roleSnapshot($b);

    $this->actingAs($a->owner)
        ->put(route('admin.roles.update', builtInRole($a, 'Dispatcher')->id), ['name' => 'Dispatcher', 'permissions' => ['trips.view']])
        ->assertRedirect(route('admin.roles.index'));

    $dispatcher = builtInRole($a, 'Dispatcher');
    expect($dispatcher->customized_at)->not->toBeNull()
        ->and($dispatcher->permissions()->pluck('name')->all())->toBe(['trips.view'])
        ->and(roleSnapshot($b))->toBe($before);
});

it('resets a built-in role to its defaults, in its own company only', function () {
    $a = Actors::company();
    $b = Actors::company();
    $this->actingAs($a->owner)->put(route('admin.roles.update', builtInRole($a, 'Dispatcher')->id), ['name' => 'Dispatcher', 'permissions' => ['trips.view']]);
    $this->actingAs($b->owner)->put(route('admin.roles.update', builtInRole($b, 'Dispatcher')->id), ['name' => 'Dispatcher', 'permissions' => ['drivers.view']]);
    $before = roleSnapshot($b);

    $this->actingAs($a->owner)->post(route('admin.roles.reset', builtInRole($a, 'Dispatcher')->id))->assertRedirect();

    $dispatcher = builtInRole($a, 'Dispatcher');
    expect($dispatcher->customized_at)->toBeNull()
        ->and($dispatcher->permissions()->pluck('name')->sort()->values()->all())->toBe(collect(RoleTemplates::permissionsFor('Dispatcher'))->sort()->values()->all())
        ->and(roleSnapshot($b))->toBe($before)
        ->and(AuditLog::where('action', 'role.reset')->where('company_id', $a->id)->count())->toBe(1);
});

it("returns 404 when resetting or viewing another company's role", function () {
    $a = Actors::company();
    $b = Actors::company();
    $before = roleSnapshot($b);

    $this->actingAs($a->owner)->post(route('admin.roles.reset', builtInRole($b, 'Dispatcher')->id))->assertNotFound();
    $this->actingAs($a->owner)->get(route('admin.roles.show', builtInRole($b, 'Owner')->id))->assertNotFound();

    expect(roleSnapshot($b))->toBe($before);
});

it('refuses to reset a custom or locked role', function (string $name) {
    $company = Actors::company();
    if ($name === 'Yard') {
        $this->actingAs($company->owner)->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => ['vehicles.view']]);
    }

    $this->actingAs($company->owner)->post(route('admin.roles.reset', builtInRole($company, $name)->id))->assertForbidden();
})->with(['Yard', 'Owner']);

it('shows a locked role read-only', function () {
    $company = Actors::company();

    $this->actingAs($company->owner)->get(route('admin.roles.show', builtInRole($company, 'Driver')->id))
        ->assertOk()->assertSee('Locked role')->assertSee('Mobile app')->assertDontSee('name="permissions[]"', false);
});

describe('without the Custom roles feature', function () {
    it('still lets the Owner view roles and edit a built-in one', function () {
        $company = Actors::withPlan(Actors::company(), ['drivers', 'trips']);
        $owner = $company->owner;

        $this->actingAs($owner)->get(route('admin.roles.index'))->assertOk()
            ->assertSee('need a plan with the Custom roles feature')
            ->assertDontSee('href="'.route('admin.roles.create').'"', false);

        $this->actingAs($owner)
            ->put(route('admin.roles.update', builtInRole($company, 'Dispatcher')->id), ['name' => 'Dispatcher', 'permissions' => ['trips.view', 'drivers.view']])
            ->assertRedirect(route('admin.roles.index'));
        expect(builtInRole($company, 'Dispatcher')->customized_at)->not->toBeNull();
    });

    it('refuses to create, edit or delete a custom role', function () {
        // A custom role left over from a plan that had the feature
        $company = Actors::withPlan(Actors::company(), ['drivers']);
        $yard = TeamScope::run($company->id, fn () => Role::create(['name' => 'Yard', 'guard_name' => 'web', 'team_id' => $company->id]));
        $yard->givePermissionTo('drivers.view');

        $this->actingAs($company->owner)->get(route('admin.roles.create'))->assertForbidden();
        $this->actingAs($company->owner)->post(route('admin.roles.store'), ['name' => 'Yard 2', 'permissions' => []])->assertForbidden();
        $this->actingAs($company->owner)->put(route('admin.roles.update', $yard->id), ['name' => 'Yard', 'permissions' => []])->assertForbidden();
        $this->actingAs($company->owner)->delete(route('admin.roles.destroy', $yard->id))->assertForbidden();

        expect($yard->fresh()->permissions()->pluck('name')->all())->toBe(['drivers.view'])
            ->and(Role::query()->where('team_id', $company->id)->where('name', 'Yard 2')->exists())->toBeFalse();
    });
});

describe('CompanyRoleSync', function () {
    /** A permission the template grants is missing, as after a catalogue change. */
    function dropGrant(Company $company, string $role, string $permission): void
    {
        DB::table('role_has_permissions')
            ->where('role_id', builtInRole($company, $role)->id)
            ->where('permission_id', DB::table('permissions')->where('guard_name', 'web')->where('name', $permission)->value('id'))
            ->delete();
    }

    it('brings one company up to the templates, keeps edited roles, and leaves other companies alone', function () {
        $a = Actors::company();
        $b = Actors::company();
        foreach ([$a, $b] as $company) {
            dropGrant($company, 'Owner', 'billing.manage');
            dropGrant($company, 'Administrator', 'drivers.view');
        }
        builtInRole($a, 'Administrator')->forceFill(['customized_at' => now()])->save();
        $before = roleSnapshot($b);

        expect(app(CompanyRoleSync::class)->sync($a->id))->toBe(1);

        expect(builtInRole($a, 'Owner')->permissions()->count())->toBe(count(PermissionCatalogue::TENANT))
            ->and(builtInRole($a, 'Administrator')->permissions()->where('name', 'drivers.view')->exists())->toBeFalse()
            ->and(roleSnapshot($b))->toBe($before);

        $this->artisan('roles:sync-company')->assertSuccessful();

        expect(builtInRole($b, 'Owner')->permissions()->count())->toBe(count(PermissionCatalogue::TENANT))
            ->and(builtInRole($b, 'Administrator')->permissions()->where('name', 'drivers.view')->exists())->toBeTrue()
            ->and(app(CompanyRoleSync::class)->sync($a->id))->toBe(0);
    });

    it('never touches custom roles', function () {
        $company = Actors::company();
        $this->actingAs($company->owner)->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => ['vehicles.view']]);

        app(CompanyRoleSync::class)->sync($company->id);

        expect(builtInRole($company, 'Yard')->permissions()->pluck('name')->all())->toBe(['vehicles.view']);
    });
});
