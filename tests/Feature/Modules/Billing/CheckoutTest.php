<?php

use App\Models\Company;
use Database\Factories\PlanFactory;
use Database\Factories\PlanPriceFactory;
use Mockery;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Service\Checkout\SessionService;
use Stripe\Service\CustomerService;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;
use Tests\Support\StripeFake;

/*
| 07 §6.1: checkout is per company. The Stripe customer belongs to the company; the session
| uses the plan_prices Stripe Price (never a client amount) and carries company metadata.
*/

function fakeCheckoutClient(?array &$captured = null): void
{
    $client = StripeFake::bindClient();
    $client->customers = Mockery::mock(CustomerService::class);
    $client->customers->shouldReceive('create')->andReturn(Customer::constructFrom(['id' => 'cus_new']));
    $client->customers->shouldReceive('update')->andReturn(Customer::constructFrom(['id' => 'cus_existing']));
    $sessions = Mockery::mock(SessionService::class);
    $sessions->shouldReceive('create')->andReturnUsing(function (array $params) use (&$captured) {
        $captured = $params;

        return Session::constructFrom(['id' => 'cs_new', 'url' => 'https://checkout.stripe.test/cs_new']);
    });
    $client->checkout = (object) ['sessions' => $sessions];
}

beforeEach(function () {
    $this->company = Actors::company(subscribed: false);
    $this->price = Billing::price();
});

it('opens a Stripe checkout for the company with the plan price and company metadata', function () {
    fakeCheckoutClient($params);

    $this->actingAs($this->company->owner)
        ->post(route('checkout'), ['plan_price_id' => $this->price->id])
        ->assertRedirect('https://checkout.stripe.test/cs_new');

    expect($params['line_items'])->toBe([['price' => $this->price->stripe_price_id, 'quantity' => 1]])
        ->and($params['client_reference_id'])->toBe((string) $this->company->id)
        ->and($params['metadata']['company_id'])->toBe((string) $this->company->id)
        ->and($params['metadata']['plan_price_id'])->toBe((string) $this->price->id)
        ->and($params['metadata']['app_host'])->not->toBeEmpty()
        ->and($this->company->fresh()->stripe_customer_id)->toBe('cus_new');
});

it('reuses the company\'s Stripe customer for every billing manager', function () {
    $this->company->forceFill(['stripe_customer_id' => 'cus_existing'])->save();
    fakeCheckoutClient($params);

    $this->actingAs($this->company->owner)->post(route('checkout'), ['plan_price_id' => $this->price->id]);

    expect($params['customer'])->toBe('cus_existing')
        ->and(Company::whereNotNull('stripe_customer_id')->count())->toBe(1);
});

it('refuses an archived price or one without a Stripe Price', function (Closure $price) {
    fakeCheckoutClient();

    $this->actingAs($this->company->owner)
        ->post(route('checkout'), ['plan_price_id' => $price()->id])
        ->assertSessionHasErrors('plan_price_id');
})->with([
    'archived' => [fn () => Billing::price(['archived_at' => now()])],
    'no Stripe price' => [fn () => PlanPriceFactory::new()->create()],
]);

it('refuses an inactive plan', function () {
    fakeCheckoutClient();
    $price = Billing::price(['plan_id' => PlanFactory::new()->inactive()->create()->id]);

    $this->actingAs($this->company->owner)
        ->post(route('checkout'), ['plan_price_id' => $price->id])
        ->assertRedirect(route('pricing.plans'));
});

it('refuses checkout while the company already pays', function () {
    fakeCheckoutClient();
    $company = Actors::company();

    $this->actingAs($company->owner)
        ->post(route('checkout'), ['plan_price_id' => $this->price->id])
        ->assertRedirect(route('pricing.plans'));

    expect($company->fresh()->stripe_customer_id)->toBeNull();
});

it('forbids a user without billing.manage', function () {
    fakeCheckoutClient();

    $this->actingAs(Actors::companyAdmin($this->company))
        ->post(route('checkout'), ['plan_price_id' => $this->price->id])
        ->assertForbidden();
});

it('lets a read-only company pay (billing stays writable)', function () {
    fakeCheckoutClient();
    $company = Actors::readOnly(Actors::company());

    $this->actingAs($company->owner)
        ->post(route('checkout'), ['plan_price_id' => $this->price->id])
        ->assertRedirect('https://checkout.stripe.test/cs_new');
});

it('is not a GET route', function () {
    $this->actingAs($this->company->owner)->get('/checkout/starter')->assertNotFound();
});
