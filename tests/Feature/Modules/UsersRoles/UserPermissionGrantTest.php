<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\Actors;

/*
| Direct per-user grants (06 §8 rule 2), backend for the Phase 1.6 UI: only permissions the actor
| holds, never to yourself, always in the company's team.
*/

describe('direct permission grants', function () {
    it('grants and revokes a held permission in the company team, with an audit trail', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)
            ->post(route('users.permissions.store', $target->id), ['permission' => 'drivers.edit'])
            ->assertRedirect();

        $row = DB::table('model_has_permissions')->where('model_id', $target->id)->sole();
        expect($row->team_id)->toBe($company->id)
            ->and(AuditLog::where('action', 'permission.granted')->where('subject_id', $target->id)->exists())->toBeTrue();

        Actors::actingAsTenant($target->fresh());
        expect($target->fresh()->can('drivers.edit'))->toBeTrue();

        $this->actingAs($company->owner)
            ->delete(route('users.permissions.destroy', [$target->id, 'drivers.edit']))
            ->assertRedirect();

        expect(DB::table('model_has_permissions')->where('model_id', $target->id)->exists())->toBeFalse();
    });

    it('refuses a permission the actor does not hold', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs(Actors::companyAdmin($company))
            ->post(route('users.permissions.store', $target->id), ['permission' => 'billing.manage'])
            ->assertForbidden();

        expect(DB::table('model_has_permissions')->where('model_id', $target->id)->exists())->toBeFalse();
    });

    it('refuses a grant to yourself', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        $this->actingAs($admin)
            ->post(route('users.permissions.store', $admin->id), ['permission' => 'drivers.edit'])
            ->assertForbidden();
    });

    it('refuses a user without permissions.grant', function () {
        $company = Actors::company();
        $target = Actors::officeUser($company);

        $this->actingAs(Actors::dispatcher($company))
            ->post(route('users.permissions.store', $target->id), ['permission' => 'trips.view'])
            ->assertForbidden();
    });

    it('rejects a permission outside the tenant catalogue', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)
            ->post(route('users.permissions.store', $target->id), ['permission' => 'platform.staff.manage'])
            ->assertSessionHasErrors('permission');
    });
});
