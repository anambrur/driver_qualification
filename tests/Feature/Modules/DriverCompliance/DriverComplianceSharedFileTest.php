<?php

use App\Models\DriverComplianceDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;

/*
| DCMP-02: "upload to all" stores ONE file and points every driver's document at it.
| Replacing or deleting one driver's document deleted that shared file, so every other
| driver's document lost its file. A file is now only deleted once no document uses it.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function uploadToAll($test, $owner, $type): string
{
    $test->actingAs($owner)
        ->post(route('admin.compliance.driver.documents.upload'), [
            'document_type_id' => $type->id,
            'asset_type' => 'driver',
            'upload_to_all' => '1',
            'expiry_date' => now()->addYear()->toDateString(),
            'file' => DriverCompliance::pdf('policy.pdf'),
        ], DriverCompliance::json())
        ->assertOk()->assertJson(['success' => true, 'uploaded_count' => 3]);

    return DriverComplianceDocument::where('document_type_id', $type->id)->value('file_path');
}

describe('DCMP-02: a file shared by "upload to all"', function () {
    it('stays for the other drivers when one driver\'s document is replaced', function () {
        [$owner, $a] = DriverCompliance::tenantWithDriver();
        $b = DriverCompliance::driverOf($owner);
        DriverCompliance::driverOf($owner);
        $type = DriverCompliance::documentType();
        $shared = uploadToAll($this, $owner, $type);

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($a, $type), DriverCompliance::json())
            ->assertOk();

        Storage::disk('local')->assertExists($shared);
        expect(DriverComplianceDocument::where('driver_id', $b->id)->value('file_path'))->toBe($shared);
        $this->actingAs($owner)
            ->get(route('admin.compliance.driver.documents.view', DriverComplianceDocument::where('driver_id', $b->id)->value('id')))
            ->assertOk();
    });

    it('stays for the other drivers when one driver\'s document is deleted', function () {
        [$owner, $a] = DriverCompliance::tenantWithDriver();
        DriverCompliance::driverOf($owner);
        DriverCompliance::driverOf($owner);
        $type = DriverCompliance::documentType();
        $shared = uploadToAll($this, $owner, $type);

        $this->actingAs($owner)
            ->deleteJson(route('admin.compliance.driver.documents.delete'), [
                'document_id' => DriverComplianceDocument::where('driver_id', $a->id)->value('id'),
            ])
            ->assertOk();

        Storage::disk('local')->assertExists($shared);
        expect(DriverComplianceDocument::where('file_path', $shared)->count())->toBe(2);
    });

    it('is deleted once the last document using it is replaced', function () {
        [$owner, $a] = DriverCompliance::tenantWithDriver();
        $b = DriverCompliance::driverOf($owner);
        $c = DriverCompliance::driverOf($owner);
        $type = DriverCompliance::documentType();
        $shared = uploadToAll($this, $owner, $type);

        foreach ([$a, $b, $c] as $driver) {
            $this->actingAs($owner)
                ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($driver, $type), DriverCompliance::json())
                ->assertOk();
        }

        Storage::disk('local')->assertMissing($shared);
    });

    it('replaces every driver\'s shared file when "upload to all" runs again, and deletes the old one', function () {
        [$owner] = DriverCompliance::tenantWithDriver();
        DriverCompliance::driverOf($owner);
        DriverCompliance::driverOf($owner);
        $type = DriverCompliance::documentType();
        $first = uploadToAll($this, $owner, $type);

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), [
                'document_type_id' => $type->id,
                'asset_type' => 'driver',
                'upload_to_all' => '1',
                'file' => DriverCompliance::pdf('policy-v2.pdf'),
            ], DriverCompliance::json())
            ->assertOk()->assertJson(['updated_count' => 3]);

        $second = DriverComplianceDocument::where('document_type_id', $type->id)->pluck('file_path')->unique();
        expect($second)->toHaveCount(1)->and($second->first())->not->toBe($first);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second->first());
    });

    it('still deletes the file of a document nobody else uses', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType());

        $this->actingAs($owner)
            ->deleteJson(route('admin.compliance.driver.documents.delete'), ['document_id' => $document->id])
            ->assertOk();

        Storage::disk('local')->assertMissing($document->file_path);
        expect(DriverComplianceDocument::count())->toBe(0);
    });
});
