<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Support\Actors;

/*
| USR-03: RoleController::destroy returned an empty response and let anyone with roles.delete
| delete the `super-admin` / `company` roles that the code checks by name (lockout).
| show() was an empty method (blank 200).
*/

function flashedMessages(): array
{
    return collect(session('flasher::envelopes', []))
        ->map(fn ($envelope) => $envelope->getMessage())
        ->all();
}

describe('USR-03: system roles are protected', function () {
    it('refuses to delete a system role', function (string $name) {
        Actors::seedAccessControl();
        $role = Role::findByName($name, 'web');
        $owner = Actors::companyOwner();

        $this->actingAs(Actors::superAdmin())
            ->delete(route('admin.roles.destroy', $role->id))
            ->assertRedirect(route('admin.roles.index'));

        expect(Role::query()->whereKey($role->id)->exists())->toBeTrue()
            ->and($owner->fresh()->hasRole('company'))->toBeTrue();
    })->with(['super-admin', 'company']);

    it('refuses to rename a system role', function (string $name) {
        Actors::seedAccessControl();
        $role = Role::findByName($name, 'web');
        $permissions = $role->permissions->pluck('name')->all();

        $this->actingAs(Actors::superAdmin())
            ->put(route('admin.roles.update', $role->id), ['name' => 'renamed', 'permissions' => $permissions])
            ->assertRedirect();

        expect($role->fresh()->name)->toBe($name);
    })->with(['super-admin', 'company']);

    it('keeps every permission on the super-admin role whatever the form sends', function () {
        $admin = Actors::superAdmin();
        $role = Role::findByName('super-admin', 'web');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role->id), ['name' => 'super-admin', 'permissions' => ['drivers.view']])
            ->assertRedirect(route('admin.roles.index'));

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role->id), ['name' => 'super-admin'])
            ->assertRedirect(route('admin.roles.index'));

        expect($role->fresh()->permissions()->count())->toBe(\Spatie\Permission\Models\Permission::count());
        $this->actingAs($admin->fresh())->get(route('admin.roles.index'))->assertOk();
    });

    it('still lets a super-admin change the permissions of the company role', function () {
        $admin = Actors::superAdmin();
        $role = Role::findByName('company', 'web');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role->id), ['name' => 'company', 'permissions' => ['drivers.view']])
            ->assertRedirect(route('admin.roles.index'));

        expect($role->fresh()->permissions->pluck('name')->all())->toBe(['drivers.view']);
    });

    it('shows the super-admin permissions as locked on the edit page', function () {
        $admin = Actors::superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.roles.edit', Role::findByName('super-admin', 'web')->id))
            ->assertOk()
            ->assertSee('The super-admin role always has every permission.');
    });

    it('deletes a custom role and redirects back with a success message', function () {
        Actors::seedAccessControl();
        $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);

        $this->actingAs(Actors::superAdmin())
            ->delete(route('admin.roles.destroy', $role->id))
            ->assertRedirect(route('admin.roles.index'));

        expect(Role::query()->whereKey($role->id)->exists())->toBeFalse()
            ->and(flashedMessages())->toContain('Role deleted successfully');
    });

    it('returns 404 when deleting an unknown role', function () {
        $this->actingAs(Actors::superAdmin())
            ->delete(route('admin.roles.destroy', 999999))
            ->assertNotFound();
    });

    it('does not let a user without roles.delete delete a role', function () {
        Actors::seedAccessControl();
        $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);

        $this->actingAs(Actors::companyOwner())
            ->delete(route('admin.roles.destroy', $role->id))
            ->assertForbidden();

        expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
    });

    it('redirects the show route instead of rendering a blank page', function () {
        Actors::seedAccessControl();
        $role = Role::findByName('company', 'web');

        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.roles.show', $role->id))
            ->assertRedirect(route('admin.roles.edit', $role->id));
    });
});

describe('roles index: delete button', function () {
    it('renders role names as escaped data attributes, not inside an inline onclick', function () {
        Actors::seedAccessControl();
        $payload = "x');alert(1);//<img src=x onerror=alert(2)>";
        $role = Role::create(['name' => $payload, 'guard_name' => 'web']);

        $html = $this->actingAs(Actors::superAdmin())
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->getContent();

        expect($html)->not->toContain('onclick="deleteRole(')
            ->and($html)->not->toContain('<img src=x onerror=alert(2)>')
            ->and($html)->toContain('data-name="'.e($payload).'"')
            ->and($html)->toContain('data-url="'.route('admin.roles.destroy', $role->id).'"');
    });

    it('hides the delete button for system roles', function () {
        Actors::seedAccessControl();

        $html = $this->actingAs(Actors::superAdmin())
            ->get(route('admin.roles.index'))
            ->getContent();

        foreach (['super-admin', 'company'] as $name) {
            expect($html)->not->toContain('data-url="'.route('admin.roles.destroy', Role::findByName($name, 'web')->id).'"');
        }
    });
});
