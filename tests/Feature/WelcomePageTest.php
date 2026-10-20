<?php

use App\Models\SiteSetting;
use App\Support\Landing;
use Database\Factories\FaqFactory;
use Database\Factories\PlanFactory;
use Database\Factories\TestimonialFactory;
use Database\Seeders\LandingContentSeeder;
use Tests\Support\Actors;

/*
| The public homepage reads its copy from App\Support\Landing and its quotes and questions
| from the database (Platform > Content).
*/

beforeEach(function () {
    // JSON-LD is printed on indexable pages only
    config(['seo.index_environments' => ['testing']]);
});

it('renders the seeded testimonials and FAQ and the default copy', function () {
    $this->seed(LandingContentSeeder::class);

    $this->get('/')->assertOk()
        ->assertSee('Every Driver File.')
        ->assertSee('5,200+')
        ->assertSee('DQF Checklists')
        ->assertSee('Marcus Johnson')
        ->assertSee('What is a Driver Qualification File (DQF)?')
        ->assertSee('href="#faq"', false)
        ->assertSee('"@type":"FAQPage"', false);
});

it('seeds only empty tables', function () {
    TestimonialFactory::new()->create(['name' => 'Kept Kim']);

    $this->seed(LandingContentSeeder::class);

    $this->get('/')->assertSee('Kept Kim')->assertDontSee('Marcus Johnson');
});

it('hides inactive items and sections without content, with their links', function () {
    TestimonialFactory::new()->inactive()->create(['name' => 'Hidden Hal']);
    FaqFactory::new()->create(['question' => 'Shown question?']);

    $this->get('/')->assertOk()
        ->assertDontSee('Hidden Hal')
        ->assertDontSee('id="testimonials"', false)
        ->assertDontSee('href="#testimonials"', false)
        ->assertSee('Shown question?');
});

it('follows the saved landing page and its switches', function () {
    FaqFactory::new()->create(['question' => 'Switched off?']);
    $landing = Landing::defaults();
    $landing['hero']['title'] = 'Fleet files, sorted.';
    $landing['features']['items'] = [['icon' => 'truck', 'color' => 'info', 'title' => 'Truck tracking', 'text' => 'Every unit.']];
    $landing['stats']['enabled'] = false;
    $landing['faq']['enabled'] = false;
    $landing['cta']['demo'] = false;
    $landing['footer']['links'] = [['label' => 'Careers', 'url' => 'https://jobs.example.com']];
    SiteSetting::getSettings()->update(['landing' => $landing, 'privacy_url' => 'https://example.com/privacy']);

    $this->get('/')->assertOk()
        ->assertSee('Fleet files, sorted.')
        ->assertSee('Truck tracking')
        ->assertDontSee('DQF Checklists')
        ->assertDontSee('Driver Files Managed')
        ->assertDontSee('Switched off?')
        ->assertDontSee('href="#faq"', false)
        ->assertDontSee('"@type":"FAQPage"', false)
        ->assertDontSee('Schedule a Demo')
        ->assertSee('href="https://jobs.example.com"', false)
        ->assertSee('href="https://example.com/privacy"', false)
        ->assertDontSee('Terms of Service');
});

it('badges the featured plan as most popular', function () {
    PlanFactory::new()->withPrices(49.00)->create(['name' => 'Basic']);
    PlanFactory::new()->withPrices(99.00)->create(['name' => 'Pro', 'is_featured' => true]);

    expect(substr_count($this->get('/')->assertOk()->getContent(), 'Most Popular'))->toBe(1);
});

it('saves the most popular switch on a plan', function () {
    $plan = PlanFactory::new()->create();

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->put(route('platform.plans.update', $plan), ['name' => $plan->name, 'slug' => $plan->slug, 'is_active' => '1', 'is_featured' => '1'])
        ->assertSessionHasNoErrors();

    expect($plan->fresh()->is_featured)->toBeTrue();
});
