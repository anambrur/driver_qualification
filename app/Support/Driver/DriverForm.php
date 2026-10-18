<?php

namespace App\Support\Driver;

use App\Models\Country;
use App\Models\Driver;
use App\Models\State;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Values and options for the admin driver create/edit form. Countries and states post their
 * codes (DriverCrudService accepts ids, codes or names). Repeating groups re-render from old
 * input after a failed submit, otherwise from the driver's saved rows.
 */
final class DriverForm
{
    public const EQUIPMENT_CLASSES = ['Straight Truck', 'Truck-Tractor', 'Semi-Trailers', 'Double/Triples', 'Flatbed', 'Bus', 'Other'];

    public const LICENSE_CLASSES = ['Class A (CDL-A)', 'Class B (CDL-B)', 'Class C (CDL-C)', 'Class D', 'Class C (Non-Commercial)', 'Class M', 'Class E', 'Class F', 'Class DJ/MJ'];

    public const TAX_CLASSIFICATIONS = [
        'individual_sole_proprietor' => 'Individual/Sole Proprietor',
        'c_corporation' => 'C Corporation',
        's_corporation' => 'S Corporation',
        'llc' => 'LLC',
    ];

    public const ENDORSEMENTS = [
        'is_h_placarded_hazmat' => 'H – Placarded hazmat',
        'is_n_tank_vehicle' => 'N – Tank vehicles',
        'is_p_passengers' => 'P – Passengers',
        'is_t_double_trailer' => 'T – Double/triple trailers',
        'is_s_school_bus' => 'S – School bus',
        'is_x_placarded_hazmat' => 'X – Placarded hazmat & tank vehicles',
    ];

    /** Input name of each column, per repeating group. */
    public const GROUPS = [
        'residences' => [
            'address' => 'residence_address', 'city' => 'residence_city', 'country' => 'residence_country',
            'state' => 'residence_state', 'postal_code' => 'residence_postal_code',
        ],
        'accidents' => [
            'date' => 'accident_date', 'location' => 'accident_location', 'injuries' => 'number_of_injuries',
            'fatalities' => 'number_of_fatalities', 'hazmat_spill' => 'hazmat_spill',
        ],
        'violations' => [
            'date' => 'violation_date', 'location' => 'violation_location', 'offense' => 'offense', 'vehicle_type' => 'vehicle_type',
        ],
        'employment' => [
            'employer_name' => 'employer_name', 'address' => 'employer_record_address', 'city' => 'employer_record_city',
            'country' => 'employer_record_country', 'state' => 'employer_record_state', 'postal_code' => 'employer_record_postal_code',
            'phone' => 'employer_record_phone', 'fax' => 'employer_record_fax', 'email' => 'employer_record_email',
            'position' => 'employer_record_position', 'date_from' => 'employer_record_date_from', 'date_to' => 'employer_record_date_to',
            'reason_for_leaving' => 'employer_record_reason_for_leaving',
            'employed_regulations' => 'employed_regulations', 'safety_sensitive_function' => 'safety_sensitive_function',
        ],
    ];

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, array<int, array<string, mixed>>>  $rows
     * @param  array<string, string>  $countries
     * @param  array<string, string>  $states
     */
    private function __construct(
        public readonly ?Driver $driver,
        public readonly array $values,
        public readonly array $rows,
        public readonly array $countries,
        public readonly array $states,
    ) {}

    public static function for(?Driver $driver = null): self
    {
        $license = $driver?->currentLicense;
        $forfeiture = $driver?->forfeitures->first();
        $documents = $driver?->driver_documents;

        $values = [
            'first_name' => $driver?->first_name,
            'middle_name' => $driver?->middle_name,
            'last_name' => $driver?->last_name,
            'suffix' => $driver?->suffix,
            'business_name' => $driver?->business_name,
            'employer_identification_number' => $driver?->employer_identification_number,
            'federal_tax_classification' => $driver?->federal_tax_classification,
            'date_of_birth' => self::date($driver?->date_of_birth),
            'ssn' => $driver?->ssn,
            'main_phone' => $driver?->main_phone,
            'alt_phone' => $driver?->alt_phone,
            'email' => $driver?->email,
            'address' => $driver?->address,
            'city' => $driver?->city,
            'country' => $driver->country_code ?? 'US',
            'state' => $driver?->state_code,
            'postal_code' => $driver?->postal_code,
            'twic_card' => (bool) $driver?->twic_card,
            'passport' => (bool) $driver?->passport,
            'license_first_name' => $license?->first_name,
            'license_last_name' => $license?->last_name,
            'license_issued' => self::date($license?->issued),
            'license_expires' => self::date($license?->expires),
            'license_country' => $license->country_code ?? 'US',
            'license_state' => $license?->state_code,
            'license_class' => $license?->class,
            'license_number' => $license?->license_number,
            'accident' => self::yesNo($documents->has_accidents ?? $driver?->accidents->isNotEmpty()),
            'violation' => self::yesNo($documents->has_violations ?? $driver?->violations->isNotEmpty()),
            'denied_license' => self::yesNo($forfeiture?->denied_license),
            'license_revoked' => self::yesNo($forfeiture?->license_revoked),
            'forfeitures' => $forfeiture?->forfeitures,
        ];

        foreach (array_keys(self::ENDORSEMENTS) as $endorsement) {
            $values[$endorsement] = (bool) $license?->{$endorsement};
        }

        $experiences = $driver?->experiences->keyBy('equipment_class') ?? collect();
        $values['experiences'] = array_map(fn (string $class) => [
            'experience' => self::yesNo($experiences->get($class)?->experience),
            'from' => self::date($experiences->get($class)?->from_date),
            'to' => self::date($experiences->get($class)?->to_date),
            'miles' => $experiences->get($class)?->approx_miles,
        ], self::EQUIPMENT_CLASSES);

        $rows = [
            'residences' => self::rows('residences', $driver?->residence_addresses, fn ($r) => [
                'address' => $r->address, 'city' => $r->city, 'country' => $r->country_code, 'state' => $r->state_code, 'postal_code' => $r->zip,
            ]),
            'accidents' => self::rows('accidents', $driver?->accidents, fn ($a) => [
                'date' => self::date($a->accident_date), 'location' => $a->accident_location, 'injuries' => $a->number_of_injuries,
                'fatalities' => $a->number_of_fatalities, 'hazmat_spill' => self::yesNo($a->hazmat_spill),
            ]),
            'violations' => self::rows('violations', $driver?->violations, fn ($v) => [
                'date' => self::date($v->violation_date), 'location' => $v->violation_location, 'offense' => $v->offense, 'vehicle_type' => $v->vehicle_type,
            ]),
            'employment' => self::rows('employment', $driver?->employment_records, fn ($e) => [
                'employer_name' => $e->employer_name, 'address' => $e->employer_record_address, 'city' => $e->employer_record_city,
                'country' => $e->employer_record_country_code, 'state' => $e->employer_record_state_code,
                'postal_code' => $e->employer_record_postal_code, 'phone' => $e->employer_record_phone, 'fax' => $e->employer_record_fax,
                'email' => $e->employer_record_email, 'position' => $e->employer_record_position,
                'date_from' => self::date($e->employer_record_date_from), 'date_to' => self::date($e->employer_record_date_to),
                'reason_for_leaving' => $e->employer_record_reason_for_leaving,
                'employed_regulations' => self::yesNo($e->employed_regulations), 'safety_sensitive_function' => self::yesNo($e->safety_sensitive_function),
            ]),
        ];

        return new self(
            $driver,
            $values,
            $rows,
            Country::orderBy('name')->pluck('name', 'iso_code')->all(),
            State::whereHas('country', fn ($q) => $q->where('iso_code', 'US'))->orderBy('name')->pluck('name', 'code')->all(),
        );
    }

    /**
     * A blank row for the add-row template.
     *
     * @return array<string, mixed>
     */
    public static function blank(string $group): array
    {
        return array_merge(array_fill_keys(array_keys(self::GROUPS[$group]), null), match ($group) {
            'residences' => ['country' => 'US'],
            'accidents' => ['hazmat_spill' => 'no'],
            'employment' => ['country' => 'US', 'employed_regulations' => 'no', 'safety_sensitive_function' => 'no'],
            default => [],
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>|null  $models
     * @param  callable(TModel): array<string, mixed>  $map
     * @return array<int, array<string, mixed>>
     */
    private static function rows(string $group, ?Collection $models, callable $map): array
    {
        $columns = self::GROUPS[$group];
        $old = session()->getOldInput();

        if ($old !== []) {
            $indexes = collect($columns)->flatMap(fn (string $input) => array_keys((array) ($old[$input] ?? [])))->unique()->sort()->values();

            // Keyed by the posted index so each row's errors stay on it.
            $rows = $indexes->mapWithKeys(fn ($i) => [$i => collect($columns)->map(fn (string $input) => $old[$input][$i] ?? null)->all()])->all();
        } else {
            $rows = $models?->map($map)->values()->all() ?? [];
        }

        return $rows !== [] ? $rows : [self::blank($group)];
    }

    private static function date(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : ($value ?: null);
    }

    private static function yesNo(mixed $value): string
    {
        return $value === 'yes' || $value === true || $value === 1 || $value === '1' ? 'yes' : 'no';
    }
}
