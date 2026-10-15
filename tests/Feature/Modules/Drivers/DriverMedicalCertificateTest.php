<?php

use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Files;

/*
| F17: the wizard's medical card is also the driver's medical examiner's certificate in the
| compliance engine: the same stored file, with the expiry entered beside it.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->type = DocumentTypeFactory::new()->requiresExpiry()->create(['system_key' => Driver::MEDICAL_CERTIFICATE_KEY]);
});

function certificateOf(Driver $driver): ?DriverComplianceDocument
{
    return DriverComplianceDocument::withoutTenancy()->where('driver_id', $driver->id)->first();
}

it('files the uploaded card as the certificate, with its expiry', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();

    $this->actingAs($owner)->post(route('admin.driver.medical.card.store'), [
        'driver_id' => $driver->id, 'medical_card' => DriverAdmin::png(), 'medical_certificate_expiration_date' => now()->addYear()->toDateString(),
    ])->assertSessionHasNoErrors();

    $certificate = certificateOf($driver);
    expect($certificate->document_type_id)->toBe($this->type->id)
        ->and($certificate->expiry_date->toDateString())->toBe(now()->addYear()->toDateString())
        ->and($certificate->file_id)->toBe($driver->driver_documents()->value('medical_card_file_id'));
});

it('requires a card and a future expiry while the driver has no certificate', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();

    $this->actingAs($owner)->post(route('admin.driver.medical.card.store'), ['driver_id' => $driver->id])
        ->assertSessionHasErrors(['medical_card', 'medical_certificate_expiration_date']);
    $this->actingAs($owner)->post(route('admin.driver.medical.card.store'), [
        'driver_id' => $driver->id, 'medical_card' => DriverAdmin::png(), 'medical_certificate_expiration_date' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('medical_certificate_expiration_date');

    expect(certificateOf($driver))->toBeNull();
});

it('keeps the certificate, its file and an expired date when the step is saved without a new card', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();
    $this->actingAs($owner)->post(route('admin.driver.medical.card.store'), [
        'driver_id' => $driver->id, 'medical_card' => DriverAdmin::png(), 'medical_certificate_expiration_date' => now()->addYear()->toDateString(),
    ]);
    // A renewed certificate filed elsewhere, now expired
    $renewed = Files::stored($driver->company_id);
    certificateOf($driver)->update(['file_id' => $renewed->id, 'expiry_date' => now()->subMonth()->toDateString()]);

    $this->actingAs($owner)->post(route('admin.driver.medical.card.store'), [
        'driver_id' => $driver->id, 'medical_certificate_expiration_date' => now()->subMonth()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(certificateOf($driver))->file_id->toBe($renewed->id)
        ->and(certificateOf($driver)->expiry_date->toDateString())->toBe(now()->subMonth()->toDateString());
    Storage::disk('local')->assertExists($renewed->path);
});
