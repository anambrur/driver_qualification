{{-- The Details tab when the trip can't be edited (or the user may not edit it). --}}
<div class="max-w-4xl space-y-6">
    <x-ui.card title="Trip">
        <dl class="grid gap-4 sm:grid-cols-3">
            <x-ui.detail label="Trip number" :value="$trip->label()" />
            <x-ui.detail label="Trip date" :value="$trip->trip_date->format('M d, Y')" />
            <x-ui.detail label="Status" :value="\App\Support\Ui\Status::label($trip->status)" />
            <x-ui.detail label="Driver" :value="$trip->driver ? trim($trip->driver->first_name.' '.$trip->driver->last_name) : null" />
            <x-ui.detail label="Vehicle" :value="$trip->vehicle?->unit_no" />
            <x-ui.detail label="Trailer" :value="$trip->trailer?->unit_no" />
            @if ($trip->status === 'cancelled')
                <x-ui.detail label="Cancelled" :value="$trip->cancelled_at?->format('M d, Y')" />
                <x-ui.detail label="Reason" :value="$trip->cancel_reason" class="sm:col-span-2" />
            @endif
        </dl>
    </x-ui.card>
    <x-ui.card title="Load">
        <dl class="grid gap-4 sm:grid-cols-3">
            <x-ui.detail label="Shipper" :value="$trip->shipper ? $trip->shipper->name.($trip->shipper->trashed() ? ' (deleted)' : '') : null" />
            <x-ui.detail label="Product" :value="$trip->product" />
            <x-ui.detail label="Weight" :value="$trip->weight !== null ? number_format((float) $trip->weight, 2).' '.$trip->weight_unit : null" />
            <x-ui.detail label="Planned miles" :value="$trip->planned_distance_miles !== null ? number_format((float) $trip->planned_distance_miles, 1) : null" />
            <x-ui.detail label="Notes" :value="$trip->notes" class="sm:col-span-3" />
        </dl>
    </x-ui.card>
</div>
