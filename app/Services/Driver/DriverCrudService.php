<?php

namespace App\Services\Driver;

use App\Models\Country;
use App\Models\Driver;
use App\Models\State;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DriverCrudService
{
    public function create(array $data, int $userId, ?UploadedFile $photo = null): Driver
    {
        return DB::transaction(function () use ($data, $userId, $photo) {
            $ssn = $this->stripSsn($data['ssn'] ?? '');
            $countryName = $this->resolveCountryName($data['country'] ?? null);
            $stateName = $this->resolveStateName($data['state'] ?? null);
            $photoPath = $this->storePhoto($photo);

            $driver = Driver::create([
                'user_id' => $userId,
                'company_id' => $data['company_id'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'suffix' => $data['suffix'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'ssn' => $ssn,
                'main_phone' => $data['main_phone'] ?? null,
                'alt_phone' => $data['alt_phone'] ?? null,
                'email' => $data['email'] ?? null,
                'medical_certificate_expiration_date' => $data['medical_certificate_expiration_date'] ?? null,
                'photo' => $photoPath,
                'business_name' => $data['business_name'] ?? null,
                'employer_identification_number' => $data['employer_identification_number'] ?? null,
                'federal_tax_classification' => $data['federal_tax_classification'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $stateName,
                'country' => $countryName,
                'postal_code' => $data['postal_code'] ?? null,
                'twic_card' => filter_var($data['twic_card'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'passport' => filter_var($data['passport'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'status' => 'draft',
            ]);

            $this->syncResidences($driver, $data, resolvePlaceNames: true);
            $this->insertLicense($driver, $data);
            $this->syncExperiences($driver, $data);
            $this->syncAccidents($driver, $data);
            $this->syncViolations($driver, $data);
            $this->syncForfeitures($driver, $data);
            $this->syncEmploymentRecords($driver, $data, resolvePlaceNames: true);

            return $driver;
        });
    }

    public function update(Driver $driver, array $data, ?UploadedFile $photo = null): Driver
    {
        return DB::transaction(function () use ($driver, $data, $photo) {
            $ssn = $this->stripSsn($data['ssn'] ?? '');
            $photoPath = $this->storePhoto($photo, $driver->photo);

            $driver->update([
                'company_id' => $data['company_id'] ?? $driver->company_id,
                'first_name' => $data['first_name'] ?? null,
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'suffix' => $data['suffix'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'ssn' => $ssn,
                'main_phone' => $data['main_phone'] ?? null,
                'alt_phone' => $data['alt_phone'] ?? null,
                'email' => $data['email'] ?? null,
                'business_name' => $data['business_name'] ?? null,
                'employer_identification_number' => $data['employer_identification_number'] ?? null,
                'federal_tax_classification' => $data['federal_tax_classification'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $this->resolveStateName($data['state'] ?? null),
                'country' => $this->resolveCountryName($data['country'] ?? null),
                'postal_code' => $data['postal_code'] ?? null,
                'twic_card' => filter_var($data['twic_card'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'passport' => filter_var($data['passport'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'status' => $data['status'] ?? $driver->status,
                'medical_certificate_expiration_date' => $data['medical_certificate_expiration_date'] ?? null,
                'photo' => $photoPath ?? $driver->photo,
            ]);

            if (!empty($data['license_number'])) {
                $this->upsertLicense($driver, $data);
            }

            $driver->residence_addresses()->delete();
            $this->syncResidences($driver, $data, resolvePlaceNames: false);

            $driver->experiences()->delete();
            $this->syncExperiences($driver, $data);

            $driver->accidents()->delete();
            $this->syncAccidents($driver, $data);

            $driver->violations()->delete();
            $this->syncViolations($driver, $data);

            $driver->forfeitures()->delete();
            $this->syncForfeitures($driver, $data);

            $driver->employment_records()->delete();
            $this->syncEmploymentRecords($driver, $data, resolvePlaceNames: false);

            return $driver->fresh();
        });
    }

    private function resolvePlaceName($value, string $type = 'country'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            if ($type === 'state') {
                return State::find($value)?->name;
            }

            return Country::find($value)?->name ?? (string) $value;
        }

        return (string) $value;
    }

    private function resolveCountryName($value): ?string
    {
        return $this->resolvePlaceName($value, 'country');
    }

    private function resolveStateName($value): ?string
    {
        return $this->resolvePlaceName($value, 'state');
    }

    private function stripSsn(string $ssn): string
    {
        return preg_replace('/[^0-9]/', '', $ssn) ?? '';
    }

    private function storePhoto(?UploadedFile $photo, ?string $oldPath = null): ?string
    {
        if (!$photo) {
            return $oldPath;
        }

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $extension = $photo->getClientOriginalExtension();
        $fileName = 'driver_photo_' . time() . '.' . $extension;

        return $photo->storeAs('images/drivers', $fileName, 'public');
    }

    private function insertLicense(Driver $driver, array $data): void
    {
        $licenseCountry = $this->resolveCountryName($data['license_country'] ?? null);
        $licenseState = $this->resolveStateName($data['license_state'] ?? null);

        DB::table('licenses')->insert([
            'driver_id' => $driver->id,
            'first_name' => $data['license_first_name'] ?? null,
            'last_name' => $data['license_last_name'] ?? null,
            'issued' => $data['license_issued'] ?? null,
            'expires' => $data['license_expires'] ?? null,
            'country' => $licenseCountry,
            'state' => $licenseState,
            'class' => $data['license_class'] ?? null,
            'license_number' => $data['license_number'] ?? null,
            'repeat_license_number' => $data['repeat_license_number'] ?? null,
            'is_h_placarded_hazmat' => filter_var($data['is_h_placarded_hazmat'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_n_tank_vehicle' => filter_var($data['is_n_tank_vehicle'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_p_passengers' => filter_var($data['is_p_passengers'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_t_double_trailer' => filter_var($data['is_t_double_trailer'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_s_school_bus' => filter_var($data['is_s_school_bus'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_x_placarded_hazmat' => filter_var($data['is_x_placarded_hazmat'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertLicense(Driver $driver, array $data): void
    {
        $licenseCountry = $this->resolveCountryName($data['license_country'] ?? null);
        $licenseState = $this->resolveStateName($data['license_state'] ?? null);

        $licensePayload = [
            'first_name' => $data['license_first_name'] ?? null,
            'last_name' => $data['license_last_name'] ?? null,
            'issued' => $data['license_issued'] ?? null,
            'expires' => $data['license_expires'] ?? null,
            'country' => $licenseCountry,
            'state' => $licenseState,
            'class' => $data['license_class'] ?? null,
            'license_number' => $data['license_number'] ?? null,
            'repeat_license_number' => $data['repeat_license_number'] ?? null,
            'is_h_placarded_hazmat' => filter_var($data['is_h_placarded_hazmat'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_n_tank_vehicle' => filter_var($data['is_n_tank_vehicle'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_p_passengers' => filter_var($data['is_p_passengers'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_t_double_trailer' => filter_var($data['is_t_double_trailer'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_s_school_bus' => filter_var($data['is_s_school_bus'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_x_placarded_hazmat' => filter_var($data['is_x_placarded_hazmat'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        $driver->loadMissing('licenses');

        if ($driver->licenses->isNotEmpty()) {
            $driver->licenses->first()->update($licensePayload);
        } else {
            DB::table('licenses')->insert(array_merge($licensePayload, [
                'driver_id' => $driver->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function syncResidences(Driver $driver, array $data, bool $resolvePlaceNames): void
    {
        if (!isset($data['residence_address']) || !is_array($data['residence_address'])) {
            return;
        }

        $residences = [];
        foreach ($data['residence_address'] as $index => $address) {
            if (empty(trim($address ?? ''))) {
                continue;
            }

            $resCountry = $data['residence_country'][$index] ?? null;
            $resState = $data['residence_state'][$index] ?? null;

            $residences[] = [
                'driver_id' => $driver->id,
                'address' => $address,
                'city' => $data['residence_city'][$index] ?? null,
                'state' => $resolvePlaceNames ? $this->resolveStateName($resState) : $resState,
                'country' => $resolvePlaceNames ? $this->resolveCountryName($resCountry) : $resCountry,
                'zip' => $data['residence_postal_code'][$index] ?? null,
                'is_current' => $index === 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($residences)) {
            DB::table('residence_addresses')->insert($residences);
        }
    }

    private function syncExperiences(Driver $driver, array $data): void
    {
        if (!isset($data['equipment_class']) || !is_array($data['equipment_class'])) {
            return;
        }

        $experiences = [];
        foreach ($data['equipment_class'] as $index => $equipmentClass) {
            if (($data['experience'][$index] ?? 'no') !== 'yes') {
                continue;
            }

            $experiences[] = [
                'driver_id' => $driver->id,
                'equipment_class' => $equipmentClass,
                'experience' => $data['experience'][$index] ?? 'no',
                'from_date' => $data['experience_from_date'][$index] ?? null,
                'to_date' => $data['experience_to_date'][$index] ?? null,
                'approx_miles' => $data['approx_miles'][$index] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($experiences)) {
            DB::table('experiences')->insert($experiences);
        }
    }

    private function syncAccidents(Driver $driver, array $data): void
    {
        if (($data['accident'] ?? null) === 'yes' && !empty($data['accident_date'])) {
            $accidents = [];
            foreach ($data['accident_date'] as $index => $date) {
                if (empty($date)) {
                    continue;
                }

                $accidents[] = [
                    'driver_id' => $driver->id,
                    'accident' => 'yes',
                    'accident_date' => $date,
                    'accident_location' => $data['accident_location'][$index] ?? null,
                    'number_of_injuries' => $data['number_of_injuries'][$index] ?? null,
                    'number_of_fatalities' => $data['number_of_fatalities'][$index] ?? null,
                    'hazmat_spill' => $data['hazmat_spill'][$index] ?? 'no',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($accidents)) {
                DB::table('accidents')->insert($accidents);
            }

            return;
        }

        DB::table('accidents')->insert([
            'driver_id' => $driver->id,
            'accident' => 'no',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncViolations(Driver $driver, array $data): void
    {
        if (($data['violation'] ?? null) === 'yes' && !empty($data['violation_date'])) {
            $violations = [];
            foreach ($data['violation_date'] as $index => $date) {
                if (empty($date)) {
                    continue;
                }

                $violations[] = [
                    'driver_id' => $driver->id,
                    'violation' => 'yes',
                    'violation_date' => $date,
                    'violation_location' => $data['violation_location'][$index] ?? null,
                    'offense' => $data['offense'][$index] ?? null,
                    'vehicle_type' => $data['vehicle_type'][$index] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($violations)) {
                DB::table('violations')->insert($violations);
            }

            return;
        }

        DB::table('violations')->insert([
            'driver_id' => $driver->id,
            'violation' => 'no',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncForfeitures(Driver $driver, array $data): void
    {
        DB::table('forfeitures')->insert([
            'driver_id' => $driver->id,
            'denied_license' => $data['denied_license'] ?? null,
            'license_revoked' => $data['license_revoked'] ?? null,
            'forfeitures' => $data['forfeitures'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncEmploymentRecords(Driver $driver, array $data, bool $resolvePlaceNames): void
    {
        if (!isset($data['employer_name']) || !is_array($data['employer_name'])) {
            return;
        }

        $employmentRecords = [];
        foreach ($data['employer_name'] as $index => $employerName) {
            if (empty(trim($employerName ?? ''))) {
                continue;
            }

            $empCountry = $data['employer_record_country'][$index] ?? null;
            $empState = $data['employer_record_state'][$index] ?? null;

            $employmentRecords[] = [
                'driver_id' => $driver->id,
                'employer_name' => $employerName,
                'employer_record_address' => $data['employer_record_address'][$index] ?? null,
                'employer_record_city' => $data['employer_record_city'][$index] ?? null,
                'employer_record_country' => $resolvePlaceNames ? $this->resolveCountryName($empCountry) : $empCountry,
                'employer_record_state' => $resolvePlaceNames ? $this->resolveStateName($empState) : $empState,
                'employer_record_postal_code' => $data['employer_record_postal_code'][$index] ?? null,
                'employer_record_phone' => $data['employer_record_phone'][$index] ?? null,
                'employer_record_fax' => $data['employer_record_fax'][$index] ?? null,
                'employer_record_email' => $data['employer_record_email'][$index] ?? null,
                'employer_record_position' => $data['employer_record_position'][$index] ?? null,
                'employer_record_date_from' => $data['employer_record_date_from'][$index] ?? null,
                'employer_record_date_to' => $data['employer_record_date_to'][$index] ?? null,
                'employer_record_reason_for_leaving' => $data['employer_record_reason_for_leaving'][$index] ?? null,
                'employed_regulations' => $data['employed_regulations'][$index] ?? 'no',
                'safety_sensitive_function' => $data['safety_sensitive_function'][$index] ?? 'no',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($employmentRecords)) {
            DB::table('employment_records')->insert($employmentRecords);
        }
    }
}
