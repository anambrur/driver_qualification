<?php

namespace App\Services\Driver;

use App\Models\Country;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\State;
use App\Services\Files\StoredFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DriverCrudService
{
    public function __construct(private StoredFileService $files) {}

    /**
     * A driver created by staff: no application, an applicant until hired (F15).
     */
    public function create(array $data, int $userId, ?UploadedFile $photo = null): Driver
    {
        return $this->files->transaction(function () use ($data, $userId, $photo) {
            $driver = Driver::create($this->driverAttributes($data) + [
                'created_by_user_id' => $userId,
                'photo_file_id' => $photo ? $this->files->store($photo, 'driver-photos')->id : null,
                'application_status' => null,
                'employment_status' => 'applicant',
                'source' => 'admin',
            ]);

            $this->syncResidences($driver, $data);
            $this->upsertLicense($driver, $data);
            $this->syncExperiences($driver, $data);
            $this->syncAccidents($driver, $data);
            $this->syncViolations($driver, $data);
            $this->syncForfeitures($driver, $data);
            $this->syncEmploymentRecords($driver, $data);

            return $driver;
        });
    }

    /**
     * The edit form never changes the driver's statuses: hiring and rejecting go through
     * DriverHireService (DRV-06).
     */
    public function update(Driver $driver, array $data, ?UploadedFile $photo = null): Driver
    {
        return $this->files->transaction(function () use ($driver, $data, $photo) {
            $attributes = $this->driverAttributes($data);

            if ($photo) {
                $this->files->purgeWhenUnreferenced($driver->photo_file_id);
                $attributes['photo_file_id'] = $this->files->store($photo, 'driver-photos')->id;
            }

            $driver->update($attributes);

            if (! empty($data['license_number'])) {
                $this->upsertLicense($driver, $data);
            }

            $driver->residence_addresses()->delete();
            $this->syncResidences($driver, $data);

            $driver->experiences()->delete();
            $this->syncExperiences($driver, $data);

            $driver->accidents()->delete();
            $this->syncAccidents($driver, $data);

            $driver->violations()->delete();
            $this->syncViolations($driver, $data);

            $driver->forfeitures()->delete();
            $this->syncForfeitures($driver, $data);

            $driver->employment_records()->delete();
            $this->syncEmploymentRecords($driver, $data);

            return $driver->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function driverAttributes(array $data): array
    {
        return [
            'first_name' => $data['first_name'] ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'suffix' => $data['suffix'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'ssn' => $this->stripSsn($data['ssn'] ?? ''),
            'main_phone' => $data['main_phone'] ?? null,
            'alt_phone' => $data['alt_phone'] ?? null,
            'email' => $data['email'] ?? null,
            'business_name' => $data['business_name'] ?? null,
            'employer_identification_number' => $data['employer_identification_number'] ?? null,
            'federal_tax_classification' => $data['federal_tax_classification'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state_code' => $this->stateCode($data['state'] ?? null),
            'country_code' => $this->countryCode($data['country'] ?? null) ?? 'US',
            'postal_code' => $data['postal_code'] ?? null,
            'twic_card' => $this->yes($data['twic_card'] ?? false),
            'passport' => $this->yes($data['passport'] ?? false),
        ];
    }

    /**
     * Forms post a country id; the API and older forms post the ISO code or the name.
     */
    public function countryCode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Country::find($value)?->iso_code;
        }

        $value = trim((string) $value);

        return strlen($value) === 2 ? strtoupper($value) : Country::where('name', $value)->value('iso_code');
    }

    /**
     * Forms post a state id; the API and older forms post the code or the name.
     */
    public function stateCode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return State::find($value)?->code;
        }

        $value = trim((string) $value);

        return strlen($value) === 2 ? strtoupper($value) : State::where('name', $value)->value('code');
    }

    private function yes(mixed $value): bool
    {
        return $value === 'yes' || filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function stripSsn(string $ssn): string
    {
        return preg_replace('/[^0-9]/', '', $ssn) ?? '';
    }

    /**
     * The posted licence becomes (or updates) the driver's current one.
     */
    private function upsertLicense(Driver $driver, array $data): void
    {
        $payload = [
            'first_name' => $data['license_first_name'] ?? null,
            'last_name' => $data['license_last_name'] ?? null,
            'issued' => $data['license_issued'] ?? null,
            'expires' => $data['license_expires'] ?? null,
            'country_code' => $this->countryCode($data['license_country'] ?? null),
            'state_code' => $this->stateCode($data['license_state'] ?? null),
            'class' => $data['license_class'] ?? null,
            'license_number' => $data['license_number'] ?? null,
            'is_h_placarded_hazmat' => $this->yes($data['is_h_placarded_hazmat'] ?? false),
            'is_n_tank_vehicle' => $this->yes($data['is_n_tank_vehicle'] ?? false),
            'is_p_passengers' => $this->yes($data['is_p_passengers'] ?? false),
            'is_t_double_trailer' => $this->yes($data['is_t_double_trailer'] ?? false),
            'is_s_school_bus' => $this->yes($data['is_s_school_bus'] ?? false),
            'is_x_placarded_hazmat' => $this->yes($data['is_x_placarded_hazmat'] ?? false),
        ];

        $current = $driver->currentLicense()->first();

        if ($current) {
            $current->update($payload);
        } else {
            $driver->licenses()->create($payload + ['is_current' => true]);
        }
    }

    private function syncResidences(Driver $driver, array $data): void
    {
        $rows = [];

        foreach ((array) ($data['residence_address'] ?? []) as $index => $address) {
            if (empty(trim($address ?? ''))) {
                continue;
            }

            $rows[] = [
                'driver_id' => $driver->id,
                'address' => $address,
                'city' => $data['residence_city'][$index] ?? null,
                'state_code' => $this->stateCode($data['residence_state'][$index] ?? null),
                'country_code' => $this->countryCode($data['residence_country'][$index] ?? null) ?? 'US',
                'zip' => $data['residence_postal_code'][$index] ?? null,
                'is_current' => $index === 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('residence_addresses')->insert($rows);
        }
    }

    private function syncExperiences(Driver $driver, array $data): void
    {
        $rows = [];

        foreach ((array) ($data['equipment_class'] ?? []) as $index => $equipmentClass) {
            if (($data['experience'][$index] ?? 'no') !== 'yes') {
                continue;
            }

            $rows[] = [
                'driver_id' => $driver->id,
                'equipment_class' => $equipmentClass,
                'experience' => true,
                'from_date' => $data['experience_from_date'][$index] ?? null,
                'to_date' => $data['experience_to_date'][$index] ?? null,
                'approx_miles' => $data['approx_miles'][$index] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('experiences')->insert($rows);
        }
    }

    /**
     * Rows exist only for real accidents; the yes/no answer is on driver_documents (F19c).
     */
    private function syncAccidents(Driver $driver, array $data): void
    {
        $rows = [];

        if (($data['accident'] ?? null) === 'yes') {
            foreach ((array) ($data['accident_date'] ?? []) as $index => $date) {
                if (empty($date)) {
                    continue;
                }

                $rows[] = [
                    'driver_id' => $driver->id,
                    'accident_date' => $date,
                    'accident_location' => $data['accident_location'][$index] ?? null,
                    'number_of_injuries' => (int) ($data['number_of_injuries'][$index] ?? 0),
                    'number_of_fatalities' => (int) ($data['number_of_fatalities'][$index] ?? 0),
                    'hazmat_spill' => $this->yes($data['hazmat_spill'][$index] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows !== []) {
            DB::table('accidents')->insert($rows);
        }

        DriverDocument::updateOrCreate(['driver_id' => $driver->id], ['has_accidents' => $rows !== []]);
    }

    /**
     * Rows exist only for real violations; the yes/no answer is on driver_documents (F19c).
     */
    private function syncViolations(Driver $driver, array $data): void
    {
        $rows = [];

        if (($data['violation'] ?? null) === 'yes') {
            foreach ((array) ($data['violation_date'] ?? []) as $index => $date) {
                if (empty($date)) {
                    continue;
                }

                $rows[] = [
                    'driver_id' => $driver->id,
                    'violation_date' => $date,
                    'violation_location' => $data['violation_location'][$index] ?? null,
                    'offense' => $data['offense'][$index] ?? null,
                    'vehicle_type' => $data['vehicle_type'][$index] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows !== []) {
            DB::table('violations')->insert($rows);
        }

        DriverDocument::updateOrCreate(['driver_id' => $driver->id], ['has_violations' => $rows !== []]);
    }

    private function syncForfeitures(Driver $driver, array $data): void
    {
        DB::table('forfeitures')->insert([
            'driver_id' => $driver->id,
            'denied_license' => $this->yes($data['denied_license'] ?? false),
            'license_revoked' => $this->yes($data['license_revoked'] ?? false),
            'forfeitures' => $data['forfeitures'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncEmploymentRecords(Driver $driver, array $data): void
    {
        $rows = [];

        foreach ((array) ($data['employer_name'] ?? []) as $index => $employerName) {
            if (empty(trim($employerName ?? ''))) {
                continue;
            }

            $rows[] = [
                'driver_id' => $driver->id,
                'employer_name' => $employerName,
                'employer_record_address' => $data['employer_record_address'][$index] ?? null,
                'employer_record_city' => $data['employer_record_city'][$index] ?? null,
                'employer_record_country_code' => $this->countryCode($data['employer_record_country'][$index] ?? null),
                'employer_record_state_code' => $this->stateCode($data['employer_record_state'][$index] ?? null),
                'employer_record_postal_code' => $data['employer_record_postal_code'][$index] ?? null,
                'employer_record_phone' => $data['employer_record_phone'][$index] ?? null,
                'employer_record_fax' => $data['employer_record_fax'][$index] ?? null,
                'employer_record_email' => $data['employer_record_email'][$index] ?? null,
                'employer_record_position' => $data['employer_record_position'][$index] ?? null,
                'employer_record_date_from' => $data['employer_record_date_from'][$index] ?? null,
                'employer_record_date_to' => $data['employer_record_date_to'][$index] ?? null,
                'employer_record_reason_for_leaving' => $data['employer_record_reason_for_leaving'][$index] ?? null,
                'employed_regulations' => $this->yes($data['employed_regulations'][$index] ?? false),
                'safety_sensitive_function' => $this->yes($data['safety_sensitive_function'][$index] ?? false),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('employment_records')->insert($rows);
        }
    }
}
