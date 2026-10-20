<?php

use App\Authorization\PermissionCatalogue;
use App\Authorization\RoleTemplates;
use App\Models\AuditLog;
use App\Models\Role;
use Database\Factories\PlatformUserFactory;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Actors;

/*
| Platform roles (04 §3): Platform Owner is locked with every permission; the other built-in
| roles can be edited and reset; custom roles can be created, assigned and deleted. Only
| platform.staff.manage, only permissions you hold, every change audited.
*/

beforeEach(function () {
    Notification::fake();
});

function platformRole(string $systemKey): Role
{
    return Role::query()->where('team_id', 0)->where('system_key', $systemKey)->firstOrFail();
}

it('describes every platform permission for the role screens', function () {
    expect(array_keys(PermissionCatalogue::PLATFORM_DETAILS))->toEqualCanonicalizing(PermissionCatalogue::platformNames());
});

it('creates a custom role and gives its permissions to the staff who hold it', function () {
    $owner = Actors::platformOwner();

    $this->actingAs($owner, 'platform')
        ->post(route('platform.roles.store'), ['name' => 'Content Editor', 'description' => 'Homepage only', 'permissions' => ['platform.content.manage']])
        ->assertRedirect(route('platform.roles.index'));

    $role = Role::query()->where('team_id', 0)->where('name', 'Content Editor')->sole();
    expect([$role->guard_name, $role->is_system, $role->permissions->pluck('name')->all()])->toBe(['platform', false, ['platform.content.manage']])
        ->and(AuditLog::where('action', 'platform_role.created')->count())->toBe(1);

    $editor = PlatformUserFactory::new()->create();
    $this->put(route('platform.staff.update', $editor), ['name' => $editor->name, 'email' => $editor->email, 'roles' => [$role->id]])
        ->assertSessionHasNoErrors();

    $this->actingAs($editor->fresh(), 'platform')->get(route('platform.content.faqs.index'))->assertOk();
    $this->get(route('platform.plans.index'))->assertForbidden();
});

it('edits a built-in role, marks it edited, and the seeder keeps the edit until reset', function () {
    $owner = Actors::platformOwner();
    $support = platformRole('platform_support');

    $this->actingAs($owner, 'platform')
        ->put(route('platform.roles.update', $support->id), ['name' => 'Renamed', 'permissions' => ['platform.companies.view', 'platform.audit.view']])
        ->assertRedirect(route('platform.roles.edit', $support->id));

    $support->refresh();
    expect($support->name)->toBe('Support Agent')
        ->and($support->customized_at)->not->toBeNull()
        ->and($support->permissions->pluck('name')->sort()->values()->all())->toBe(['platform.audit.view', 'platform.companies.view']);

    (new PlatformRoleSeeder)->run();
    expect(platformRole('platform_support')->permissions()->pluck('name')->sort()->values()->all())->toBe(['platform.audit.view', 'platform.companies.view']);

    $this->post(route('platform.roles.reset', $support->id))->assertSessionHasNoErrors();

    $support->refresh();
    expect($support->customized_at)->toBeNull()
        ->and($support->permissions()->pluck('name')->sort()->values()->all())->toBe(RoleTemplates::platformPermissionsFor('platform_support'))
        ->and(AuditLog::whereIn('action', ['platform_role.updated', 'platform_role.reset'])->count())->toBe(2);
});

it('never edits Platform Owner', function () {
    $owner = Actors::platformOwner();
    $ownerRole = platformRole('platform_owner');

    $this->actingAs($owner, 'platform')->get(route('platform.roles.edit', $ownerRole->id))->assertOk()->assertSee('Locked role');

    $this->put(route('platform.roles.update', $ownerRole->id), ['name' => 'Platform Owner', 'permissions' => []])
        ->assertSessionHasErrors('permissions');

    expect($ownerRole->permissions()->count())->toBe(count(PermissionCatalogue::PLATFORM));
});

it('deletes a custom role only when nobody holds it', function () {
    $owner = Actors::platformOwner();
    $this->actingAs($owner, 'platform')->post(route('platform.roles.store'), ['name' => 'Temp', 'permissions' => ['platform.audit.view']]);
    $role = Role::query()->where('team_id', 0)->where('name', 'Temp')->sole();
    $holder = PlatformUserFactory::new()->create();
    $this->put(route('platform.staff.update', $holder), ['name' => $holder->name, 'email' => $holder->email, 'roles' => [$role->id]]);

    $this->delete(route('platform.roles.destroy', $role->id))->assertSessionHasErrors('role');
    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();

    $this->put(route('platform.staff.update', $holder), ['name' => $holder->name, 'email' => $holder->email, 'roles' => [platformRole('auditor')->id]]);
    $this->delete(route('platform.roles.destroy', $role->id))->assertRedirect(route('platform.roles.index'));

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});

it('refuses built-in role deletion, duplicate names and unknown permissions', function () {
    $owner = Actors::platformOwner();

    $this->actingAs($owner, 'platform')->delete(route('platform.roles.destroy', platformRole('auditor')->id))->assertSessionHasErrors('role');
    $this->post(route('platform.roles.store'), ['name' => 'Auditor', 'permissions' => ['users.view']])
        ->assertSessionHasErrors(['name', 'permissions.0']);
});

it('keeps role screens to staff with platform.staff.manage', function () {
    $this->actingAs(Actors::platformStaff('platform_admin'), 'platform')->get(route('platform.roles.index'))->assertForbidden();
    $this->post(route('platform.roles.store'), ['name' => 'Sneaky', 'permissions' => ['platform.staff.manage']])->assertForbidden();

    expect(Role::query()->where('name', 'Sneaky')->exists())->toBeFalse();
});

it('renders the roles list and every role form', function () {
    $this->actingAs(Actors::platformOwner(), 'platform');

    $this->get(route('platform.roles.index'))->assertOk()->assertSee('Built-in')->assertSee('Billing Manager');
    $this->get(route('platform.roles.create'))->assertOk()->assertSee('Public site content');
    foreach (array_keys(RoleTemplates::PLATFORM_ROLES) as $key) {
        $this->get(route('platform.roles.edit', platformRole($key)->id))->assertOk();
    }
    $this->get(route('platform.staff.create'))->assertOk()->assertSee('Every permission')->assertSee('Manage roles');
});
