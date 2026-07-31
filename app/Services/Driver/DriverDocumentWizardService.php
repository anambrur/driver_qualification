<?php

namespace App\Services\Driver;

use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Violation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DriverDocumentWizardService
{
    public function saveLicense(Driver $driver, ?UploadedFile $front, ?UploadedFile $back): DriverDocument
    {
        return DB::transaction(function () use ($driver, $front, $back) {
            $existing = DriverDocument::where('driver_id', $driver->id)->first();
            $licenseFront = $existing?->license_front;
            $licenseBack = $existing?->license_back;

            if ($front) {
                $licenseFront = $this->replaceFile($existing?->license_front, $front, 'license_front');
            }

            if ($back) {
                $licenseBack = $this->replaceFile($existing?->license_back, $back, 'license_back');
            }

            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'license_front' => $licenseFront,
                    'license_back' => $licenseBack,
                ]
            );
        });
    }

    public function saveMedicalCard(Driver $driver, ?UploadedFile $file): DriverDocument
    {
        return DB::transaction(function () use ($driver, $file) {
            $existing = DriverDocument::where('driver_id', $driver->id)->first();
            $medicalCard = $existing?->medical_card;

            if ($file) {
                $medicalCard = $this->replaceFile($existing?->medical_card, $file, 'medical_card');
            }

            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                ['medical_card' => $medicalCard]
            );
        });
    }

    public function saveForfeiture(Driver $driver, ?UploadedFile $file): DriverDocument
    {
        return DB::transaction(function () use ($driver, $file) {
            $existing = DriverDocument::where('driver_id', $driver->id)->first();
            $forfeiture = $existing?->forfeiture_document;

            if ($file) {
                $forfeiture = $this->replaceFile($existing?->forfeiture_document, $file, 'forfeiture_document');
            }

            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                ['forfeiture_document' => $forfeiture]
            );
        });
    }

    public function saveViolationRecord(Driver $driver, array $data): DriverDocument
    {
        return DB::transaction(function () use ($driver, $data) {
            Violation::where('driver_id', $driver->id)->delete();

            $signature = $data['applicant_signature'];
            $dateSigned = $data['date_signed'];

            if (($data['violation'] ?? 'no') === 'no') {
                Violation::create([
                    'driver_id' => $driver->id,
                    'violation' => 'no',
                    'violation_record_signature' => $signature,
                    'violation_record_date_signed' => $dateSigned,
                ]);
            } else {
                $hasViolations = false;

                foreach ($data['violation_date'] ?? [] as $index => $date) {
                    if (! empty(trim($date ?? ''))) {
                        $hasViolations = true;
                        Violation::create([
                            'driver_id' => $driver->id,
                            'violation' => 'yes',
                            'violation_date' => $date,
                            'violation_location' => $data['violation_location'][$index] ?? null,
                            'offense' => $data['offense'][$index] ?? null,
                            'vehicle_type' => $data['vehicle_type'][$index] ?? null,
                            'violation_record_signature' => $signature,
                            'violation_record_date_signed' => $dateSigned,
                        ]);
                    }
                }

                if (! $hasViolations) {
                    Violation::create([
                        'driver_id' => $driver->id,
                        'violation' => 'no',
                        'violation_record_signature' => $signature,
                        'violation_record_date_signed' => $dateSigned,
                    ]);
                }
            }

            return DriverDocument::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'violation_record_signature' => $signature,
                    'violation_record_date_signed' => $dateSigned,
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

            if ($finalizeToPending) {
                $driver->update(['status' => 'pending']);
            }

            return $document;
        });
    }

    public function existingDocument(int $driverId): ?DriverDocument
    {
        return DriverDocument::where('driver_id', $driverId)->first();
    }

    private function replaceFile(?string $oldPath, UploadedFile $file, string $prefix): string
    {
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $fileName = $prefix . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        return $file->storeAs('images/documents', $fileName, 'public');
    }
}
