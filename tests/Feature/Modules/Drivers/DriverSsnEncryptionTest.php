<?php

use App\Models\Driver;
use Database\Factories\DriverFactory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\PublicApplication\Applicant;
use Tests\Support\Actors;

/*
| DRV-02: drivers.ssn was a plain string column with no cast, so every SSN sat in the
| database (and every backup/dump) in clear text.
*/

function rawSsn(int $driverId): ?string
{
    return DB::table('drivers')->where('id', $driverId)->value('ssn');
}

describe('DRV-02: SSNs are encrypted at rest', function () {
    it('encrypts the SSN when a tenant creates a driver', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)
            ->post(route('admin.driver.store'), DriverAdmin::payload(['ssn' => '123-45-6789']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $driver = Driver::query()->latest('id')->firstOrFail();

        expect(rawSsn($driver->id))->not->toContain('123456789')
            ->and(Crypt::decryptString(rawSsn($driver->id)))->toBe('123456789')
            ->and($driver->ssn)->toBe('123456789');
    });

    it('encrypts the SSN when a tenant edits a driver', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'pending']);

        $this->actingAs($owner)
            ->put(route('admin.driver.update', $driver->id), DriverAdmin::updatePayload($driver, ['ssn' => '987-65-4321']))
            ->assertSessionHasNoErrors();

        expect(rawSsn($driver->id))->not->toContain('987654321')
            ->and($driver->fresh()->ssn)->toBe('987654321');
    });

    it('encrypts the SSN an applicant enters on the public step 1', function () {
        Applicant::fakeOtp();
        $me = Applicant::draft();

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step1', $me->company->slug), Applicant::step1Payload($me))
            ->assertSessionHasNoErrors();

        expect(rawSsn($me->id))->not->toContain('123456789')
            ->and($me->fresh()->ssn)->toBe('123456789');
    });

    it('still shows only the last four digits on the driver page', function () {
        [$owner, $driver] = DriverAdmin::tenantWithDriver(['ssn' => '123456789']);

        $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))
            ->assertOk()
            ->assertSee('***-**-6789')
            ->assertDontSee('123456789');
    });

    it('encrypts the SSNs already stored in plain text, once, and can undo it', function () {
        $plain = DriverFactory::new()->create();
        $empty = DriverFactory::new()->create();
        DB::table('drivers')->where('id', $plain->id)->update(['ssn' => '111223333']);
        DB::table('drivers')->where('id', $empty->id)->update(['ssn' => null]);

        $migration = require database_path('migrations/2026_10_09_000600_encrypt_driver_ssn.php');
        $migration->up();
        $once = rawSsn($plain->id);
        $migration->up(); // must not encrypt twice

        expect(rawSsn($plain->id))->toBe($once)
            ->and(Crypt::decryptString($once))->toBe('111223333')
            ->and(rawSsn($empty->id))->toBeNull()
            ->and(Driver::find($plain->id)->ssn)->toBe('111223333');

        $migration->down();

        expect(rawSsn($plain->id))->toBe('111223333');
    });
});
