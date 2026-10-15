<?php

use Database\Factories\PlanFactory;
use Database\Factories\PlanPriceFactory;
use Tests\Support\Actors;

/*
| SADM-02: plan feature text must not break out of the page. The old admin/plans forms put a
| features array inside <script>; the platform plan form (WP8a) edits marketing_bullets in a
| textarea, and the public pricing on the welcome page lists them. Both must escape the text.
*/

const SADM_PAYLOAD = '</script><script>alert(document.domain)</script><!--<script>';

it('escapes a stored bullet on the platform edit page', function () {
    $plan = PlanFactory::new()->create(['marketing_bullets' => [SADM_PAYLOAD]]);

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.plans.edit', $plan))
        ->assertOk()
        ->assertDontSee('<script>alert(document.domain)</script>', false)
        ->assertSee(e(SADM_PAYLOAD), false);
});

it('escapes old() bullets on the create page after a validation error', function () {
    $owner = Actors::platformOwner();

    $this->actingAs($owner, 'platform')
        ->from(route('platform.plans.create'))
        ->post(route('platform.plans.store'), ['marketing_bullets' => SADM_PAYLOAD])
        ->assertRedirect(route('platform.plans.create'));

    $this->actingAs($owner, 'platform')
        ->get(route('platform.plans.create'))
        ->assertOk()
        ->assertDontSee('<script>alert(document.domain)</script>', false)
        ->assertSee(e(SADM_PAYLOAD), false);
});

it('escapes bullets in the public pricing', function () {
    $plan = PlanFactory::new()->create(['marketing_bullets' => [SADM_PAYLOAD], 'is_public' => true, 'is_active' => true]);
    PlanPriceFactory::new()->create(['plan_id' => $plan->id]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('<script>alert(document.domain)</script>', false)
        ->assertSee(e(SADM_PAYLOAD), false);
});
