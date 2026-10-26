{{-- One stop of the stops form: an optional shipper and the address picker. Needs $type, $stop (or null) and $shipperOptions (TripController::shipperOptions). --}}
@php
    $prefix = "stops[{$type}]";
    $names = ['address_line2' => null];
    foreach (['city', 'state_code', 'zip', 'country_code', 'latitude', 'longitude', 'place_source', 'place_ref'] as $part) {
        $names[$part] = "{$prefix}[{$part}]";
    }
    $names['address_line1'] = "{$prefix}[address_line]";
    $value = $stop === null ? ['country_code' => 'US'] : ['address_line1' => $stop->address_line] + $stop->only(['city', 'state_code', 'zip', 'country_code', 'latitude', 'longitude', 'place_source', 'place_ref']);
@endphp
<div class="space-y-4">
    <x-ui.field label="Shipper" :name="$prefix.'[shipper_id]'" hint="Optional: the shipper at this stop.">
        <x-ui.select :name="$prefix.'[shipper_id]'" :options="$shipperOptions" :value="$stop?->shipper_id" placeholder="None" />
    </x-ui.field>
    <x-maps.address-picker :value="$value" :names="$names" />
</div>
