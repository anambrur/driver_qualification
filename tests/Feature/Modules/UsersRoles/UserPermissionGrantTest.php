<?php

use App\Authorization\TeamScope;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\Actors;

/*
| Direct per-user grants (06 §8 rule 2) and the Extra permissions card on the user edit page:
| only permissions the actor holds, never to yourself or the Owner, always in the company's team.
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

describe('extra permissions card', function () {
    it('shows role permissions read-only and offers only what the actor may grant', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)
            ->get(route('users.edit', $target->id))
            ->assertOk()
            ->assertSee('Extra permissions')
            ->assertSee('· Dispatcher')
            ->assertSee('action="'.route('users.permissions.store', $target->id).'"', false)
            ->assertSee('<option value="drivers.edit"', false)
            ->assertDontSee('<option value="trips.view"', false);
    });

    it('leaves out what an Administrator does not hold', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs(Actors::companyAdmin($company))
            ->get(route('users.edit', $target->id))
            ->assertOk()
            ->assertSee('<option value="drivers.edit"', false)
            ->assertDontSee('<option value="billing.manage"', false);
    });

    it('is hidden without permissions.grant', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs(Actors::memberWithPermissions($company, ['users.view', 'users.edit']))
            ->get(route('users.edit', $target->id))
            ->assertOk()
            ->assertDontSee('Extra permissions')
            ->assertDontSee('Custom roles feature');
    });

    it('is hidden without the Custom roles feature, with an upgrade line for the Owner only', function () {
        $company = Actors::withPlan(Actors::company(), []);
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)
            ->get(route('users.edit', $target->id))
            ->assertOk()
            ->assertDontSee('action="'.route('users.permissions.store', $target->id).'"', false)
            ->assertSee('Custom roles feature')
            ->assertSee(route('billing.index'));

        $this->actingAs(Actors::companyAdmin($company))
            ->get(route('users.edit', $target->id))
            ->assertOk()
            ->assertDontSee('Custom roles feature');

        $this->actingAs($company->owner)
            ->get(route('users.edit', $company->owner->id))
            ->assertOk()
            ->assertDontSee('Custom roles feature');
    });

    it('is hidden on your own page and on the Owner\'s page', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        $this->actingAs($admin)->get(route('users.edit', $admin->id))->assertOk()->assertDontSee('Extra permissions');
        $this->actingAs($admin)->get(route('users.edit', $company->owner->id))->assertOk()->assertDontSee('Extra permissions');
    });

    it('grants and revokes through the card, with both audit rows', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)
            ->from(route('users.edit', $target->id))
            ->post(route('users.permissions.store', $target->id), ['permission' => 'drivers.edit'])
            ->assertRedirect(route('users.edit', $target->id));

        $this->actingAs($company->owner)
            ->get(route('users.edit', $target->id))
            ->assertSee('action="'.route('users.permissions.destroy', [$target->id, 'drivers.edit']).'"', false)
            ->assertSee('data-confirm="Revoke Drivers: Edit?"', false)
            ->assertDontSee('<option value="drivers.edit"', false);

        $this->actingAs($company->owner)
            ->get(route('users.index'))
            ->assertSee('+1 extra');

        $this->actingAs($company->owner)
            ->delete(route('users.permissions.destroy', [$target->id, 'drivers.edit']))
            ->assertRedirect();

        expect(DB::table('model_has_permissions')->where('model_id', $target->id)->exists())->toBeFalse();

        foreach (['permission.granted', 'permission.revoked'] as $action) {
            expect(AuditLog::where('action', $action)->where('company_id', $company->id)->where('subject_id', $target->id)->exists())->toBeTrue();
        }

        $this->actingAs($company->owner)->get(route('users.index'))->assertDontSee('+1 extra');
    });

    it('shows no Revoke for a grant the actor does not hold', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)->post(route('users.permissions.store', $target->id), ['permission' => 'billing.manage']);

        $this->actingAs(Actors::companyAdmin($company))
            ->get(route('users.edit', $target->id))
            ->assertSee('Only someone who holds this can revoke it')
            ->assertDontSee('action="'.route('users.permissions.destroy', [$target->id, 'billing.manage']).'"', false);
    });

    it('locks the Owner', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        $this->actingAs($admin)
            ->post(route('users.permissions.store', $company->owner->id), ['permission' => 'drivers.edit'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->delete(route('users.permissions.destroy', [$company->owner->id, 'drivers.edit']))
            ->assertForbidden();

        expect(DB::table('model_has_permissions')->where('model_id', $company->owner->id)->exists())->toBeFalse();
    });

    it('is a 404 for another company\'s user', function () {
        $other = Actors::dispatcher(Actors::company());
        $owner = Actors::company()->owner;

        $this->actingAs($owner)->get(route('users.edit', $other->id))->assertNotFound();
        $this->actingAs($owner)->post(route('users.permissions.store', $other->id), ['permission' => 'drivers.edit'])->assertNotFound();
        $this->actingAs($owner)->delete(route('users.permissions.destroy', [$other->id, 'drivers.edit']))->assertNotFound();

        expect(DB::table('model_has_permissions')->where('model_id', $other->id)->exists())->toBeFalse();
    });

    it('refuses grants and revokes in a read-only company', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        TeamScope::run($company->id, fn () => $target->givePermissionTo('drivers.edit'));
        $company = Actors::readOnly($company);

        $this->actingAs($company->owner)
            ->post(route('users.permissions.store', $target->id), ['permission' => 'drivers.view'])
            ->assertForbidden();
        $this->actingAs($company->owner)
            ->delete(route('users.permissions.destroy', [$target->id, 'drivers.edit']))
            ->assertForbidden();

        expect(DB::table('model_has_permissions')->where('model_id', $target->id)->pluck('permission_id')->count())->toBe(1);
    });
});
