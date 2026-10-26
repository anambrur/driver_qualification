{{--
    The trip's details (req 4.1–4.3), for the create page and the Details tab.
    Needs $trip, $shippers (TripController::shipperOptions) and $numberEditable (the number and date
    can still change: an unconfirmed draft, or a new trip, which gets its number on save).
--}}
<x-ui.card title="Trip" :description="$trip->exists ? null : 'The next trip number is given when you save. You can change it until it is confirmed.'">
    <div class="grid gap-4 sm:grid-cols-2">
        @if ($trip->exists)
            <x-ui.field label="Trip number" name="trip_number" :hint="$numberEditable ? 'Any unused number. Locked once confirmed.' : 'Confirmed.'">
                <x-ui.input type="number" name="trip_number" :value="$trip->trip_number" min="1" max="{{ \App\Models\Trip::MAX_NUMBER }}" step="1" :disabled="! $numberEditable" required />
            </x-ui.field>
        @endif
        <x-ui.field label="Trip date" name="trip_date" required>
            <x-ui.input type="date" name="trip_date" :value="$trip->trip_date?->toDateString()" :disabled="! $numberEditable" required />
        </x-ui.field>
    </div>
</x-ui.card>

<x-ui.card title="Load" description="One load per trip.">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.field label="Shipper" name="shipper_id" class="sm:col-span-2" :hint="$shippers === [] ? 'No shippers saved yet.' : null">
            <x-ui.select name="shipper_id" :options="$shippers" :value="$trip->shipper_id" placeholder="None" />
        </x-ui.field>
        <x-ui.field label="Product" name="product" class="sm:col-span-2" hint="What is hauled, for example Frozen food.">
            <x-ui.input name="product" :value="$trip->product" maxlength="150" />
        </x-ui.field>
        <x-ui.field label="Weight" name="weight">
            <x-ui.input type="number" name="weight" :value="$trip->weight" min="0" max="99999999.99" step="0.01" />
        </x-ui.field>
        <x-ui.field label="Weight unit" name="weight_unit" required>
            <x-ui.select name="weight_unit" :options="\App\Models\Trip::WEIGHT_UNITS" :value="$trip->weight_unit ?? 'lb'" required />
        </x-ui.field>
        <x-ui.field label="Planned miles" name="planned_distance_miles" hint="Typed in; there is no route calculation.">
            <x-ui.input type="number" name="planned_distance_miles" :value="$trip->planned_distance_miles" min="0" max="9999999.9" step="0.1" />
        </x-ui.field>
    </div>
</x-ui.card>

<x-ui.card title="Notes">
    <x-ui.field label="Notes" name="notes">
        <x-ui.textarea name="notes" rows="3" maxlength="2000" :value="$trip->notes" />
    </x-ui.field>
</x-ui.card>
