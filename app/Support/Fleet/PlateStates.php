<?php

namespace App\Support\Fleet;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * States and provinces that issue plates: US and Canada only (PO-11). The states table also
 * holds other countries, so a bare exists:states,code is not enough.
 */
final class PlateStates
{
    public const COUNTRIES = ['US' => 'United States', 'CA' => 'Canada'];

    /**
     * A state or province of $country when given (an address), else of either country (a plate).
     */
    public static function rule(?string $country = null): Exists
    {
        $countries = $country !== null && isset(self::COUNTRIES[$country]) ? [$country] : array_keys(self::COUNTRIES);

        return Rule::exists('states', 'code')->where(fn (Builder $query) => $query->whereIn(
            'country_id', Country::query()->whereIn('iso_code', $countries)->select('id'),
        ));
    }

    /**
     * Codes by lower-case name per country ("texas" → "TX"), for geocoder results.
     *
     * @return array<string, array<string, string>>
     */
    public static function codesByName(): array
    {
        return self::states()
            ->groupBy('iso_code')
            ->map(fn ($states) => $states->mapWithKeys(fn (State $state) => [mb_strtolower((string) $state->name) => $state->code])->all())
            ->all();
    }

    /**
     * Codes keyed by country name, for an optgroup select.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        $states = self::states();

        return collect(self::COUNTRIES)
            ->mapWithKeys(fn (string $name, string $iso) => [$name => $states->where('iso_code', $iso)->pluck('name', 'code')->all()])
            ->filter()
            ->all();
    }

    /**
     * @return Collection<int, State>
     */
    private static function states(): Collection
    {
        return State::query()
            ->join('countries', 'countries.id', '=', 'states.country_id')
            ->whereIn('countries.iso_code', array_keys(self::COUNTRIES))
            ->orderBy('states.name')
            ->get(['states.code', 'states.name', 'countries.iso_code'])
            ->filter(fn (State $state) => strlen((string) $state->code) === 2); // only codes the rule accepts
    }
}
