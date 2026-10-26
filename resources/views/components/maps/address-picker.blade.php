{{--
    Address fields with a search and a Leaflet map (req 6.1), used for shippers and trip stops.
        <x-maps.address-picker :value="$shipper" :names="['address_line1' => 'stops[0][address_line]', 'address_line2' => null]" />
    `names` maps each part to its input name; a null name leaves the field out. Without JavaScript
    or with search off (MAPS_GEOCODER=none) the plain fields still work. resources/js/address-picker.js
    shows the search and the map; a result fills the fields and place_source/place_ref, a pin moved
    by hand makes place_source "manual".
--}}
@props(['value' => null, 'names' => []])
@php
    $names = array_replace([
        'address_line1' => 'address_line1',
        'address_line2' => 'address_line2',
        'city' => 'city',
        'state_code' => 'state_code',
        'zip' => 'zip',
        'country_code' => 'country_code',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'place_source' => 'place_source',
        'place_ref' => 'place_ref',
    ], $names);
    $current = fn (string $part) => old(\App\Support\Ui\Field::errorKey($names[$part]), data_get($value, $part));
    $state = (string) $current('state_code');
    $searchOn = app(\App\Services\Maps\Geocoder::class)->enabled();
    // One query per page, however many pickers (trip stops)
    $stateOptions = once(fn () => \App\Support\Fleet\PlateStates::options());
@endphp
@once
    @push('styles')
        @vite('resources/js/address-picker.js')
    @endpush
@endonce
<div data-address-picker
    data-search-url="{{ $searchOn ? route('maps.search') : '' }}"
    data-reverse-url="{{ $searchOn ? route('maps.reverse') : '' }}"
    data-tile-url="{{ config('maps.tiles.url') }}"
    data-attribution="{{ config('maps.tiles.attribution') }}"
    {{ $attributes->class('space-y-4') }}>

    @if ($searchOn)
        <div data-picker-search hidden class="space-y-2">
            <div class="flex gap-2">
                <input type="search" data-picker-query class="input w-full" placeholder="Company, street, city or ZIP" aria-label="Search for an address" maxlength="200" autocomplete="off">
                <x-ui.button variant="outline" icon="search" data-picker-find>Search</x-ui.button>
            </div>
            <p data-picker-message class="text-sm text-muted-foreground" role="status" aria-live="polite"></p>
            <ul data-picker-results class="divide-y divide-border rounded-md border border-border" hidden role="list"></ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.field label="Address" :name="$names['address_line1']" required class="sm:col-span-2">
            <x-ui.input :name="$names['address_line1']" :value="$current('address_line1')" required maxlength="255" autocomplete="off" data-picker-field="address_line1" />
        </x-ui.field>
        @if ($names['address_line2'])
            <x-ui.field label="Address line 2" :name="$names['address_line2']" class="sm:col-span-2">
                <x-ui.input :name="$names['address_line2']" :value="$current('address_line2')" maxlength="255" autocomplete="off" />
            </x-ui.field>
        @endif
        <x-ui.field label="City" :name="$names['city']" required>
            <x-ui.input :name="$names['city']" :value="$current('city')" required maxlength="100" autocomplete="off" data-picker-field="city" />
        </x-ui.field>
        <x-ui.field label="State or province" :name="$names['state_code']" required>
            <x-ui.select :name="$names['state_code']" placeholder="Select" required data-picker-field="state_code">
                @foreach ($stateOptions as $country => $states)
                    <optgroup label="{{ $country }}">
                        @foreach ($states as $code => $stateName)
                            <option value="{{ $code }}" @selected($state === (string) $code)>{{ $stateName }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field label="ZIP or postal code" :name="$names['zip']" required>
            <x-ui.input :name="$names['zip']" :value="$current('zip')" required maxlength="10" autocomplete="off" data-picker-field="zip" />
        </x-ui.field>
        <x-ui.field label="Country" :name="$names['country_code']" required>
            <x-ui.select :name="$names['country_code']" :value="$current('country_code') ?? 'US'" :options="\App\Support\Fleet\PlateStates::COUNTRIES" required data-picker-field="country_code" />
        </x-ui.field>
    </div>

    <div data-picker-map-wrap hidden class="space-y-2">
        <div data-picker-map class="h-72 w-full overflow-hidden rounded-md border border-border sm:h-80" role="application" aria-label="Map. Drag the pin or click to set the location."></div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-muted-foreground">Map data {!! config('maps.tiles.attribution') !!}</p>
            @if ($searchOn)
                <x-ui.button variant="ghost" size="sm" icon="map-pin" data-picker-reverse>Address from pin</x-ui.button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.field label="Latitude" :name="$names['latitude']" hint="Set by the pin. Optional.">
            <x-ui.input type="number" step="any" min="-90" max="90" :name="$names['latitude']" :value="$current('latitude')" data-picker-field="latitude" />
        </x-ui.field>
        <x-ui.field label="Longitude" :name="$names['longitude']">
            <x-ui.input type="number" step="any" min="-180" max="180" :name="$names['longitude']" :value="$current('longitude')" data-picker-field="longitude" />
        </x-ui.field>
    </div>
    <input type="hidden" name="{{ $names['place_source'] }}" value="{{ $current('place_source') }}" data-picker-field="place_source">
    <input type="hidden" name="{{ $names['place_ref'] }}" value="{{ $current('place_ref') }}" data-picker-field="place_ref">
</div>
