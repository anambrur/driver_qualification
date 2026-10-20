<?php

use App\Models\Company;
use App\Models\Role;
use App\Services\Authorization\RoleService;
use App\Support\Ui\Toast;
use Tests\Support\Actors;

/*
| USR-03, changed by design (11 §5): roles are per team. Editing company A's role never changes
| company B's, locked roles can't be changed, and another company's role id is a 404.
*/

function companyRole(Company $company, string $name): Role
{
    return Role::query()->where('team_id', $company->id)->where('name', $name)->firstOrFail();
}

function rolePermissionNames(Role $role): array
{
    return $role->fresh()->permissions()->pluck('name')->sort()->values()->all();
}

function roleFlashes(): array
{
    return collect(session(Toast::SESSION_KEY, []))->pluck('message')->all();
}

describe('per-company roles', function () {
    it('lets the Owner create a role in their own team', function () {
        $company = Actors::company();

        $this->actingAs($company->owner)
            ->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => ['vehicles.view', 'trailers.view']])
            ->assertRedirect(route('admin.roles.index'));

        expect(rolePermissionNames(companyRole($company, 'Yard')))->toBe(['trailers.view', 'vehicles.view']);
    });

    it('lets two companies use the same role name', function () {
        $a = Actors::company();
        $b = Actors::company();

        foreach ([$a, $b] as $company) {
            $this->actingAs($company->owner)
                ->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => ['vehicles.view']])
                ->assertRedirect(route('admin.roles.index'));
        }

        expect(companyRole($a, 'Yard')->id)->not->toBe(companyRole($b, 'Yard')->id);
    });

    it('rejects a duplicate role name within the same company', function () {
        $company = Actors::company();

        $this->actingAs($company->owner)
            ->post(route('admin.roles.store'), ['name' => 'Dispatcher', 'permissions' => []])
            ->assertSessionHasErrors('name');
    });

    it("never changes another company's role when editing your own", function () {
        $a = Actors::company();
        $b = Actors::company();
        $before = rolePermissionNames(companyRole($b, 'Dispatcher'));

        $this->actingAs($a->owner)
            ->put(route('admin.roles.update', companyRole($a, 'Dispatcher')->id), ['name' => 'Dispatcher', 'permissions' => ['trips.view']])
            ->assertRedirect(route('admin.roles.index'));

        expect(rolePermissionNames(companyRole($a, 'Dispatcher')))->toBe(['trips.view'])
            ->and(rolePermissionNames(companyRole($b, 'Dispatcher')))->toBe($before);
    });

    it("returns 404 for another company's role on every role route", function (string $method, string $route) {
        $foreign = companyRole(Actors::company(), 'Dispatcher');
        $before = rolePermissionNames($foreign);

        $this->actingAs(Actors::companyOwner())
            ->call($method, route($route, $foreign->id), ['name' => 'Hacked', 'permissions' => []])
            ->assertNotFound();

        expect($foreign->fresh()?->name)->toBe('Dispatcher')
            ->and(rolePermissionNames($foreign))->toBe($before);
    })->with([
        'edit' => ['GET', 'admin.roles.edit'],
        'update' => ['PUT', 'admin.roles.update'],
        'destroy' => ['DELETE', 'admin.roles.destroy'],
    ]);

    it('lists only the own company\'s roles and hides the support roles', function () {
        $company = Actors::company();
        $other = Actors::company();
        $this->actingAs($other->owner)->post(route('admin.roles.store'), ['name' => 'Other Yard', 'permissions' => []]);

        $this->actingAs($company->owner)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Dispatcher')
            ->assertDontSee('Other Yard')
            ->assertDontSee('Support (read)');
    });
});

describe('locked and system roles', function () {
    it('refuses to edit or delete a locked role', function (string $name) {
        $company = Actors::company();
        $role = companyRole($company, $name);
        $before = rolePermissionNames($role);

        $this->actingAs($company->owner)->get(route('admin.roles.edit', $role->id))->assertForbidden();
        $this->actingAs($company->owner)
            ->put(route('admin.roles.update', $role->id), ['name' => $name, 'permissions' => ['trips.view']])
            ->assertForbidden();
        $this->actingAs($company->owner)->delete(route('admin.roles.destroy', $role->id))->assertForbidden();

        expect(rolePermissionNames($role))->toBe($before);
    })->with(['Owner', 'Driver']);

    it('refuses to rename a system role but lets its permissions change', function () {
        $company = Actors::company();
        $role = companyRole($company, 'Dispatcher');

        $this->actingAs($company->owner)
            ->put(route('admin.roles.update', $role->id), ['name' => 'Renamed', 'permissions' => ['trips.view']])
            ->assertForbidden();

        expect($role->fresh()->name)->toBe('Dispatcher');
    });

    it('refuses to delete a system role that is still assigned', function () {
        $company = Actors::company();
        Actors::dispatcher($company);

        $this->actingAs($company->owner)
            ->delete(route('admin.roles.destroy', companyRole($company, 'Dispatcher')->id))
            ->assertForbidden();

        expect(Role::query()->where('team_id', $company->id)->where('name', 'Dispatcher')->exists())->toBeTrue();
    });

    it('deletes a custom role and redirects with a success message', function () {
        $company = Actors::company();
        $this->actingAs($company->owner)->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => []]);
        $role = companyRole($company, 'Yard');

        $this->actingAs($company->owner)
            ->delete(route('admin.roles.destroy', $role->id))
            ->assertRedirect(route('admin.roles.index'));

        expect(Role::query()->whereKey($role->id)->exists())->toBeFalse()
            ->and(roleFlashes())->toContain('Role deleted successfully');
    });

    it('returns 404 for an unknown role', function () {
        $this->actingAs(Actors::companyOwner())
            ->delete(route('admin.roles.destroy', 999999))
            ->assertNotFound();
    });

    it('does not let a user without roles.delete delete a role', function () {
        $company = Actors::company();
        $this->actingAs($company->owner)->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => []]);
        $role = companyRole($company, 'Yard');

        $this->actingAs(Actors::dispatcher($company))
            ->delete(route('admin.roles.destroy', $role->id))
            ->assertForbidden();

        expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
    });

    it('redirects the show route to the edit page', function () {
        $company = Actors::company();
        $role = companyRole($company, 'Dispatcher');

        $this->actingAs($company->owner)
            ->get(route('admin.roles.show', $role->id))
            ->assertRedirect(route('admin.roles.edit', $role->id));
    });
});

describe('grant only what you hold (06 §8 rule 2)', function () {
    it('refuses to create a role with a permission the actor lacks', function () {
        $company = Actors::company();

        $this->actingAs(Actors::companyAdmin($company))
            ->post(route('admin.roles.store'), ['name' => 'Payer', 'permissions' => ['billing.manage']])
            ->assertForbidden();

        expect(Role::query()->where('team_id', $company->id)->where('name', 'Payer')->exists())->toBeFalse();
    });

    it('rejects a permission that is not in the tenant catalogue', function () {
        $this->actingAs(Actors::companyOwner())
            ->post(route('admin.roles.store'), ['name' => 'Platform', 'permissions' => ['platform.staff.manage']])
            ->assertSessionHasErrors('permissions.0');
    });

    it('only shows held permissions on the role form', function () {
        $company = Actors::company();

        $this->actingAs(Actors::companyAdmin($company))
            ->get(route('admin.roles.create'))
            ->assertOk()
            ->assertSee('value="billing.view"', false)
            ->assertDontSee('value="billing.manage"', false);
    });
});

describe('roles the actor cannot fully grant', function () {
    it('refuses the edit page and hides Edit for a role with permissions the actor lacks', function () {
        $company = Actors::company();
        app(RoleService::class)->create($company->owner, 'Finance', ['billing.manage']);
        $role = companyRole($company, 'Finance');
        $admin = Actors::companyAdmin($company);

        $this->actingAs($admin)->get(route('admin.roles.edit', $role->id))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.roles.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('admin.roles.edit', $role->id).'"', false)
            ->assertSee('href="'.route('admin.roles.edit', companyRole($company, 'Dispatcher')->id).'"', false);
    });
});

describe('roles index: delete button', function () {
    it('renders role names as escaped data attributes, not inside an inline onclick', function () {
        $company = Actors::company();
        $payload = "x');alert(1);//<img src=x onerror=alert(2)>";
        $this->actingAs($company->owner)->post(route('admin.roles.store'), ['name' => $payload, 'permissions' => []]);
        $role = companyRole($company, $payload);

        $html = $this->actingAs($company->owner)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->getContent();

        expect($html)->not->toContain('onclick="deleteRole(')
            ->and($html)->not->toContain('<img src=x onerror=alert(2)>')
            ->and($html)->toContain('data-confirm="Delete the '.e($payload).' role?"')
            ->and($html)->toContain('action="'.route('admin.roles.destroy', $role->id).'"');
    });

    it('hides the delete button for a system role that is still assigned', function () {
        $company = Actors::company();
        Actors::dispatcher($company);

        $html = $this->actingAs($company->owner)->get(route('admin.roles.index'))->getContent();

        expect($html)->not->toContain('action="'.route('admin.roles.destroy', companyRole($company, 'Dispatcher')->id).'"')
            ->and($html)->toContain('action="'.route('admin.roles.destroy', companyRole($company, 'Office / Compliance')->id).'"');
    });

    it('hides the delete button for locked roles', function () {
        $company = Actors::company();

        $html = $this->actingAs($company->owner)
            ->get(route('admin.roles.index'))
            ->getContent();

        foreach (['Owner', 'Driver'] as $name) {
            expect($html)->not->toContain('action="'.route('admin.roles.destroy', companyRole($company, $name)->id).'"');
        }
    });
});
