<?php

use Database\Factories\AssetTransferFactory;
use Database\Factories\CompanyFactory;
use Database\Factories\PaymentFactory;
use Database\Factories\PlanFactory;
use Database\Factories\PlanPriceFactory;
use Database\Factories\PlatformUserFactory;
use Database\Factories\SubscriptionFactory;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| UI-08 (phase 2, batch 4a-1): the platform lists that grow without limit (companies,
| subscriptions, payments, asset transfers) on server-side tables, behind the same permissions.
*/

function platformRows(string $route, array $params = []): array
{
    return test()->withHeaders(FleetAssets::ajax())
        ->get(route($route, ['draw' => 1] + $params))
        ->assertOk()->json('data');
}

dataset('platform lists', [
    'companies' => ['platform.companies.index', 'companies'],
    'subscriptions' => ['platform.subscriptions.index', 'subscriptions'],
    'payments' => ['platform.subscriptions.payments', 'payments'],
    'asset transfers' => ['platform.asset-transfers.index', 'asset-transfers'],
]);

it('renders the page on the shared table, without page links', function (string $route, string $table) {
    $this->actingAs(Actors::platformOwner(), 'platform')->get(route($route))
        ->assertOk()
        ->assertSee("id=\"$table\"", false)
        ->assertDontSee('?page=', false);
})->with('platform lists');

it('refuses the table data without the permission', function (string $route, Closure $staff) {
    $this->actingAs($staff(), 'platform')->withHeaders(FleetAssets::ajax())
        ->get(route($route, ['draw' => 1]))
        ->assertForbidden();
})->with([
    'companies' => ['platform.companies.index', fn () => PlatformUserFactory::new()->create()],
    'subscriptions' => ['platform.subscriptions.index', fn () => PlatformUserFactory::new()->create()],
    'payments' => ['platform.subscriptions.payments', fn () => Actors::platformSupport()],
    'asset transfers' => ['platform.asset-transfers.index', fn () => Actors::platformStaff('billing_manager')],
]);

describe('companies', function () {
    it('pages on the server', function () {
        CompanyFactory::new()->count(7)->create();
        $this->actingAs(Actors::platformOwner(), 'platform');

        $json = $this->withHeaders(FleetAssets::ajax())
            ->get(route('platform.companies.index', ['draw' => 1, 'start' => 5, 'length' => 5]))
            ->assertOk()->json();

        expect($json['recordsTotal'])->toBe(7)->and($json['data'])->toHaveCount(2);
    });

    it('searches the name, contact email and owner email', function () {
        $byName = Actors::company(['company_name' => 'Zephyr Haulage']);
        $byOwner = Actors::company();
        $byOwner->owner->update(['email' => 'boss@zephyr-owner.test']);
        Actors::company(['company_name' => 'Other Freight']);
        $this->actingAs(Actors::platformOwner(), 'platform');

        $ids = array_column(platformRows('platform.companies.index', ['search' => ['value' => 'zephyr']]), 'id');

        expect($ids)->toEqualCanonicalizing([$byName->id, $byOwner->id]);
    });

    it('filters by status and ignores an unknown one', function () {
        $suspended = CompanyFactory::new()->create(['status' => 'suspended']);
        CompanyFactory::new()->create(['status' => 'active']);
        $this->actingAs(Actors::platformOwner(), 'platform');

        expect(array_column(platformRows('platform.companies.index', ['status' => 'suspended']), 'id'))->toBe([$suspended->id])
            ->and(platformRows('platform.companies.index', ['status' => 'bogus']))->toHaveCount(2);
    });

    it('treats a search term as text, not a wildcard', function () {
        CompanyFactory::new()->create(['company_name' => 'Acme Trucking']);
        $this->actingAs(Actors::platformOwner(), 'platform');

        expect(platformRows('platform.companies.index', ['search' => ['value' => '%']]))->toBe([]);
    });
});

describe('subscriptions', function () {
    it('filters by status, plan and open, and searches the company name', function () {
        $gold = PlanFactory::new()->create(['name' => 'Gold']);
        $match = SubscriptionFactory::new()->forCompany(CompanyFactory::new()->create(['company_name' => 'Kestrel Lines']))
            ->for(PlanPriceFactory::new()->for($gold), 'planPrice')->pastDue()->create();
        SubscriptionFactory::new()->pastDue()->create();
        SubscriptionFactory::new()->forCompany(CompanyFactory::new()->create(['company_name' => 'Kestrel Ended']))->expired()->create();
        $this->actingAs(Actors::platformOwner(), 'platform');

        expect(array_column(platformRows('platform.subscriptions.index', ['status' => 'past_due', 'plan_id' => $gold->id]), 'id'))->toBe([$match->id])
            ->and(array_column(platformRows('platform.subscriptions.index', ['open' => 1, 'search' => ['value' => 'kestrel']]), 'id'))->toBe([$match->id]);
    });
});

describe('payments', function () {
    it('filters by status and searches the company name', function () {
        $company = CompanyFactory::new()->create(['company_name' => 'Osprey Freight']);
        $failed = PaymentFactory::new()->forCompany($company)->create(['status' => 'failed', 'paid_at' => null]);
        PaymentFactory::new()->forCompany($company)->create();
        PaymentFactory::new()->create(['status' => 'failed']);
        $this->actingAs(Actors::platformOwner(), 'platform');

        expect(array_column(platformRows('platform.subscriptions.payments', ['status' => 'failed', 'search' => ['value' => 'osprey']]), 'id'))->toBe([$failed->id]);
    });

    it('opens to billing staff', function () {
        PaymentFactory::new()->create();

        $this->actingAs(Actors::platformStaff('billing_manager'), 'platform');

        expect(platformRows('platform.subscriptions.payments'))->toHaveCount(1);
    });
});

describe('asset transfers', function () {
    it('filters by asset type and searches the source or target company', function () {
        $target = CompanyFactory::new()->create(['company_name' => 'Heron Logistics']);
        $match = AssetTransferFactory::new()->create(['asset_type' => 'trailer', 'target_company_id' => $target->id]);
        AssetTransferFactory::new()->create(['asset_type' => 'vehicle', 'target_company_id' => $target->id]);
        AssetTransferFactory::new()->create(['asset_type' => 'trailer']);
        $this->actingAs(Actors::platformOwner(), 'platform');

        $rows = platformRows('platform.asset-transfers.index', ['asset_type' => 'trailer', 'search' => ['value' => 'heron']]);

        expect(array_column($rows, 'id'))->toBe([$match->id])
            ->and($rows[0]['copied'])->toContain('Service logs');
    });
});

it('escapes tenant-controlled text in the table data', function () {
    $company = CompanyFactory::new()->create(['company_name' => '<script>alert(1)</script>']);
    AssetTransferFactory::new()->create(['source_company_id' => $company->id, 'reason' => '<img src=x onerror=alert(2)>']);
    $this->actingAs(Actors::platformOwner(), 'platform');

    $company = platformRows('platform.companies.index')[0]['company'];
    $transfer = platformRows('platform.asset-transfers.index')[0];

    expect($company)->not->toContain('<script>')->toContain(e('<script>alert(1)</script>'))
        ->and($transfer['route'])->not->toContain('<script>')
        ->and($transfer['reason'])->not->toContain('<img');
});

it('keeps tenant logins out of the table data', function () {
    $this->actingAs(Actors::companyOwner())->withHeaders(FleetAssets::ajax())
        ->get(route('platform.companies.index', ['draw' => 1]))
        ->assertUnauthorized();
});
