<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Route;
use Tests\Support\Actors;

/*
| USR-02: users.2fa.reset, users.suspend and users.unsuspend pointed at controller methods that
| did not exist, so every call was a 500. The app has no 2FA, so that route is removed.
| Changed by design (10 §5): suspend/unsuspend are now deactivate/reactivate, and users are
| never hard-deleted.
*/

describe('USR-02: deactivate / reactivate routes', function () {
    it('deactivates a user and audits it', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)
            ->post(route('users.deactivate', $target->id))
            ->assertRedirect(route('users.index'));

        expect($target->fresh()->status)->toBe('inactive')
            ->and(AuditLog::where('action', 'user.deactivated')->where('subject_id', $target->id)->where('company_id', $company->id)->exists())->toBeTrue();
    });

    it('reactivates a user', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company, ['status' => 'inactive']);

        $this->actingAs($company->owner)
            ->post(route('users.reactivate', $target->id))
            ->assertRedirect(route('users.index'));

        expect($target->fresh()->status)->toBe('active');
    });

    it('does not let the Owner deactivate themselves', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)->post(route('users.deactivate', $owner->id))->assertForbidden();

        expect($owner->fresh()->status)->toBe('active');
        $this->assertAuthenticated();
    });

    it('returns 404 for an unknown user', function () {
        $this->actingAs(Actors::companyOwner())->post(route('users.deactivate', 999999))->assertNotFound();
    });

    it('has no route to hard-delete a user', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        expect(Route::has('users.destroy'))->toBeFalse();

        $this->actingAs($company->owner)->delete("/users/{$target->id}")->assertStatus(405);
        expect($target->fresh())->not->toBeNull();
    });

    it('no longer exposes a 2FA reset route for a feature the app does not have', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        expect(Route::has('users.2fa.reset'))->toBeFalse();

        $this->actingAs($company->owner)
            ->delete("/users/{$target->id}/2fa")
            ->assertNotFound();
    });
});
