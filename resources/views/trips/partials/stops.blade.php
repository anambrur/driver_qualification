{{-- The Stops tab (req 6.1, 6.3): the route on a map, then the form while the trip can change. --}}
@php
    $markers = $trip->stops->filter(fn ($stop) => $stop->latitude !== null && $stop->longitude !== null)
        ->map(fn ($stop) => ['lat' => $stop->latitude, 'lng' => $stop->longitude, 'label' => \App\Models\TripStop::TYPES[$stop->stop_type].': '.$stop->address_line.', '.$stop->cityState()])
        ->values();
@endphp
<div class="space-y-6">
    @if ($trip->stops->isEmpty())
        <x-ui.empty-state icon="map-pin" title="No stops yet" :description="$canEdit ? 'Add the start and the drop-off below.' : 'This trip has no stops.'" />
    @else
        <x-ui.card title="Route">
            @if ($markers->isNotEmpty())
                @once
                    @push('styles')
                        @vite('resources/js/trip-map.js')
                    @endpush
                @endonce
                <div data-trip-map data-stops="{{ $markers->toJson() }}" data-tile-url="{{ config('maps.tiles.url') }}" data-attribution="{{ config('maps.tiles.attribution') }}"
                    class="mb-2 h-72 w-full overflow-hidden rounded-md border border-border sm:h-80" role="img" aria-label="Map of the trip's stops"></div>
                <p class="mb-4 text-xs text-muted-foreground">Map data {!! config('maps.tiles.attribution') !!}</p>
            @endif
            <ol class="divide-y divide-border">
                @foreach ($trip->stops as $stop)
                    <li class="flex flex-wrap items-start gap-x-3 gap-y-1 py-3">
                        <span class="badge" data-tone="neutral">{{ $loop->iteration }}</span>
                        <div class="min-w-0">
                            <p class="font-medium text-foreground">
                                {{ \App\Models\TripStop::TYPES[$stop->stop_type] }}{{ $stop->also_pickup ? ' and pickup' : '' }}
                                @if ($stop->shipper)
                                    <span class="font-normal text-muted-foreground">· {{ $stop->shipper->name }}</span>
                                @endif
                            </p>
                            <p class="text-sm text-muted-foreground">{{ $stop->address_line }}, {{ $stop->cityState() }} {{ $stop->zip }}</p>
                            @if ($stop->arrived_at || $stop->departed_at)
                                <p class="text-xs text-muted-foreground">
                                    {{ collect(['Arrived' => $stop->arrived_at, 'Departed' => $stop->departed_at])->filter()->map(fn ($at, $label) => $label.' '.$at->timezone($timezone)->format('M d, g:i A'))->implode(' · ') }}
                                </p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif

    @if ($trip->status === 'in_progress' && auth()->user()->can('trips.edit'))
        @include('trips.partials.stop-times')
    @endif

    @if ($canEdit)
        @php
            // Old input counts only when this form was the one sent back (the page has three forms)
            $resent = old('_form') === 'stops';
            $hasPickup = $resent ? (bool) old('has_pickup') : $trip->stop('pickup') !== null;
            $alsoPickup = $resent ? (bool) old('stops.start.also_pickup') : (bool) $trip->stop('start')?->also_pickup;
        @endphp
        <form method="POST" action="{{ route('trips.stops.update', $trip) }}" class="space-y-6" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="stops">

            <x-ui.card title="Start" description="Where the trip starts.">
                @include('trips.partials.stop-fields', ['type' => 'start', 'stop' => $trip->stop('start'), 'shipperOptions' => $shippers])
                <div class="mt-4">
                    @include('trips.partials.stop-check', ['name' => 'stops[start][also_pickup]', 'label' => 'The load is picked up at the start', 'description' => 'Then no separate pickup is needed.', 'checked' => $alsoPickup])
                </div>
            </x-ui.card>

            <x-ui.card title="Pickup" description="Optional: a pickup between the start and the drop-off.">
                @include('trips.partials.stop-check', ['name' => 'has_pickup', 'label' => 'Add a separate pickup', 'description' => null, 'checked' => $hasPickup, 'toggle' => true])
                {{-- Hidden by the script only: without JavaScript the fields stay usable --}}
                <div class="mt-4" data-pickup-fields>
                    @include('trips.partials.stop-fields', ['type' => 'pickup', 'stop' => $trip->stop('pickup'), 'shipperOptions' => $shippers])
                </div>
            </x-ui.card>

            <x-ui.card title="Drop-off" description="Where the load is delivered.">
                @include('trips.partials.stop-fields', ['type' => 'drop_off', 'stop' => $trip->stop('drop_off'), 'shipperOptions' => $shippers])
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button type="submit" icon="check">Save stops</x-ui.button>
            </div>
        </form>

        @push('scripts')
            <script>
                (() => {
                    const toggle = document.querySelector('[data-pickup-toggle]');
                    const fields = document.querySelector('[data-pickup-fields]');
                    const sync = () => { fields.hidden = !toggle.checked; };
                    toggle?.addEventListener('change', sync);
                    if (toggle) sync();
                })();
            </script>
        @endpush
    @endif
</div>
