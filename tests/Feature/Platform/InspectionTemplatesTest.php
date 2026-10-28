<?php

use App\Models\AuditLog;
use App\Models\InspectionTemplate;
use Database\Factories\InspectionFactory;
use Database\Factories\InspectionTemplateFactory;
use Database\Factories\TripFactory;
use Database\Seeders\InspectionTemplateSeeder;
use Tests\Support\Actors;

/*
| Pre-trip checklist versions, a platform lookup (08 §9, LKP-01, P2-4). A version an inspection
| used is never edited; a new version copies the items; one version is active at a time.
*/

function usedBy(InspectionTemplate $template): void
{
    InspectionFactory::new()->forTrip(TripFactory::new()->forCompany(Actors::company())->assigned()->create())->create(['template_id' => $template->id]);
}

beforeEach(function () {
    $this->owner = Actors::platformOwner();
});

it('creates a draft, adds items and publishes it as the only active version', function () {
    $this->actingAs($this->owner, 'platform');
    $old = InspectionTemplateFactory::new()->active()->withItems()->create();

    $this->post(route('platform.lookups.inspection-templates.store'), ['name' => 'Tractor pre-trip'])->assertRedirect();
    $draft = InspectionTemplate::query()->where('name', 'Tractor pre-trip')->sole();
    expect($draft->version)->toBe(1)->and($draft->isDraft())->toBeTrue()->and($draft->is_active)->toBeFalse();

    $this->post(route('platform.lookups.inspection-templates.publish', $draft))->assertSessionHasErrors('template');

    $this->post(route('platform.lookups.inspection-templates.items.store', $draft), ['section' => 'Lights', 'label' => 'Headlights', 'sort_order' => 10, 'is_required' => '1'])
        ->assertSessionHasNoErrors();
    $this->post(route('platform.lookups.inspection-templates.publish', $draft))->assertSessionHasNoErrors();

    expect($draft->refresh()->is_active)->toBeTrue()->and($draft->published_at)->not->toBeNull()
        ->and($old->refresh()->is_active)->toBeFalse()
        ->and(AuditLog::where('action', 'inspection_template.published')->where('actor_type', 'platform_user')->where('actor_id', $this->owner->id)->value('changes'))
        ->toBe(['version' => 1, 'deactivated' => [$old->id]]);
});

it('edits and deletes items while the version is unused, published or not', function () {
    $this->actingAs($this->owner, 'platform');
    $template = InspectionTemplateFactory::new()->active()->withItems()->create();
    $item = $template->items()->first();

    $this->put(route('platform.lookups.inspection-templates.items.update', [$template, $item]), ['section' => 'Lamps', 'label' => 'Head lamps', 'sort_order' => 5])
        ->assertSessionHasNoErrors();
    expect($item->refresh()->only(['section', 'label', 'sort_order', 'is_required']))->toBe(['section' => 'Lamps', 'label' => 'Head lamps', 'sort_order' => 5, 'is_required' => false]);

    $this->delete(route('platform.lookups.inspection-templates.items.destroy', [$template, $item]))->assertSessionHasNoErrors();
    expect($template->items()->count())->toBe(2);
});

it('never edits a version an inspection used', function () {
    $this->actingAs($this->owner, 'platform');
    $template = InspectionTemplateFactory::new()->active()->withItems()->create();
    $item = $template->items()->first();
    usedBy($template);

    $this->get(route('platform.lookups.inspection-templates.edit', $template))->assertOk()->assertSee('its items are fixed')->assertDontSee('Add item');
    $this->post(route('platform.lookups.inspection-templates.items.store', $template), ['section' => 'Lights', 'label' => 'Fog lamps', 'sort_order' => 90])
        ->assertSessionHasErrors('template');
    $this->put(route('platform.lookups.inspection-templates.items.update', [$template, $item]), ['section' => 'X', 'label' => 'Y', 'sort_order' => 1])
        ->assertSessionHasErrors('template');
    $this->delete(route('platform.lookups.inspection-templates.items.destroy', [$template, $item]))->assertSessionHasErrors('template');

    expect($template->items()->count())->toBe(3)->and($item->refresh()->label)->toBe('Headlights');
});

it('creates a new version as a draft with the items copied, and reuses an open draft', function () {
    $this->actingAs($this->owner, 'platform');
    $template = InspectionTemplateFactory::new()->active()->withItems()->create(['name' => 'Pre-trip']);
    usedBy($template);

    $this->post(route('platform.lookups.inspection-templates.new-version', $template))->assertRedirect();
    $draft = InspectionTemplate::query()->where('name', 'Pre-trip')->where('version', 2)->sole();

    expect($draft->isDraft())->toBeTrue()
        ->and($draft->items()->get(['section', 'label', 'sort_order', 'is_required'])->toArray())
        ->toBe($template->items()->get(['section', 'label', 'sort_order', 'is_required'])->toArray())
        ->and($template->refresh()->is_active)->toBeTrue();

    $this->post(route('platform.lookups.inspection-templates.new-version', $template))
        ->assertRedirect(route('platform.lookups.inspection-templates.edit', $draft));
    expect(InspectionTemplate::query()->where('name', 'Pre-trip')->count())->toBe(2);
});

it('deactivates and activates a published version, audited', function () {
    $this->actingAs($this->owner, 'platform');
    $template = InspectionTemplateFactory::new()->active()->withItems()->create();
    $other = InspectionTemplateFactory::new()->withItems()->create(['published_at' => now()]);

    $this->post(route('platform.lookups.inspection-templates.deactivate', $template))->assertSessionHasNoErrors();
    expect($template->refresh()->is_active)->toBeFalse();

    $this->post(route('platform.lookups.inspection-templates.activate', $other))->assertSessionHasNoErrors();
    $this->post(route('platform.lookups.inspection-templates.activate', $template))->assertSessionHasNoErrors();

    expect($template->refresh()->is_active)->toBeTrue()->and($other->refresh()->is_active)->toBeFalse()
        ->and(AuditLog::where('action', 'inspection_template.deactivated')->count())->toBe(1)
        ->and(AuditLog::where('action', 'inspection_template.activated')->count())->toBe(2);
});

it('deletes only a draft', function () {
    $this->actingAs($this->owner, 'platform');
    $draft = InspectionTemplateFactory::new()->withItems()->create();
    $published = InspectionTemplateFactory::new()->active()->create();

    $this->delete(route('platform.lookups.inspection-templates.destroy', $published))->assertSessionHasErrors('template');
    $this->delete(route('platform.lookups.inspection-templates.destroy', $draft))->assertRedirect(route('platform.lookups.inspection-templates.index'));

    expect(InspectionTemplate::query()->pluck('id')->all())->toBe([$published->id])
        ->and(AuditLog::where('action', 'inspection_template.deleted')->exists())->toBeTrue();
});

it('refuses an item of another template', function () {
    $this->actingAs($this->owner, 'platform');
    $template = InspectionTemplateFactory::new()->withItems()->create();
    $other = InspectionTemplateFactory::new()->withItems()->create();

    $this->delete(route('platform.lookups.inspection-templates.items.destroy', [$template, $other->items()->first()]))->assertNotFound();
});

it('validates items and names', function () {
    $this->actingAs($this->owner, 'platform');
    InspectionTemplateFactory::new()->create(['name' => 'Pre-trip']);
    $draft = InspectionTemplateFactory::new()->create();

    $this->post(route('platform.lookups.inspection-templates.store'), ['name' => 'Pre-trip'])->assertSessionHasErrors('name');
    $this->post(route('platform.lookups.inspection-templates.items.store', $draft), ['section' => '', 'label' => str_repeat('x', 256), 'sort_order' => -1])
        ->assertSessionHasErrors(['section', 'label', 'sort_order']);
});

it('lists the versions', function () {
    $this->actingAs($this->owner, 'platform');
    $template = InspectionTemplateFactory::new()->active()->withItems()->create(['name' => 'Pre-trip']);
    usedBy($template);

    $this->get(route('platform.lookups.inspection-templates.index'))->assertOk()
        ->assertSee('Pre-trip v1')->assertSee('Active')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('is managed only with platform.settings.manage', function () {
    $this->actingAs($this->owner, 'platform');
    $template = InspectionTemplateFactory::new()->create();

    $this->actingAs(Actors::platformSupport(), 'platform')->get(route('platform.lookups.inspection-templates.index'))->assertForbidden();
    $this->actingAs(Actors::platformSupport(), 'platform')->post(route('platform.lookups.inspection-templates.publish', $template))->assertForbidden();
    $this->actingAs(Actors::platformStaff('platform_admin'), 'platform')->get(route('platform.lookups.inspection-templates.edit', $template))->assertOk();
});

it('is out of reach for tenant users', function () {
    $template = InspectionTemplateFactory::new()->create();

    $this->actingAs(Actors::companyOwner())->postJson(route('platform.lookups.inspection-templates.publish', $template))->assertUnauthorized();
});

it('seeds one published pre-trip version and is safe to re-run', function () {
    $this->actingAs($this->owner, 'platform');
    $this->seed(InspectionTemplateSeeder::class);
    $this->seed(InspectionTemplateSeeder::class);

    $template = InspectionTemplate::query()->sole();
    $sections = $template->items()->pluck('section')->unique()->values()->all();

    expect($template->only(['name', 'version', 'is_active']))->toBe(['name' => 'Pre-trip', 'version' => 1, 'is_active' => true])
        ->and($template->published_at)->not->toBeNull()
        ->and($sections)->toBe(array_keys(InspectionTemplateSeeder::SECTIONS))
        ->and($template->items()->count())->toBe(collect(InspectionTemplateSeeder::SECTIONS)->flatten()->count())
        ->and($template->items()->where('is_required', false)->exists())->toBeFalse();
});
