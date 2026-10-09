<?php

use App\Models\Driver;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Stripe\Webhook;
use Tests\Support\Actors;
use Tests\Support\Perf;
use Tests\Support\StripeFake;

/*
| Sanity checks for the shared test harness. If these fail, fix tests/Support or the
| factories before trusting any module suite.
*/

describe('actors', function () {
    it('lets a super-admin into the admin area without a subscription', function () {
        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk();
    });

    it('lets a subscribed company owner reach a permission-guarded page', function () {
        $owner = Actors::companyOwner();

        expect($owner->hasRole('company'))->toBeTrue()
            ->and($owner->can('drivers.view'))->toBeTrue()
            ->and($owner->hasActiveSubscription())->toBeTrue();

        $this->actingAs($owner)
            ->get(route('admin.driver.index'))
            ->assertOk();
    });

    it('sends an expired tenant to the pricing page', function () {
        $this->actingAs(Actors::expiredCompanyOwner())
            ->get(route('admin.driver.index'))
            ->assertRedirect(route('pricing.plans'));
    });

    it('gives a plain user no module permissions', function () {
        expect(Actors::plainUser()->can('drivers.view'))->toBeFalse();
    });
});

describe('factories', function () {
    it('keeps tenant data attached to the right company', function () {
        $company = Actors::companyOf(Actors::companyOwner());

        $driver = DriverFactory::new()->forCompany($company)->create();
        $vehicle = VehicleFactory::new()->forCompany($company)->create();

        expect($driver->company_id)->toBe($company->id)
            ->and($driver->user_id)->toBe($company->user_id)
            ->and($vehicle->company_id)->toBe($company->id);
    });
});

describe('helpers', function () {
    it('counts queries', function () {
        expect(Perf::countQueries(fn () => Driver::query()->count()))->toBe(1);
    });

    it('signs Stripe webhooks the way Stripe verifies them', function () {
        $payload = StripeFake::event('invoice.paid', ['id' => 'in_test', 'object' => 'invoice']);

        $event = Webhook::constructEvent($payload, StripeFake::signature($payload), StripeFake::WEBHOOK_SECRET);

        expect($event->type)->toBe('invoice.paid');
    });

    it('runs with dummy third-party credentials', function () {
        expect(config('services.stripe.secret'))->toBe('sk_test_dummy')
            ->and(config('services.vonage.api_key'))->toBe('test_key');
    });
});
