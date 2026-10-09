<?php

use App\Models\DriverDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-10 (found during this run): uploads from the public, unauthenticated application were
| stored on the public disk under the *client's* file extension. A valid PNG named `x.html`
| (or `.svg`, `.phtml`…) passes `image|mimes:png`, because content is checked, not the name,
| and was then served from /storage as HTML (stored XSS on the app's origin). Files must be
| stored with $file->hashName(), whose extension comes from the content.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local'); // DRV-03: driver files live on the private disk
    Applicant::fakeOtp();
});

function pngNamed(string $clientName): UploadedFile
{
    // The fake's tmpfile() disappears with the object, so keep a copy for the request.
    $fake = UploadedFile::fake()->image('real.png', 20, 20);
    $path = tempnam(sys_get_temp_dir(), 'png');
    copy($fake->getPathname(), $path);

    return new UploadedFile($path, $clientName, 'image/png', null, true);
}

dataset('dangerous client names', ['evil.html', 'evil.svg', 'evil.htm', 'evil.js']);

describe('APP-10: public uploads are stored under a server-chosen name', function () {
    it('stores licence images with the extension of their content', function (string $clientName) {
        $me = Applicant::draft();

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step2', $me->company->slug), [
                'driver_id' => $me->id,
                'license_front' => pngNamed($clientName),
                'license_back' => pngNamed($clientName),
            ])->assertSessionHasNoErrors();

        $doc = DriverDocument::query()->where('driver_id', $me->id)->firstOrFail();

        expect($doc->license_front)->toEndWith('.png')
            ->and($doc->license_back)->toEndWith('.png')
            ->and($doc->license_front)->not->toContain('evil');
        Storage::disk('local')->assertExists($doc->license_front);
    })->with('dangerous client names');

    it('stores the medical card and forfeiture document with the extension of their content', function () {
        $me = Applicant::draft();
        $slug = $me->company->slug;

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step3', $slug), ['driver_id' => $me->id, 'medical_card' => pngNamed('evil.html')])
            ->assertSessionHasNoErrors();
        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step4', $slug), ['driver_id' => $me->id, 'forfeiture_document' => pngNamed('evil.html')])
            ->assertSessionHasNoErrors();

        $doc = DriverDocument::query()->where('driver_id', $me->id)->firstOrFail();

        expect($doc->medical_card)->toEndWith('.png')
            ->and($doc->forfeiture_document)->toEndWith('.png');
    });

    it('stores the step 1 photo with the extension of its content', function () {
        $me = Applicant::draft();

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step1', $me->company->slug), Applicant::step1Payload($me, ['photo' => pngNamed('evil.html')]))
            ->assertSessionHasNoErrors();

        expect($me->fresh()->photo)->toStartWith('images/drivers/')->toEndWith('.png');
        Storage::disk('local')->assertExists($me->fresh()->photo);
    });

    it('gives two uploads in the same second different names', function () {
        $company = Applicant::company();
        $a = Applicant::draft($company);
        $b = Applicant::draft($company);

        foreach ([$a, $b] as $driver) {
            $this->withSession(Applicant::session($driver))
                ->post(route('public.application.store.step1', $company->slug), Applicant::step1Payload($driver, [
                    'email' => "d{$driver->id}@example.com",
                    'photo' => pngNamed('photo.png'),
                ]))->assertSessionHasNoErrors();
        }

        expect($a->fresh()->photo)->not->toBe($b->fresh()->photo);
    });
});
