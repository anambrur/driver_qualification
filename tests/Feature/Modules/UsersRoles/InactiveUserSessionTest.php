<?php

use Tests\Support\Actors;

/*
| USR-05: `status = inactive` was only checked at login, so a user who was already logged in
| kept working after being suspended.
*/

describe('USR-05: suspending a user ends their existing session', function () {
    it('logs out a user who was deactivated after logging in', function () {
        $user = Actors::superAdmin();

        $this->actingAs($user)->get(route('users.index'))->assertOk();

        $user->update(['status' => 'inactive']);

        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('logs out a user suspended by an admin on their next request', function () {
        $victim = Actors::companyOwner();

        $this->actingAs(Actors::superAdmin())
            ->put(route('users.update', $victim->id), [
                'name' => $victim->name,
                'email' => $victim->email,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('users.index'));

        $this->actingAs($victim->fresh())->get(route('billing.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('leaves active users alone', function () {
        $this->actingAs(Actors::superAdmin())->get(route('users.index'))->assertOk();
        $this->assertAuthenticated();
    });
});
