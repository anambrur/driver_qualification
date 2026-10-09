<?php

use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\Support\Actors;

/*
| USR-06: RoleController echoed `$e->getMessage()` (SQL errors, class names) to the UI via toastr.
*/

function roleFlashes(): array
{
    return collect(session('flasher::envelopes', []))
        // php-flasher >= 2.6 stores each envelope serialized
        ->map(fn ($envelope) => (is_string($envelope) ? unserialize($envelope) : $envelope)->getMessage())
        ->all();
}

describe('USR-06: role errors are logged, not shown', function () {
    it('shows a generic message when creating a role fails', function () {
        $admin = Actors::superAdmin();
        Log::spy();
        Role::creating(fn () => throw new RuntimeException('SQLSTATE[HY000]: secret table detail'));

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), ['name' => 'dispatcher'])
            ->assertRedirect();

        $messages = implode(' ', roleFlashes());
        expect($messages)->not->toContain('SQLSTATE')
            ->and($messages)->toContain('Failed to create role');
        Log::shouldHaveReceived('error')->once();
    });

    it('shows a generic message when updating a role fails', function () {
        $admin = Actors::superAdmin();
        $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
        Log::spy();
        Role::updating(fn () => throw new RuntimeException('SQLSTATE[HY000]: secret table detail'));

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role->id), ['name' => 'dispatcher-2'])
            ->assertRedirect();

        $messages = implode(' ', roleFlashes());
        expect($messages)->not->toContain('SQLSTATE')
            ->and($messages)->toContain('Failed to update role');
        Log::shouldHaveReceived('error')->once();
    });

    it('returns 404 for an unknown role instead of a toast with the model class', function () {
        $this->actingAs(Actors::superAdmin())
            ->put(route('admin.roles.update', 999999), ['name' => 'ghost'])
            ->assertNotFound();
    });
});
