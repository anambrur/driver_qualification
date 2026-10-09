<?php

use Illuminate\Support\Facades\Route;
use Tests\Support\Actors;

/*
| USR-02: users.2fa.reset, users.suspend and users.unsuspend pointed at controller methods that
| did not exist, so every call was a 500. The app has no 2FA, so that route is removed;
| suspend/unsuspend set the same `status` the edit form and the login check already use.
*/

describe('USR-02: suspend / unsuspend / 2fa routes', function () {
    it('suspends a user instead of failing with a 500', function () {
        $target = Actors::companyOwner();

        $this->actingAs(Actors::superAdmin())
            ->post(route('users.suspend', $target->id))
            ->assertRedirect(route('users.index'));

        expect($target->fresh()->status)->toBe('inactive');
    });

    it('unsuspends a user instead of failing with a 500', function () {
        $target = Actors::companyOwner(['status' => 'inactive']);

        $this->actingAs(Actors::superAdmin())
            ->post(route('users.unsuspend', $target->id))
            ->assertRedirect(route('users.index'));

        expect($target->fresh()->status)->toBe('active');
    });

    it('does not let a super-admin suspend themselves', function () {
        $admin = Actors::superAdmin();

        $this->actingAs($admin)
            ->post(route('users.suspend', $admin->id))
            ->assertRedirect(route('users.index'));

        expect($admin->fresh()->status)->toBe('active');
    });

    it('does not let a user deactivate themselves from the edit form', function () {
        $admin = Actors::superAdmin();

        $this->actingAs($admin)
            ->from(route('users.edit', $admin->id))
            ->put(route('users.update', $admin->id), [
                'name' => $admin->name,
                'email' => $admin->email,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('users.edit', $admin->id));

        expect($admin->fresh()->status)->toBe('active');
        $this->assertAuthenticated();
    });

    it('returns 404 for an unknown user', function () {
        $this->actingAs(Actors::superAdmin())->post(route('users.suspend', 999999))->assertNotFound();
    });

    it('no longer exposes a 2FA reset route for a feature the app does not have', function () {
        $target = Actors::companyOwner();

        expect(Route::has('users.2fa.reset'))->toBeFalse();

        $this->actingAs(Actors::superAdmin())
            ->delete("/users/{$target->id}/2fa")
            ->assertNotFound();
    });
});
