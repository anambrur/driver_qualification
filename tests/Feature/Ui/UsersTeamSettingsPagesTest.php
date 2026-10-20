<?php

use App\Models\PolicyPdf;
use App\Models\Role;
use App\Services\Platform\SupportSessionService;
use App\Services\Users\InvitationService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| UI-14 (phase 2, batch 7): users, invitations, support access, roles and the company settings on
| x-ui. Destructive actions ask in the shared confirm dialog; no native alert() or confirm() is left.
| Routes, permissions and form fields are unchanged.
*/

function assertBatch7Ui(string $html): void
{
    expect($html)->not->toMatch('/(?<![A-Za-z])(alert|confirm)\(/')
        ->not->toContain('showAppConfirm(')
        ->not->toMatch('/\b(text|bg|border)-(red|green|blue|gray|indigo)-\d/')
        ->not->toContain('shadow-theme-')
        ->not->toContain('dark:text-white/90');
}

function dispatcherRoleId(int $companyId): int
{
    return (int) Role::query()->where('team_id', $companyId)->where('name', 'Dispatcher')->value('id');
}

describe('users', function () {
    it('lists users with a confirm step before deactivating or revoking', function () {
        Mail::fake();
        $company = Actors::company();
        $dispatcher = Actors::dispatcher($company, ['name' => 'Dana Dispatch']);
        Actors::dispatcher($company, ['name' => 'Ina Inactive', 'status' => 'inactive']);
        app(TenantContext::class)->run($company->id, fn () => app(InvitationService::class)->invite($company->owner, 'pending@example.com', dispatcherRoleId($company->id)));

        $html = $this->actingAs($company->owner)->get(route('users.index'))->assertOk()
            ->assertSee(['Users', 'Invite User', 'Dana Dispatch', 'Pending invitations', 'pending@example.com'])
            ->assertSee('class="table table-stack"', false)
            ->assertSee('action="'.route('users.deactivate', $dispatcher->id).'"', false)
            ->assertSee('data-confirm="Deactivate Dana Dispatch?"', false)
            ->assertSee('data-confirm="Revoke this invitation?"', false)
            ->assertSee('name="search"', false)
            ->getContent();

        assertBatch7Ui($html);
    });

    it('pages the list with the themed pagination', function () {
        $company = Actors::company();
        foreach (range(1, 16) as $i) {
            Actors::plainUser($company);
        }

        $html = $this->actingAs($company->owner)->get(route('users.index'))->assertOk()
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->assertSee('rel="next"', false)
            ->getContent();

        expect($html)->not->toContain('text-gray-');
    });

    it('shows the empty state when no user matches', function () {
        $this->actingAs(Actors::companyOwner())->get(route('users.index', ['search' => 'nobody-matches']))->assertOk()
            ->assertSee(['No users found', 'No user matches these filters.'])
            ->assertSee('class="empty border', false);
    });

    it('renders the invite and edit forms with the same fields', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $invite = $this->actingAs($company->owner)->get(route('users.invite'))->assertOk()
            ->assertSee('action="'.route('users.invitations.store').'"', false)
            ->assertSee(['name="email"', 'name="role_id"'], false)
            ->assertSee('7 days')
            ->getContent();

        $edit = $this->actingAs($company->owner)->get(route('users.edit', $target->id))->assertOk()
            ->assertSee('action="'.route('users.update', $target->id).'"', false)
            ->assertSee(['name="name"', 'name="roles[]" value="'.dispatcherRoleId($company->id).'"', 'name="_method" value="PUT"'], false)
            ->getContent();

        assertBatch7Ui($invite);
        assertBatch7Ui($edit);
    });

    it('forbids each users page without its permission', function (string $route, array $permissions) {
        $company = Actors::company();
        $target = Actors::dispatcher($company);
        $member = Actors::memberWithPermissions($company, $permissions);

        $this->actingAs($member)
            ->get(route($route, $route === 'users.edit' ? $target->id : []))
            ->assertForbidden();
    })->with([
        'index' => ['users.index', ['drivers.view']],
        'invite' => ['users.invite', ['users.view']],
        'edit' => ['users.edit', ['users.view']],
    ]);
});

describe('support access', function () {
    beforeEach(function () {
        Notification::fake();
        $this->company = Actors::company();
        $this->sessions = app(SupportSessionService::class);
        $this->staff = Actors::platformOwner(['name' => 'Sam Support']);
    });

    it('asks before refusing a request or ending an approval', function () {
        $waiting = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 1: waiting');
        $approved = $this->sessions->request($this->staff, $this->company, 'read_write', 'Ticket 2: approved');
        $this->sessions->approveAsTenant($this->company->owner, $approved);

        $html = $this->actingAs($this->company->owner)->get(route('support-access.index'))->assertOk()
            ->assertSee(['Ticket 1: waiting', 'Waiting for you', 'Approved by you, waiting for the platform', 'View and change'])
            ->assertSee('action="'.route('support-access.approve', $waiting).'"', false)
            ->assertSee('data-confirm="Refuse this request?"', false)
            ->assertSee('action="'.route('support-access.revoke', $approved).'"', false)
            ->assertSee('data-confirm="Withdraw your approval?"', false)
            ->getContent();

        assertBatch7Ui($html);
    });

    it('shows the empty state without requests', function () {
        $this->actingAs($this->company->owner)->get(route('support-access.index'))->assertOk()
            ->assertSee('Platform support has not asked for access.')
            ->assertSee('class="empty border', false);
    });

    it('forbids anyone but the owner', function () {
        $this->actingAs(Actors::companyAdmin($this->company))->get(route('support-access.index'))->assertForbidden();
    });
});

describe('roles', function () {
    it('deletes a role only after the confirm dialog', function () {
        $company = Actors::company();
        $this->actingAs($company->owner)->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => ['vehicles.view']]);
        $yard = Role::query()->where('team_id', $company->id)->where('name', 'Yard')->sole();

        $html = $this->actingAs($company->owner)->get(route('admin.roles.index'))->assertOk()
            ->assertSee(['Roles and permissions', 'New role', 'Yard', 'Custom'])
            ->assertSee('action="'.route('admin.roles.destroy', $yard->id).'"', false)
            ->assertSee('data-confirm="Delete the Yard role?"', false)
            ->assertDontSee('deleteRoleForm', false)
            ->getContent();

        assertBatch7Ui($html);
    });

    it('renders the role form with the permission groups', function () {
        $company = Actors::company();
        $role = Role::query()->where('team_id', $company->id)->where('name', 'Dispatcher')->sole();

        $create = $this->actingAs($company->owner)->get(route('admin.roles.create'))->assertOk()
            ->assertSee('action="'.route('admin.roles.store').'"', false)
            ->assertSee(['>All</button>', 'name="permissions[]"', 'value="vehicles.view"'], false)
            ->getContent();

        $edit = $this->actingAs($company->owner)->get(route('admin.roles.edit', $role->id))->assertOk()
            ->assertSee('action="'.route('admin.roles.update', $role->id).'"', false)
            ->assertSee('Built-in roles keep their name.')
            ->assertSee('readonly', false)
            ->getContent();

        assertBatch7Ui($create);
        assertBatch7Ui($edit);
    });

    it('forbids each roles page without its permission', function (string $route, array $permissions) {
        $company = Actors::company();
        $role = Role::query()->where('team_id', $company->id)->where('name', 'Dispatcher')->sole();

        $this->actingAs(Actors::memberWithPermissions($company, $permissions))
            ->get(route($route, $route === 'admin.roles.edit' ? $role->id : []))
            ->assertForbidden();
    })->with([
        'index' => ['admin.roles.index', ['drivers.view']],
        'create' => ['admin.roles.create', ['roles.view']],
        'edit' => ['admin.roles.edit', ['roles.view']],
    ]);
});

describe('company settings', function () {
    it('renders the company form with the same fields', function () {
        $company = Actors::company(['company_name' => 'Acme Freight']);

        $html = $this->actingAs($company->owner)->get(route('admin.settings.company.edit', $company->id))->assertOk()
            ->assertSee(['Edit Company', 'Company Information', 'Company Logo', 'Address Information'])
            ->assertSee('action="'.route('admin.settings.company.update', $company->id).'"', false)
            ->assertSee(['enctype="multipart/form-data"', 'name="_method" value="PATCH"', 'id="logo" name="logo"'], false)
            ->assertSee('value="Acme Freight"', false)
            ->assertDontSee('console.log', false)
            ->getContent();

        assertBatch7Ui($html);
    });

    it('validates policy files in the dialog instead of alert()', function () {
        $company = Actors::company();
        PolicyPdf::create(['company_id' => $company->id, 'general_work_policy_file_id' => Files::stored($company->id, attributes: ['original_name' => 'work.pdf'])->id]);

        $html = $this->actingAs($company->owner)->get(route('admin.settings.policy.pdf'))->assertOk()
            ->assertSee(['Existing Policy PDFs', 'General Work Policy', 'work.pdf'])
            ->assertSee('href="'.route('admin.settings.policy.pdf.file', 'general_work').'"', false)
            ->assertSee(['id="alcohol_drug_test_policy_pdf" name="alcohol_drug_test_policy_pdf"', 'id="general_work_policy_pdf" name="general_work_policy_pdf"'], false)
            ->assertSee(['id="alcohol_pdf_filename"', 'id="general_pdf_filename"'], false)
            ->assertSee('window.showAppAlert(', false)
            ->getContent();

        assertBatch7Ui($html);
    });

    it('forbids the settings pages without company.edit', function (string $route) {
        $company = Actors::company();

        $this->actingAs(Actors::memberWithPermissions($company, ['drivers.view']))
            ->get($route === 'admin.settings.company.edit' ? route($route, $company->id) : route($route))
            ->assertForbidden();
    })->with(['admin.settings.company.edit', 'admin.settings.policy.pdf']);
});
