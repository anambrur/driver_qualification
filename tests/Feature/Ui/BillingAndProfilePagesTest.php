<?php

use App\Services\Billing\AccessStateResolver;
use Database\Factories\PaymentFactory;
use Database\Factories\SubscriptionFactory;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| UI-12 (phase 2, batch 5a): billing, the billing banner and the profile on x-ui, without Font Awesome
| or the Breeze components. Stripe routes, permissions and form fields are unchanged.
*/

function assertNoLegacyUi(string $html): void
{
    expect($html)->not->toContain('class="fas ')
        ->not->toContain('fa-')
        ->not->toContain('text-gray-')
        ->not->toContain('dark:bg-gray-');
}

function errorsIn(string $bag, array $messages): ViewErrorBag
{
    return (new ViewErrorBag)->put($bag, new MessageBag($messages));
}

it('renders billing with the Stripe actions and a confirm step before cancelling', function () {
    $company = Actors::company();
    $company->forceFill(['stripe_customer_id' => 'cus_test_ui'])->save();
    PaymentFactory::new()->forCompany($company)->create(['hosted_invoice_url' => 'https://invoice.stripe.com/i/test']);

    $html = $this->actingAs($company->owner)->get(route('billing.index'))->assertOk()
        ->assertSee(['Current Plan', 'Manage in Stripe', 'Cancel Plan', 'Payment History', 'Update Payment'])
        ->assertSee('action="'.route('billing.portal').'"', false)
        ->assertSee('data-confirm="Cancel your plan?"', false)
        ->assertSee('href="https://invoice.stripe.com/i/test"', false)
        ->assertDontSee('onsubmit="return confirm(', false)
        ->getContent();

    assertNoLegacyUi($html);
});

it('offers resume instead of cancel while a plan is cancelling', function () {
    $company = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($company)->cancelingAtPeriodEnd()->create();

    $this->actingAs($company->owner)->get(route('billing.index'))->assertOk()
        ->assertSee(['Cancelling', 'Resume Subscription'])
        ->assertSee('action="'.route('billing.resume').'"', false)
        ->assertDontSee('action="'.route('billing.cancel').'"', false);
});

it('shows the empty state without a subscription', function () {
    $this->actingAs(Actors::company(subscribed: false)->owner)->get(route('billing.index'))->assertOk()
        ->assertSee(['No Active Subscription', 'View Plans', 'No payments yet'])
        ->assertSee('class="empty border', false);
});

it('hides the billing actions from a member who can only view billing', function () {
    $member = Actors::memberWithPermissions(Actors::company(), ['billing.view']);

    $this->actingAs($member)->get(route('billing.index'))->assertOk()
        ->assertSee('Current Plan')
        ->assertDontSee(['Manage in Stripe', 'Cancel Plan']);
});

it('forbids billing without billing.view', function () {
    $this->actingAs(Actors::memberWithPermissions(Actors::company(), ['drivers.view']))
        ->get(route('billing.index'))
        ->assertForbidden();
});

it('renders the plans with one checkout form per price', function () {
    $price = Billing::price();
    $company = Actors::company(subscribed: false);

    $html = $this->actingAs($company->owner)->get(route('pricing.plans'))->assertOk()
        ->assertSee($price->plan->name)
        ->assertSee('action="'.route('checkout').'"', false)
        ->assertSee('name="plan_price_id" value="'.$price->id.'"', false)
        ->assertSee(['Secure payment via Stripe', 'Cancel anytime'])
        ->getContent();

    assertNoLegacyUi($html);
});

it('marks the current plan and tells members without billing.manage to ask the owner', function () {
    $company = Actors::company();
    $other = Billing::price();
    $member = Actors::memberWithPermissions($company, ['billing.view']);

    $this->actingAs($member)->get(route('pricing.plans'))->assertOk()
        ->assertSee(['Current Plan', 'Manage Billing', 'Ask your company owner to choose a plan.', $other->plan->name])
        ->assertDontSee('name="plan_price_id"', false);
});

it('renders the checkout success page', function () {
    $company = Actors::company();

    $html = $this->actingAs($company->owner)->get(route('checkout.success'))->assertOk()
        ->assertSee(["You're all set!", 'Go to Dashboard', 'Manage Billing'], false)
        ->getContent();

    assertNoLegacyUi($html);
});

it('shows the billing banner as an x-ui alert', function (string $state, string $tone, string $text) {
    $company = $state === 'read-only' ? Actors::readOnly(Actors::company()) : Actors::company(subscribed: false);
    if ($state === 'grace') {
        SubscriptionFactory::new()->forCompany($company)->pastDue()->create(['current_period_start' => now()->subDays(2)]);
    }
    app(AccessStateResolver::class)->forget($company->id);

    $this->actingAs($company->owner)->get(route('users.index'))->assertOk()
        ->assertSee('data-tone="'.$tone.'"', false)
        ->assertSee($text)
        ->assertDontSee('fa-exclamation-triangle', false)
        ->assertDontSee('fa-lock', false);
})->with([
    'read-only' => ['read-only', 'danger', 'subscription has ended'],
    'grace' => ['grace', 'warning', 'last payment failed'],
]);

it('renders the profile on x-ui fields with a close account dialog for the owner', function () {
    $html = $this->actingAs(Actors::companyOwner())->get(route('profile.edit'))->assertOk()
        ->assertSee(['Profile Information', 'Update Password', 'Close Account'])
        ->assertSee(['name="name"', 'name="email"', 'name="current_password"', 'name="password_confirmation"'], false)
        ->assertSee('<dialog id="confirm-user-deletion"', false)
        ->assertSee('action="'.route('profile.destroy').'"', false)
        ->assertDontSee("document.getElementById('confirm-user-deletion').showModal();</script>", false)
        ->getContent();

    assertNoLegacyUi($html);
});

it('reopens the close account dialog when the password was wrong', function () {
    $this->actingAs(Actors::companyOwner())
        ->withSession(['errors' => errorsIn('userDeletion', ['password' => 'The password is incorrect.'])])
        ->get(route('profile.edit'))->assertOk()
        ->assertSee('The password is incorrect.')
        ->assertSee('id="password-error"', false)
        ->assertSee("document.getElementById('confirm-user-deletion').showModal();</script>", false);
});

it('shows password errors from the updatePassword bag on their field', function () {
    $this->actingAs(Actors::companyOwner())
        ->withSession(['errors' => errorsIn('updatePassword', ['current_password' => 'The password is incorrect.'])])
        ->get(route('profile.edit'))->assertOk()
        ->assertSee('id="update_password_current_password-error"', false)
        ->assertSee('aria-describedby="update_password_current_password-error"', false);
});

it('has no close account section for a non-owner', function () {
    $this->actingAs(Actors::companyAdmin())->get(route('profile.edit'))->assertOk()
        ->assertDontSee('confirm-user-deletion', false);
});

it('removed the unused subscription views and Breeze components', function (string $view) {
    expect(view()->exists($view))->toBeFalse();
})->with([
    'subscription.checkout', 'subscription.expired', 'subscription.my', 'subscription.payment-cancel',
    'subscription.payment-success', 'subscription.plans', 'subscription.renew',
    'components.primary-button', 'components.secondary-button', 'components.danger-button', 'components.text-input',
    'components.input-label', 'components.input-error', 'components.modal', 'components.auth-session-status',
    'components.application-logo',
]);

it('still redirects the legacy subscription URLs', function (string $from, string $to) {
    $this->actingAs(Actors::companyOwner())->get($from)->assertRedirect(route($to));
})->with([
    ['/subscription/plans', 'pricing.plans'],
    ['/subscription/my', 'billing.index'],
    ['/subscription/renew', 'billing.index'],
    ['/subscription/expired', 'pricing.plans'],
]);
