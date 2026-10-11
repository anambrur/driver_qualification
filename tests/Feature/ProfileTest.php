<?php

use Tests\Support\Actors;

test('profile page is displayed', function () {
    $user = Actors::companyOwner();

    $response = $this
        ->actingAs($user)
        ->get('/admin/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = Actors::companyOwner();

    $response = $this
        ->actingAs($user)
        ->patch('/admin/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = Actors::companyOwner();

    $response = $this
        ->actingAs($user)
        ->patch('/admin/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can close their account, which deactivates it (PO-17)', function () {
    $user = Actors::companyOwner();

    $response = $this
        ->actingAs($user)
        ->delete('/admin/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertSame('inactive', $user->fresh()->status);
});

test('correct password must be provided to delete account', function () {
    $user = Actors::companyOwner();

    $response = $this
        ->actingAs($user)
        ->from('/admin/profile')
        ->delete('/admin/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/admin/profile');

    $this->assertNotNull($user->fresh());
});
