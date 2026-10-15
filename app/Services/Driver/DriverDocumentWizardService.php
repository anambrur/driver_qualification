<?php

namespace App\Services\Driver;

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Models\DriverDocument;
use App\Models\Violation;
use App\Services\Files\StoredFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverDocumentWizardService
{
    /** Servable files: the photo on `drivers`, the rest on `driver_documents`. */
    public const FILE_FIELDS = ['photo', ...DriverDocument::FILE_FIELDS];

    public function __construct(private StoredFileService $files) {}

    public function saveLicense(Driver $driver, ?UploadedFile $front, ?UploadedFile $back): DriverDocument
    {
        return $this->files->transaction(fn () => $this->saveFiles($driver, ['license_front' => $front, 'license_back' => $back]));
    }

    /**
     * The card is also the driver's medical examiner's certificate in the compliance engine
     * (F17): the same stored file, with the expiry entered beside it.
     */
    public function saveMedicalCard(Driver $driver, ?UploadedFile $file, ?string $expiresOn = null): DriverDocument
    {
        return $this->files->transaction(function () use ($driver, $file, $expiresOn) {
            $document = $this->saveFiles($driver, ['medical_card' => $file]);

            // The expiry belongs to a card: without a new card the certificate stays as it is
            if ($file === null) {
                return $document;
            }

            $certificate = $driver->medicalCertificate()->first();
            $values = ['file_id' => $document->medical_card_file_id, 'expiry_date' => $expiresOn];
            // The system type comes from DocumentTypeSeeder (checked by the seed smoke test)
            $typeId = DocumentType::where('system_key', Driver::MEDICAL_CERTIFICATE_KEY)->value('id');

            if ($certificate) {
                $this->files->purgeWhenUnreferenced($certificate->file_id);
                $certificate->update($values);
            } elseif ($typeId !== null) {
                DriverComplianceDocument::create($values + [
                    'driver_id' => $driver->id,
                    'document_type_id' => $typeId,
                    'uploaded_by_user_id' => Auth::guard('web')->id(),
                ]);
            }

            return $document;
        });
    }

    /**
     * The medical-card step's rules (F17): a driver without a certificate needs a card and a
     * future expiry. With a certificate on file the card is optional, and the expiry only
     * counts (and must be in the future) when a new card comes with it.
     *
     * @return array<string, mixed>
     */
    public static function medicalCardRules(Driver $driver): array
    {
        $hasCertificate = $driver->medicalCertificate()->exists();

        return [
            'medical_card' => [$hasCertificate ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'medical_certificate_expiration_date' => [...($hasCertificate ? ['exclude_without:medical_card'] : []), 'required', 'date', 'after_or_equal:today'],
        ];
    }

    public function saveForfeiture(Driver $driver, ?UploadedFile $file): DriverDocument
    {
        return $this->files->transaction(fn () => $this->saveFiles($driver, ['forfeiture_document' => $file]));
    }

    /**
     * Rows exist only for real violations; the yes/no answer and the signature are on
     * driver_documents (F19c).
     */
    public function saveViolationRecord(Driver $driver, array $data): DriverDocument
    {
        return DB::transaction(function () use ($driver, $data) {
            Violation::where('driver_id', $driver->id)->delete();
            $hasViolations = false;

            if (($data['violation'] ?? 'no') === 'yes') {
                foreach ($data['violation_date'] ?? [] as $index => $date) {
                    if (! empty(trim($date ?? ''))) {
                        $hasViolations = true;
                        Violation::create([
                            'driver_id' => $driver->id,
                            'violation_date' => $date,
                            'violation_location' => $data['violation_location'][$index] ?? null,
                            'offense' => $data['offense'][$index] ?? null,
                            'vehicle_type' => $data['vehicle_type'][$index] ?? null,
                        ]);
                    }
                }
            }

            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'has_violations' => $hasViolations,
                    'violation_record_signature' => $data['applicant_signature'],
                    'violation_record_date_signed' => $data['date_signed'],
                ]
            );
        });
    }

    public function saveAlcoholAndDrugTest(Driver $driver, array $data): DriverDocument
    {
        return DB::transaction(function () use ($driver, $data) {
            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'drug_test_question_1' => $data['drug_test_question_1'],
                    'drug_test_question_2' => $data['drug_test_question_2'],
                    'drug_test_signature' => $data['applicant_signature'],
                    'drug_test_date_signed' => $data['date_signed'],
                ]
            );
        });
    }

    public function saveFmcsaConsent(Driver $driver, array $data): DriverDocument
    {
        return DB::transaction(function () use ($driver, $data) {
            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'fmcsa_consent' => true,
                    'fmcsa_consent_date' => now(),
                    'fmcsa_consent_signature' => $data['employee_signature'],
                    'fmcsa_consent_agreement' => $data['consent_agreement'],
                    'fmcsa_date_signed' => $data['date_signed'],
                ]
            );
        });
    }

    public function savePspAuthorization(Driver $driver, array $data): DriverDocument
    {
        return DB::transaction(function () use ($driver, $data) {
            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'psp_authorization' => true,
                    'psp_authorization_date' => now(),
                    'psp_authorization_signature' => $data['applicant_signature'],
                    'psp_authorization_agreement' => $data['authorization_agreement'],
                ]
            );
        });
    }

    public function saveAlcoholAndDrugTestPolicy(Driver $driver, array $data): DriverDocument
    {
        return DB::transaction(function () use ($driver, $data) {
            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'alcohol_drug_test_policy_signature' => $data['employee_signature'],
                    'alcohol_drug_test_policy_date' => $data['date_signed'],
                ]
            );
        });
    }

    public function saveGeneralWorkPolicy(Driver $driver, array $data, bool $finalizeToPending = false): DriverDocument
    {
        return DB::transaction(function () use ($driver, $data, $finalizeToPending) {
            $document = DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'general_work_policy_signature' => $data['employee_signature'],
                    'general_work_policy_date' => $data['date_signed'],
                ]
            );

            // A public application is submitted; staff-created drivers have no application (F15)
            if ($finalizeToPending && $driver->application_status !== null) {
                $driver->update(['application_status' => 'pending']);
            }

            return $document;
        });
    }

    public function existingDocument(int $driverId): ?DriverDocument
    {
        return DriverDocument::where('driver_id', $driverId)->first();
    }

    /**
     * Stream one of the driver's files. The caller must already have authorized access to $driver.
     */
    public function fileResponse(Driver $driver, string $field): StreamedResponse
    {
        abort_unless(in_array($field, self::FILE_FIELDS, true), 404);

        $file = $field === 'photo'
            ? $driver->photoFile
            : DriverDocument::where('driver_id', $driver->id)->first()?->{Str::camel($field).'File'};

        return $this->files->response($file);
    }

    /**
     * Store the uploaded files on the driver's document row; a replaced file is purged after
     * commit if nothing else uses it.
     *
     * Runs inside the caller's StoredFileService::transaction().
     *
     * @param  array<string, UploadedFile|null>  $uploads  keyed by DriverDocument::FILE_FIELDS
     */
    private function saveFiles(Driver $driver, array $uploads): DriverDocument
    {
        $existing = DriverDocument::where('driver_id', $driver->id)->first();
        $values = [];

        foreach (array_filter($uploads) as $field => $upload) {
            $values["{$field}_file_id"] = $this->files->store($upload, 'driver-files')->id;
            $this->files->purgeWhenUnreferenced($existing?->getAttribute("{$field}_file_id"));
        }

        return DriverDocument::updateOrCreate(['driver_id' => $driver->id], $values);
    }
}
