<?php

use App\Models\Driver;
use App\Models\DriverDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\PublicApplication\Applicant;
use Tests\Support\Actors;

/*
| DRV-03: driver photos, licence front/back, medical cards and forfeiture documents were
| stored on the *public* disk, so anyone with the URL could read them at /storage/... without
| logging in. They now live on the private `local` disk and are served by authorized routes:
| admin.driver.file (tenant of the driver, or super-admin) and public.application.file
| (the applicant's own session).
| DRV-04: SVG photos. DRV-05: same-second photo names. Both were already closed by APP-10 and
| Laravel 12's `image` rule; the tests below stay as regression guards.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function wizardUploads(Driver $driver): array
{
    return [
        ['admin.driver.license.store', ['license_front' => DriverAdmin::png(), 'license_back' => DriverAdmin::png()], ['license_front', 'license_back']],
        ['admin.driver.medical.card.store', ['medical_card' => DriverAdmin::png()], ['medical_card']],
        ['admin.driver.forfeiture.store', ['forfeiture_document' => DriverAdmin::png()], ['forfeiture_document']],
    ];
}

function driverWithStoredFiles(Driver $driver): DriverDocument
{
    $paths = [];
    foreach (['license_front', 'license_back', 'medical_card', 'forfeiture_document'] as $field) {
        $paths[$field] = "images/documents/{$field}_{$driver->id}.png";
        Storage::disk('local')->put($paths[$field], "bytes-of-{$field}");
    }
    $driver->update(['photo' => "images/drivers/driver_photo_{$driver->id}.png"]);
    Storage::disk('local')->put($driver->photo, 'bytes-of-photo');

    return DriverDocument::create(['driver_id' => $driver->id] + $paths);
}

dataset('driver file fields', ['photo', 'license_front', 'license_back', 'medical_card', 'forfeiture_document']);

describe('DRV-03: driver documents are private', function () {
    it('stores wizard uploads on the private disk', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();

        foreach (wizardUploads($driver) as [$route, $files, $fields]) {
            $this->actingAs($owner)->post(route($route), ['driver_id' => $driver->id] + $files)
                ->assertSessionHasNoErrors()->assertRedirect();

            $doc = DriverDocument::where('driver_id', $driver->id)->firstOrFail();
            foreach ($fields as $field) {
                Storage::disk('local')->assertExists($doc->{$field});
                Storage::disk('public')->assertMissing($doc->{$field});
            }
        }
    });

    it('stores the photo from the admin create form on the private disk', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)
            ->post(route('admin.driver.store'), DriverAdmin::payload(['photo' => DriverAdmin::png()]))
            ->assertSessionHasNoErrors();

        $photo = Driver::query()->latest('id')->value('photo');
        Storage::disk('local')->assertExists($photo);
        Storage::disk('public')->assertMissing($photo);
    });

    it('stores public application uploads on the private disk', function () {
        Applicant::fakeOtp();
        $me = Applicant::draft();

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step2', $me->company->slug), Applicant::stepPayload(2, $me))
            ->assertSessionHasNoErrors();

        $doc = DriverDocument::where('driver_id', $me->id)->firstOrFail();
        Storage::disk('local')->assertExists($doc->license_front);
        Storage::disk('public')->assertMissing($doc->license_front);
    });

    it('serves each file to the driver\'s tenant', function (string $field) {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        driverWithStoredFiles($driver);

        $response = $this->actingAs($owner)->get(route('admin.driver.file', [$driver->id, $field]))->assertOk();

        expect($response->streamedContent())->toBe("bytes-of-{$field}")
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    })->with('driver file fields');

    it('lets a super-admin open any company\'s file', function () {
        [, $driver] = DriverAdmin::tenantWithDriver();
        driverWithStoredFiles($driver);

        $this->actingAs(Actors::superAdmin())->get(route('admin.driver.file', [$driver->id, 'license_front']))->assertOk();
    });

    it('refuses another tenant', function (string $field) {
        [, $driver] = DriverAdmin::tenantWithDriver();
        driverWithStoredFiles($driver);

        $this->actingAs(Actors::companyOwner())->get(route('admin.driver.file', [$driver->id, $field]))->assertForbidden();
    })->with('driver file fields');

    it('refuses guests and users without driver permissions', function () {
        [, $driver] = DriverAdmin::tenantWithDriver();
        driverWithStoredFiles($driver);

        $this->get(route('admin.driver.file', [$driver->id, 'license_front']))->assertRedirect(route('login'));
        $this->actingAs(Actors::plainUser())->get(route('admin.driver.file', [$driver->id, 'license_front']))->assertForbidden();
    });

    it('returns 404 for unknown fields and missing files', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();

        $this->actingAs($owner)->get(route('admin.driver.file', [$driver->id, 'ssn']))->assertNotFound();
        $this->actingAs($owner)->get(route('admin.driver.file', [$driver->id, 'license_front']))->assertNotFound();
    });

    it('still serves files uploaded before the move, from the public disk', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        Storage::disk('public')->put('images/documents/old.png', 'legacy');
        DriverDocument::create(['driver_id' => $driver->id, 'license_front' => 'images/documents/old.png']);

        $response = $this->actingAs($owner)->get(route('admin.driver.file', [$driver->id, 'license_front']))->assertOk();
        expect($response->streamedContent())->toBe('legacy');
    });

    it('links the admin pages to the authorized route, never to /storage', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver();
        driverWithStoredFiles($driver);
        $this->actingAs($owner);

        $pages = [
            route('admin.driver.license', $driver->id) => ['license_front', 'license_back'],
            route('admin.driver.medical.card', $driver->id) => ['medical_card'],
            route('admin.driver.forfeiture', $driver->id) => ['forfeiture_document'],
            route('admin.driver.show', $driver->id) => ['photo'],
            route('admin.driver.edit', $driver->id) => ['photo'],
        ];

        foreach ($pages as $url => $fields) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach ($fields as $field) {
                expect($html)->toContain(e(route('admin.driver.file', [$driver->id, $field])));
            }
            expect($html)->not->toContain('/storage/images/');
        }

        $row = $this->withHeaders(DriverAdmin::ajax())->get(route('admin.driver.index', ['draw' => 1]))->json('data.0');
        expect($row['photo'])->toContain(e(route('admin.driver.file', [$driver->id, 'photo'])));
    });

    it('serves an applicant their own uploads, and nobody else', function () {
        Applicant::fakeOtp();
        $me = Applicant::draft();
        $other = Applicant::draft($me->company);
        driverWithStoredFiles($me);
        $slug = $me->company->slug;

        $this->withSession(Applicant::session($me))
            ->get(route('public.application.file', [$slug, $me->id, 'license_front']))
            ->assertOk();

        $this->withSession(Applicant::session($other))
            ->get(route('public.application.file', [$slug, $me->id, 'license_front']))
            ->assertRedirect();

        $this->flushSession();
        $this->get(route('public.application.file', [$slug, $me->id, 'license_front']))->assertRedirect();

        $html = $this->withSession(Applicant::session($me))
            ->get(route('public.application.step2', [$slug, $me->id]))->assertOk()->getContent();
        expect($html)->toContain(e(route('public.application.file', [$slug, $me->id, 'license_front'])))
            ->and($html)->not->toContain('/storage/images/');
    });

    it('moves files already on the public disk to the private disk, and can move them back', function () {
        [, $driver] = DriverAdmin::tenantWithDriver();
        DB::table('drivers')->where('id', $driver->id)->update(['photo' => 'images/drivers/p.png']);
        DriverDocument::create(['driver_id' => $driver->id, 'license_front' => 'images/documents/f.png', 'medical_card' => 'images/documents/missing.png']);
        Storage::disk('public')->put('images/drivers/p.png', 'photo');
        Storage::disk('public')->put('images/documents/f.png', 'front');

        $migration = require database_path('migrations/2026_10_09_000700_move_driver_files_to_private_disk.php');
        $migration->up();

        Storage::disk('public')->assertMissing(['images/drivers/p.png', 'images/documents/f.png']);
        expect(Storage::disk('local')->get('images/drivers/p.png'))->toBe('photo')
            ->and(Storage::disk('local')->get('images/documents/f.png'))->toBe('front');

        $migration->down();

        Storage::disk('local')->assertMissing('images/documents/f.png');
        Storage::disk('public')->assertExists(['images/drivers/p.png', 'images/documents/f.png']);
    });
});

describe('DRV-04/DRV-05: photo uploads (regression guards)', function () {
    it('rejects an SVG photo', function () {
        $owner = Actors::companyOwner();
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($owner)
            ->post(route('admin.driver.store'), DriverAdmin::payload(['photo' => $svg]))
            ->assertSessionHasErrors('photo');

        expect(Driver::count())->toBe(0);
    });

    it('stores the photo under a random name with the extension of its content', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)
            ->post(route('admin.driver.store'), DriverAdmin::payload(['photo' => DriverAdmin::png('evil.svg')]))
            ->assertSessionHasNoErrors();

        expect(Driver::query()->latest('id')->value('photo'))->toEndWith('.png')->not->toContain('evil');
    });

    it('gives two photos uploaded in the same second different names', function () {
        $this->freezeSecond();
        $owner = Actors::companyOwner();

        $this->actingAs($owner)->post(route('admin.driver.store'), DriverAdmin::payload(['photo' => DriverAdmin::png()]));
        $this->actingAs($owner)->post(route('admin.driver.store'), DriverAdmin::payload(['photo' => DriverAdmin::png()]));

        $photos = Driver::query()->pluck('photo');
        expect($photos)->toHaveCount(2)
            ->and($photos->unique())->toHaveCount(2);
    });
});
