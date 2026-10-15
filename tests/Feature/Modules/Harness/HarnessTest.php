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
    it('lets platform staff into the platform area', function () {
        $this->actingAs(Actors::platformOwner(), 'platform')
            ->get(route('platform.dashboard'))
            ->assertOk();
    });

    it('lets a subscribed company owner reach a permission-guarded page', function () {
        $owner = Actors::actingAsTenant(Actors::companyOwner());

        expect(Actors::companyOf($owner)->owner_user_id)->toBe($owner->id)
            ->and($owner->can('drivers.view'))->toBeTrue()
            ->and(Actors::openSubscription(Actors::companyOf($owner)))->not->toBeNull();

        $this->actingAs($owner)
            ->get(route('admin.driver.index'))
            ->assertOk();
    });

    it('sends a tenant that never subscribed to the pricing page', function () {
        $this->actingAs(Actors::companyOwner(Actors::company(subscribed: false)))
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
            ->and($driver->created_by_user_id)->toBe($company->owner_user_id)
            ->and($vehicle->company_id)->toBe($company->id);
    });
});

describe('helpers', function () {
    it('counts queries', function () {
        expect(Perf::countQueries(fn () => Driver::withoutTenancy()->count()))->toBe(1);
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
