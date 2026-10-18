<?php

use App\Support\Navigation\CompanyMenu;
use App\Support\Navigation\PlatformMenu;
use Illuminate\Http\Request;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;

/*
| UI-02: the sidebar is built from CompanyMenu / PlatformMenu. Every link keeps the gate of the
| route it points to, and the active state is resolved on the server.
*/

function menuLabels(array $groups): array
{
    $labels = [];
    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            $labels[] = $item['label'];
            foreach ($item['children'] ?? [] as $child) {
                $labels[] = $item['label'].' › '.$child['label'];
            }
        }
    }

    return $labels;
}

it('shows a member without permissions only the dashboard and subscription', function () {
    $user = Actors::actingAsTenant(Actors::plainUser());

    expect(menuLabels(CompanyMenu::for($user, new Request)))->toBe([
        'Dashboard', 'Subscription', 'Subscription › Plans & pricing', 'Subscription › Billing & invoices',
    ]);
});

it('hides modules the plan does not include', function () {
    $owner = Actors::companyOwner();
    Actors::withPlan(Actors::companyOf($owner), ['drivers']);

    $labels = menuLabels(CompanyMenu::for(Actors::actingAsTenant($owner->fresh()), new Request));

    expect($labels)->toContain('Drivers › All drivers')
        ->not->toContain('Fleet')
        ->not->toContain('Maintenance');
});

it('marks the current page active in the rendered sidebar', function () {
    [$owner] = DriverAdmin::tenantWithDriver();

    $html = $this->actingAs($owner)->get(route('admin.driver.index'))->assertOk()->getContent();

    expect($html)->toMatch('~href="'.preg_quote(route('admin.driver.index'), '~').'"[^>]*aria-current="page"~');
});

it('marks only the current platform section active, not the dashboard', function () {
    $company = Actors::company();

    $html = $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.companies.show', $company))->assertOk()->getContent();

    expect($html)->toMatch('~href="'.preg_quote(route('platform.companies.index'), '~').'"[^>]*aria-current="page"~')
        ->not->toMatch('~href="'.preg_quote(route('platform.dashboard'), '~').'"[^>]*aria-current="page"~');
});

it('hides platform settings from support staff', function () {
    $labels = menuLabels(PlatformMenu::for(Actors::platformSupport(), new Request));

    expect($labels)->toContain('Dashboard')
        ->not->toContain('Site settings')
        ->not->toContain('Staff');
});

it('gives the platform owner every platform section', function () {
    $labels = menuLabels(PlatformMenu::for(Actors::platformOwner(), new Request));

    expect($labels)->toContain('Companies', 'Plans', 'Fuel types', 'Site settings', 'Audit log');
});

it('renders a mobile menu button in the platform header', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('aria-label="Open navigation"', false)
        ->assertSee('id="app-sidebar"', false);
});
