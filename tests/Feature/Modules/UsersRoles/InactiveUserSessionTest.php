<?php

use Tests\Support\Actors;

/*
| USR-05: `status = inactive` was only checked at login, so a user who was already logged in
| kept working after being deactivated.
*/

describe('USR-05: deactivating a user ends their existing session', function () {
    it('logs out a user who was deactivated after logging in', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        $this->actingAs($admin)->get(route('users.index'))->assertOk();

        $admin->update(['status' => 'inactive']);

        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('logs out a user deactivated by the Owner on their next request', function () {
        $company = Actors::company();
        $victim = Actors::companyAdmin($company);

        $this->actingAs($company->owner)
            ->post(route('users.deactivate', $victim->id))
            ->assertRedirect(route('users.index'));

        $this->actingAs($victim->fresh())->get(route('users.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('leaves active users alone', function () {
        $this->actingAs(Actors::companyOwner())->get(route('users.index'))->assertOk();
        $this->assertAuthenticated();
    });
});
