<?php

use App\Models\Role;
use App\Services\Authorization\RoleService;
use Tests\Support\Actors;

/*
| USR-06: RoleController echoed `$e->getMessage()` (SQL errors, class names) to the UI via toastr.
| The controller no longer catches; with debug off the error page shows no internals.
*/

describe('USR-06: role errors are not shown to the user', function () {
    it('does not leak the exception message when creating a role fails', function () {
        config(['app.debug' => false]);
        $owner = Actors::companyOwner();
        Role::creating(fn () => throw new RuntimeException('SQLSTATE[HY000]: secret table detail'));

        $this->actingAs($owner)
            ->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => []])
            ->assertServerError()
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('secret table detail');
    });

    it('does not leak the exception message when updating a role fails', function () {
        config(['app.debug' => false]);
        $company = Actors::company();
        $role = app(RoleService::class)->create($company->owner, 'Yard', []);
        Role::updating(fn () => throw new RuntimeException('SQLSTATE[HY000]: secret table detail'));

        $this->actingAs($company->owner)
            ->put(route('admin.roles.update', $role->id), ['name' => 'Yard 2', 'permissions' => []])
            ->assertServerError()
            ->assertDontSee('SQLSTATE');
    });

    it('returns 404 for an unknown role instead of a toast with the model class', function () {
        $this->actingAs(Actors::companyOwner())
            ->put(route('admin.roles.update', 999999), ['name' => 'ghost', 'permissions' => []])
            ->assertNotFound()
            ->assertDontSee('App\\Models\\Role');
    });
});
