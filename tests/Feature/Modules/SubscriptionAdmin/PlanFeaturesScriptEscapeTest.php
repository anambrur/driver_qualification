<?php

use Database\Factories\PlanFactory;
use Tests\Support\Actors;

/*
| SADM-02: admin/plans/{create,edit}.blade.php embed the features array inside <script>.
| A feature containing </script> or <!-- must not break out of the script block.
*/

const SADM_PAYLOAD = '</script><script>alert(document.domain)</script><!--<script>';

function sadmScriptBlock(string $html): string
{
    preg_match('/Alpine\.data\(\'featuresManager\'.*?<\/script>/s', $html, $m);

    return $m[0] ?? '';
}

it('escapes a stored feature on the edit page', function () {
    $plan = PlanFactory::new()->create(['features' => [SADM_PAYLOAD]]);

    $html = $this->actingAs(Actors::superAdmin())
        ->get(route('admin.plans.edit', $plan))
        ->assertOk()
        ->getContent();

    $block = sadmScriptBlock($html);

    expect($block)->not->toBe('')
        ->and($html)->not->toContain('<script>alert(document.domain)</script>')
        ->and(substr_count($block, '</script>'))->toBe(1)
        ->and(stripos($block, '<script'))->toBeFalse()
        ->and(strpos($block, '<!--'))->toBeFalse();
});

it('escapes old() features on the create page after a validation error', function () {
    $admin = Actors::superAdmin();

    $this->actingAs($admin)
        ->from(route('admin.plans.create'))
        ->post(route('admin.plans.store'), ['features' => [SADM_PAYLOAD]])
        ->assertRedirect(route('admin.plans.create'));

    $html = $this->actingAs($admin)->get(route('admin.plans.create'))->assertOk()->getContent();
    $block = sadmScriptBlock($html);

    expect($block)->not->toBe('')
        ->and($html)->not->toContain('<script>alert(document.domain)</script>')
        ->and(substr_count($block, '</script>'))->toBe(1)
        ->and(stripos($block, '<script'))->toBeFalse()
        ->and(strpos($block, '<!--'))->toBeFalse();
});

it('escapes old() features on the edit page after a validation error', function () {
    $admin = Actors::superAdmin();
    $plan = PlanFactory::new()->create();

    $this->actingAs($admin)
        ->from(route('admin.plans.edit', $plan))
        ->put(route('admin.plans.update', $plan), ['features' => [SADM_PAYLOAD]])
        ->assertRedirect(route('admin.plans.edit', $plan));

    $html = $this->actingAs($admin)->get(route('admin.plans.edit', $plan))->assertOk()->getContent();
    $block = sadmScriptBlock($html);

    expect(substr_count($block, '</script>'))->toBe(1)
        ->and(stripos($block, '<script'))->toBeFalse()
        ->and(strpos($block, '<!--'))->toBeFalse();
});

it('still hands the exact feature text to Alpine', function () {
    $features = ['Unlimited "drivers" & <b>trailers</b>', "Owner's portal"];
    $plan = PlanFactory::new()->create(['features' => $features]);

    $html = $this->actingAs(Actors::superAdmin())->get(route('admin.plans.edit', $plan))->getContent();

    expect(sadmScriptBlock($html))->toContain('features: '.\Illuminate\Support\Js::from($features)->toHtml().',');
});
