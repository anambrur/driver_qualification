<?php

use App\Support\Ui\Toast;
use Illuminate\Support\Facades\File;
use Tests\Feature\Modules\PublicApplication\Applicant;
use Tests\Support\Actors;

/*
| UX follow-ups part D: php-flasher/toastr (and its own jQuery) is gone. App\Support\Ui\Toast flashes
| toasts to the session and x-ui.toaster shows them on the next page through window.notify(), which
| escapes the text. Every layout that can receive a toast renders x-ui.toaster.
*/

/** @return array<string, Closure(): string> */
function toastPages(): array
{
    return [
        'main' => function () {
            test()->actingAs(Actors::companyOwner());

            return route('dashboard');
        },
        'platform' => function () {
            test()->actingAs(Actors::platformOwner(), 'platform');

            return route('platform.dashboard');
        },
        'application form' => fn () => route('application.form', Applicant::company()->slug),
        'auth' => fn () => route('register'),
    ];
}

describe('Toast helper', function () {
    it('flashes each toast in order with its category', function () {
        Toast::success('Saved.');
        Toast::error('Failed.');
        Toast::warning('Careful.');
        Toast::info('Note.');

        expect(session(Toast::SESSION_KEY))->toBe([
            ['category' => 'success', 'message' => 'Saved.'],
            ['category' => 'error', 'message' => 'Failed.'],
            ['category' => 'warning', 'message' => 'Careful.'],
            ['category' => 'info', 'message' => 'Note.'],
        ])->and(session('_flash.new'))->toContain(Toast::SESSION_KEY);
    });
});

describe('toasts on the next page', function () {
    it('shows every toast type through window.notify', function (Closure $page) {
        $url = $page();
        $toasts = collect(['success', 'error', 'warning', 'info'])
            ->map(fn ($category) => ['category' => $category, 'message' => ucfirst($category).' message'])->all();

        $html = $this->withSession([Toast::SESSION_KEY => $toasts])->get($url)->assertOk()->getContent();

        expect($html)->toContain('<div id="toaster" class="toaster"')
            ->toContain((string) Js::from($toasts))
            ->toContain('window.notify(toast.category, toast.message)');
    })->with(toastPages());

    it('renders no toast script without toasts', function (Closure $page) {
        $this->get($page())->assertOk()
            ->assertSee('id="toaster"', false)
            ->assertDontSee('window.notify(toast.category', false);
    })->with(toastPages());

    it('shows a toast once', function () {
        $owner = Actors::companyOwner();
        Toast::success('Only once.');

        $this->actingAs($owner)->get(route('dashboard'))->assertSee('Only once.');
        $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('Only once.');
    });

    it('shows the subscription warning on the pricing page', function () {
        $this->actingAs(Actors::companyOwner(Actors::company(subscribed: false)))
            ->followingRedirects()
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertSee((string) Js::from([['category' => 'warning', 'message' => 'An active subscription is required. Please choose a plan.']]), false);
    });

    it('escapes the message so it cannot close the script tag', function () {
        $payload = '</script><img src=x onerror=alert(1)>"\'&';

        $html = $this->actingAs(Actors::companyOwner())
            ->withSession([Toast::SESSION_KEY => [['category' => 'error', 'message' => $payload]]])
            ->get(route('dashboard'))->assertOk()->getContent();

        expect($html)->not->toContain($payload)
            ->not->toContain('<img src=x onerror')
            ->toContain((string) Js::from([['category' => 'error', 'message' => $payload]]))
            ->and(File::get(resource_path('js/app.js')))
            ->toContain('title: escapeHtml(title), description: escapeHtml(description)');
    });
});

describe('php-flasher is gone', function () {
    it('loads no flasher, toastr or separate jQuery on any layout', function (Closure $page) {
        $url = $page();

        $this->withSession([Toast::SESSION_KEY => [['category' => 'success', 'message' => 'Saved.']]])
            ->get($url)->assertOk()
            ->assertDontSee(['flasher', 'toastr', 'jquery'], false);
    })->with(toastPages());

    it('has no package, assets or calls left', function () {
        expect(File::get(base_path('composer.json')))->not->toContain('php-flasher')
            ->and(File::get(base_path('composer.lock')))->not->toContain('php-flasher')
            ->and(File::exists(public_path('vendor/flasher')))->toBeFalse()
            ->and(collect(File::allFiles(app_path()))->filter(fn ($file) => str_contains($file->getContents(), 'toastr'))->all())->toBe([]);
    });
});
