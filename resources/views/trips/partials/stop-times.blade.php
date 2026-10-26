{{-- Arrival and departure times while the trip is in progress, in the company's time zone. --}}
<form method="POST" action="{{ route('trips.stop-times.update', $trip) }}">
    @csrf
    @method('PUT')
    <x-ui.card title="Stop times" :description="'Times in '.str_replace('_', ' ', $timezone).'. Leave a time empty to clear it.'">
        @error('stops')
            <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
        @enderror
        <div class="grid gap-4">
            @foreach ($trip->stops as $stop)
                <fieldset class="grid gap-4 sm:grid-cols-3 sm:items-end">
                    <legend class="mb-2 text-sm font-medium text-foreground sm:col-span-3">{{ \App\Models\TripStop::TYPES[$stop->stop_type] }}{{ $stop->also_pickup ? ' and pickup' : '' }} · {{ $stop->cityState() }}</legend>
                    @foreach (['arrived_at' => 'Arrived', 'departed_at' => 'Departed'] as $field => $label)
                        <x-ui.field :label="$label" :name="'stops['.$stop->id.']['.$field.']'">
                            <x-ui.input type="datetime-local" :name="'stops['.$stop->id.']['.$field.']'" :value="$stop->{$field}?->timezone($timezone)->format('Y-m-d\TH:i')" />
                        </x-ui.field>
                    @endforeach
                </fieldset>
            @endforeach
        </div>
        <x-slot:footer class="justify-end">
            <x-ui.button type="submit" icon="check">Save stop times</x-ui.button>
        </x-slot:footer>
    </x-ui.card>
</form>
