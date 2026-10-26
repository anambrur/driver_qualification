{{--
    The Assignment card (req 4.5, 4.6): driver, vehicle and trailer with the equipment check and
    the progress times. Pickers while the trip is planned or assigned and the user has trips.assign.
    Needs $trip, $canAssign, $assignOptions, $groupDefaults, $warnings and $timezone (TripController::show).
--}}
@php
    $driverName = $trip->driver ? trim($trip->driver->first_name.' '.$trip->driver->last_name) : null;
@endphp
<x-ui.card title="Assignment" :description="$canAssign ? 'Leave the vehicle empty to use the driver\'s asset group.' : null">
    @if ($warnings !== [])
        <x-ui.alert tone="warning" title="Check before the trip starts" class="mb-4">
            <ul class="list-disc space-y-1 ps-5">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @if ($canAssign)
        <form method="POST" action="{{ route('trips.assignment.update', $trip) }}" id="trip-assignment-form" class="grid gap-4 sm:grid-cols-3" data-group-defaults="{{ json_encode((object) $groupDefaults) }}">
            @csrf
            @method('PUT')
            @foreach (['driver_id' => ['Driver', $assignOptions['drivers'], $trip->driver_id, 'Select driver'], 'vehicle_id' => ['Vehicle', $assignOptions['vehicles'], $trip->vehicle_id, 'From the asset group'], 'trailer_id' => ['Trailer', $assignOptions['trailers'], $trip->trailer_id, 'No trailer']] as $field => [$label, $options, $current, $placeholder])
                <x-ui.field :label="$label" :name="$field" :required="$field === 'driver_id'">
                    <x-ui.input type="search" :aria-label="'Filter '.strtolower($label).'s'" placeholder="Filter…" class="mb-2" data-select-filter="{{ $field }}" hidden />
                    <x-ui.select :name="$field" :options="$options" :value="$current" :placeholder="$placeholder" :required="$field === 'driver_id'" />
                </x-ui.field>
            @endforeach
            <div class="flex flex-wrap justify-end gap-2 sm:col-span-3">
                <x-ui.button type="submit" icon="user-round-check">{{ $trip->status === 'assigned' ? 'Save assignment' : 'Assign' }}</x-ui.button>
            </div>
        </form>
        @if ($trip->status === 'assigned')
            <form method="POST" action="{{ route('trips.assignment.destroy', $trip) }}" class="mt-2 flex justify-end"
                data-confirm="Unassign trip {{ $trip->label() }}?" data-confirm-text="The driver, vehicle and trailer are removed and the trip is planned again." data-confirm-button="Unassign">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="outline" icon="user-round-x">Unassign</x-ui.button>
            </form>
        @endif
    @else
        <dl class="grid gap-4 sm:grid-cols-3">
            <x-ui.detail label="Driver" :value="$driverName" />
            <x-ui.detail label="Vehicle" :value="$trip->vehicle_unit_no_snapshot ?? $trip->vehicle?->unit_no" />
            <x-ui.detail label="Trailer" :value="$trip->trailer_unit_no_snapshot ?? $trip->trailer?->unit_no" />
        </dl>
    @endif

    @if ($trip->driver_id !== null)
        <dl class="mt-4 grid gap-4 border-t border-border pt-4 sm:grid-cols-3">
            <x-ui.detail label="Equipment" :value="$trip->equipment_confirmed_at
                ? 'Confirmed '.$trip->equipment_confirmed_at->timezone($timezone)->format('M d, Y g:i A').($trip->equipmentConfirmedBy ? ' by '.$trip->equipmentConfirmedBy->name : '')
                : 'Not confirmed'" />
            <x-ui.detail label="Started" :value="$trip->started_at?->timezone($timezone)->format('M d, Y g:i A')" />
            <x-ui.detail label="Completed" :value="$trip->completed_at?->timezone($timezone)->format('M d, Y g:i A')" />
            @if ($trip->status === 'completed')
                <x-ui.detail label="Actual miles" :value="$trip->actual_distance_miles !== null ? number_format((float) $trip->actual_distance_miles, 1) : null" />
            @endif
        </dl>
    @endif
</x-ui.card>

@if ($canAssign)
    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('trip-assignment-form');
                if (!form) return;

                // A filter box above each picker hides the options that don't match
                form.querySelectorAll('[data-select-filter]').forEach((input) => {
                    const select = form.elements[input.dataset.selectFilter];
                    input.hidden = false;
                    input.addEventListener('input', () => {
                        const term = input.value.trim().toLowerCase();
                        [...select.options].forEach((option) => {
                            option.hidden = option.value !== '' && term !== '' && !option.text.toLowerCase().includes(term);
                        });
                    });
                });

                // Picking a driver fills the vehicle and trailer of the driver's asset group
                const defaults = JSON.parse(form.dataset.groupDefaults);
                form.elements.driver_id.addEventListener('change', (event) => {
                    const group = defaults[event.target.value];
                    if (!group) return;
                    form.elements.vehicle_id.value = group.vehicle_id ?? '';
                    form.elements.trailer_id.value = group.trailer_id ?? '';
                });
            })();
        </script>
    @endpush
@endif
