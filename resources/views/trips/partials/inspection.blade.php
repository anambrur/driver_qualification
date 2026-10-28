{{--
    The Inspection tab (req 5.1–5.3): the latest pre-trip inspection with its progress, result and
    failed items, and the earlier ones. Needs $trip and $inspections (TripController::show, newest
    first, the latest with its failed results loaded).
--}}
@php
    $latest = $inspections->first();
    $canStart = $trip->status === 'assigned' && auth()->user()->can('inspections.manage');
    $open = $latest?->isOpen() ? $latest : null;
@endphp
@error('inspection')
    <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
@enderror

@if ($latest === null)
    <x-ui.empty-state icon="clipboard-check" title="No pre-trip inspection yet"
        :description="$trip->status === 'assigned' ? 'Inspect the tractor and trailer with the driver before the trip starts.' : 'An inspection starts once the trip is assigned.'">
        @if ($canStart)
            <form method="POST" action="{{ route('trips.inspections.store', $trip) }}">
                @csrf
                <x-ui.button type="submit" icon="clipboard-check">Start inspection</x-ui.button>
            </form>
        @endif
    </x-ui.empty-state>
@else
    <x-ui.card :title="'Inspection of '.$latest->started_at?->format('M d, Y g:i A')" :description="$latest->template->label().($latest->performedBy ? ' · by '.$latest->performedBy->name : '')">
        <x-slot:action>
            @include('partials.ui.status-badge', ['status' => $latest->status])
            @if ($latest->result)
                <span class="badge" data-tone="{{ $latest->result === 'no_defects' ? 'success' : 'danger' }}">{{ \App\Models\Inspection::RESULTS[$latest->result] }}</span>
            @endif
        </x-slot:action>
        <div class="space-y-4 pb-6">
            @include('inspections.partials.progress', ['answered' => (int) $latest->results_count, 'total' => $latest->itemsCount()])

            @if ($latest->results->isNotEmpty())
                <div>
                    <h3 class="mb-2 text-sm font-semibold">Failed items</h3>
                    <ul class="space-y-1 text-sm">
                        @foreach ($latest->results as $result)
                            <li class="flex gap-2">
                                {{ svg('lucide-circle-x', 'mt-0.5 size-4 shrink-0 text-destructive', ['aria-hidden' => 'true']) }}
                                <span><span class="font-medium">{{ $result->item_label }}</span>@if ($result->note)<span class="text-muted-foreground"> · {{ $result->note }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-muted-foreground">Attachments</dt><dd class="tabular-nums">{{ $latest->documents_count }}</dd></div>
                <div><dt class="text-muted-foreground">Odometer</dt><dd class="tabular-nums">{{ $latest->odometer !== null ? number_format($latest->odometer) : '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Completed</dt><dd>{{ $latest->completed_at?->format('M d, Y g:i A') ?? '—' }}</dd></div>
            </dl>
        </div>
        <x-slot:footer class="justify-end">
            <x-ui.button variant="outline" icon="eye" :href="route('inspections.show', $latest)">Open inspection</x-ui.button>
            @if ($canStart && $open)
                <x-ui.button icon="clipboard-pen" :href="route('inspections.show', $latest)">Continue</x-ui.button>
            @elseif ($canStart)
                <form method="POST" action="{{ route('trips.inspections.store', $trip) }}">
                    @csrf
                    <x-ui.button type="submit" icon="clipboard-check">New inspection</x-ui.button>
                </form>
            @endif
        </x-slot:footer>
    </x-ui.card>

    @if ($inspections->count() > 1)
        <x-ui.card title="Earlier inspections" class="mt-6" flush>
            <x-ui.table>
                <thead>
                    <tr><th>Started</th><th>Status</th><th>Result</th><th class="text-right"><span class="sr-only">Open</span></th></tr>
                </thead>
                <tbody>
                    @foreach ($inspections->skip(1) as $inspection)
                        <tr>
                            <td data-label="Started" data-primary>{{ $inspection->started_at?->format('M d, Y g:i A') }}</td>
                            <td data-label="Status">@include('partials.ui.status-badge', ['status' => $inspection->status])</td>
                            <td data-label="Result">{{ $inspection->result ? \App\Models\Inspection::RESULTS[$inspection->result] : '—' }}</td>
                            <td class="text-right"><x-ui.button variant="ghost" size="sm" :href="route('inspections.show', $inspection)">Open</x-ui.button></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif
@endif
