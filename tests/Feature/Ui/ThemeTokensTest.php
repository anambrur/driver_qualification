<?php

use App\Models\MaintenanceSchedule;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Actors;

/*
| UI-14 (phase 2, batch 5c): pages use theme tokens (foreground, muted, border, primary…), not the
| legacy gray-* / brand-* scales, and app.css no longer defines those scales. The 404 page is on x-ui.
| UI-30 (batch 9a): the TailAdmin success/error/warning/blue-light/orange/pink/purple scales, the
| unused shadow-theme/text-title/text-theme variables and every raw Tailwind palette class are gone too.
*/

it('has no gray-* or brand-* utility left in views, scripts or models', function () {
    $files = collect([resource_path('views'), resource_path('js'), app_path()])
        ->flatMap(fn ($dir) => File::allFiles($dir))
        ->filter(fn ($file) => preg_match('/(?<![\w-])(?:[a-z-]+:)*(?:text|bg|border|ring|from|via|to|divide|placeholder|shadow)-(?:gray|brand)-\d+/', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values();

    expect($files->all())->toBe([]);
});

it('has no TailAdmin scale or raw palette class left in views, scripts or models', function () {
    $files = collect([resource_path('views'), resource_path('js'), app_path()])
        ->flatMap(fn ($dir) => collect(File::allFiles($dir))->map(fn ($file) => $file->getPathname()))
        ->push(public_path('js/driver-form-validation.js'))
        ->filter(fn ($path) => preg_match('/(?<=[a-z]-)(?:success|error|warning|blue-light|orange|pink|purple|red|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|fuchsia|rose|slate|zinc|neutral|stone)-(?:25|50|[1-9]00|950)\b/', File::get($path)))
        ->map(fn ($path) => basename($path))
        ->values();

    expect($files->all())->toBe([]);
});

it('no longer defines the TailAdmin scales or the unused theme variables in app.css', function () {
    expect(File::get(resource_path('css/app.css')))
        ->not->toMatch('/--color-(success|error|warning|blue-light|orange|pink|purple)-/')
        ->not->toMatch('/--(shadow-theme|text-title|text-theme|shadow-focus-ring|font-outfit|breakpoint-)/')
        ->toContain('--color-success: var(--success);')
        ->toContain('--color-warning: var(--warning);')
        ->toContain('--color-info: var(--info);');
});

it('colours every welcome feature icon with a whole token class', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach ([
        'bg-primary/10' => 'text-primary',
        'bg-success/10' => 'text-success',
        'bg-warning/10' => 'text-warning',
        'bg-info/10' => 'text-info',
        'bg-[color-mix(in_oklch,var(--chart-5)_10%,transparent)]' => 'text-[var(--chart-5)]',
        'bg-destructive/10' => 'text-destructive',
    ] as $bg => $text) {
        expect($html)->toContain('rounded-xl '.$bg.' flex')->toContain('<svg class="w-6 h-6 '.$text.'"');
    }
});

it('shows the support session banner in the warning tone', function () {
    Notification::fake();
    $company = Actors::company();
    Actors::inSupportSession($company, 'read', Actors::platformOwner());

    $this->get(route('admin.dashboard'))->assertOk()
        ->assertSee('border-b border-warning/50 bg-warning/15', false)
        ->assertSee('<button class="btn" data-variant="outline" data-size="sm">End session</button>', false)
        ->assertDontSee('amber-', false);
});

it('marks client-side errors with aria-invalid only and scrolls to the first server error', function () {
    $js = File::get(public_path('js/driver-form-validation.js'));

    expect($js)->not->toContain('border-red-500')
        ->toContain("form.querySelector('[aria-invalid=\"true\"]')")
        ->toContain("form.querySelectorAll('[data-js-invalid]')");
});

it('builds the maintenance badges from tokens', function () {
    expect((new MaintenanceSchedule(['status' => 'active']))->status_badge)->toContain('bg-success/15 text-success')
        ->and((new MaintenanceSchedule(['status' => 'paused']))->status_badge)->toContain('bg-warning/15 text-warning')
        ->and((new MaintenanceSchedule(['status' => 'completed']))->status_badge)->toContain('bg-info/15 text-info');

    $log = new ServiceLog;
    $log->setRelation('maintenanceCategories', collect([(object) ['name' => 'Brakes']]));
    expect($log->categories_html)->toContain('bg-info/15 text-info')->toContain('Brakes');
});

it('no longer defines the gray and brand scales in app.css', function () {
    expect(File::get(resource_path('css/app.css')))
        ->not->toContain('--color-gray-')
        ->not->toContain('--color-brand-');
});

it('renders the token-based pages', function (string $route) {
    $owner = Actors::companyOwner();
    $url = $route === 'admin.settings.company.edit' ? route($route, $owner->company_id) : route($route);

    expect($this->actingAs($owner)->get($url)->assertOk()->getContent())->not->toMatch('/-(gray|brand)-\d/');
})->with(['admin.roles.index', 'admin.roles.create', 'admin.settings.company.edit', 'admin.settings.policy.pdf', 'users.index', 'users.invite']);

it('keeps the welcome footer dark in both themes', function () {
    $this->get('/')->assertOk()
        // The sidebar tokens: a .dark wrapper does not re-resolve bg-background below :root.
        ->assertSee('<footer class="bg-sidebar text-sidebar-foreground', false)
        ->assertDontSee('-gray-', false)
        ->assertDontSee('-brand-5', false);
});

it('keeps the auth panel gradient as a mix of the brand colour', function () {
    $this->get(route('login'))->assertOk()
        ->assertSee('from-[color-mix(in_oklch,var(--primary)_74%,black)]', false);
});

it('serves missing pages from the x-ui 404 page, noindex and with a 404 status', function () {
    $this->get('/this-page-does-not-exist-'.uniqid())
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee(['Page not found', 'Back to home'])
        ->assertSee('class="empty border', false)
        ->assertDontSee('TailAdmin');

    expect(view()->exists('404'))->toBeFalse()
        ->and(view()->exists('partials.preloader'))->toBeFalse();
});

it('still forbids the users list without users.view', function () {
    $this->actingAs(Actors::memberWithPermissions(Actors::company(), ['drivers.view']))
        ->get(route('users.index'))
        ->assertForbidden();
});
