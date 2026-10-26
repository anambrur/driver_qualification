{{--
    The 10 newest trips of a driver or vehicle, for its page. Needs $recentTrips, $allUrl (the list
    filtered by it) and $crew ('driver' on a vehicle page, 'vehicle' on a driver page).
--}}
<x-ui.card title="Recent trips" class="mt-6">
    <x-slot:action>
        <x-ui.button :href="$allUrl" variant="outline" size="sm" icon-end="arrow-right">All trips</x-ui.button>
    </x-slot:action>
    @if ($recentTrips->isEmpty())
        <x-ui.empty-state icon="route" title="No trips yet" class="border-0 p-6!" />
    @else
        <x-ui.table>
            <thead>
                <tr><th>Trip</th><th>Date</th><th>Status</th><th>{{ $crew === 'driver' ? 'Driver' : 'Vehicle' }}</th></tr>
            </thead>
            <tbody>
                @foreach ($recentTrips as $recentTrip)
                    <tr>
                        <td data-label="Trip" data-primary><a href="{{ route('trips.show', $recentTrip) }}" class="font-medium text-foreground hover:underline">{{ $recentTrip->label() }}</a></td>
                        <td data-label="Date">{{ $recentTrip->trip_date->format('M d, Y') }}</td>
                        <td data-label="Status">@include('partials.ui.status-badge', ['status' => $recentTrip->status])</td>
                        <td data-label="{{ $crew === 'driver' ? 'Driver' : 'Vehicle' }}">
                            @if ($crew === 'driver')
                                {{ $recentTrip->driver ? trim($recentTrip->driver->first_name.' '.$recentTrip->driver->last_name) : '—' }}
                            @else
                                {{ $recentTrip->vehicle_unit_no_snapshot ?? $recentTrip->vehicle->unit_no ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    @endif
</x-ui.card>
