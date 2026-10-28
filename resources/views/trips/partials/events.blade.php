{{--
    The Events tab (req 7.1–7.6): the buttons that open the record dialogs, then the timeline,
    newest first; voided events stay, struck through. Needs $trip, $events (TripController::show,
    with documents and the fuel purchase loaded), $canRecordEvents and $timezone.
--}}
@php
    $tz = $timezone ?? config('app.timezone');
    $icons = ['stop' => 'circle-pause', 'police_stop' => 'siren', 'weigh_station' => 'scale', 'accident' => 'car-front', 'breakdown' => 'wrench', 'fuel' => 'fuel'];
    $yesNo = fn (?bool $value) => $value === null ? '—' : ($value ? 'Yes' : 'No');
    $eventForm = in_array(old('_form'), ['event', 'fuel', 'attach', 'void'], true);
@endphp
@error('event')
    <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
@enderror
@if ($eventForm && ! $errors->has('event') && $errors->any() && in_array(old('_form'), ['attach', 'void'], true))
    <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
@endif

@if ($canRecordEvents)
    <div class="mb-6 flex flex-wrap gap-2">
        <x-ui.button icon="circle-pause" data-dialog-open="trip-event-dialog" data-event-type="stop">Add stop</x-ui.button>
        <x-ui.button variant="outline" icon="siren" data-dialog-open="trip-event-dialog" data-event-type="police_stop">Police stop</x-ui.button>
        <x-ui.button variant="outline" icon="scale" data-dialog-open="trip-event-dialog" data-event-type="weigh_station">Weigh station</x-ui.button>
        <x-ui.button variant="outline" icon="car-front" data-dialog-open="trip-event-dialog" data-event-type="accident">Accident</x-ui.button>
        <x-ui.button variant="outline" icon="wrench" data-dialog-open="trip-event-dialog" data-event-type="breakdown">Breakdown</x-ui.button>
        <x-ui.button variant="outline" icon="fuel" data-dialog-open="trip-fuel-dialog">Fuel stop</x-ui.button>
    </div>
@endif

@if ($events->isEmpty())
    <x-ui.empty-state icon="list-checks" title="No events yet"
        :description="$canRecordEvents ? 'Record stops, police stops, weigh stations, accidents, breakdowns and fuel stops as they happen.' : 'Events are recorded while the trip is in progress, and up to '.\App\Services\Operations\TripEventService::LATE_DAYS.' days after it is completed.'" />
@else
    <ol class="space-y-4" aria-label="Events, newest first">
        @foreach ($events as $event)
            @php
                $voided = $event->isVoided();
                $purchase = $event->fuelPurchase;
                $files = $event->documents->concat($purchase?->documents ?? []);
                $canChange = $canRecordEvents && ! $voided;
            @endphp
            <li id="event-{{ $event->id }}" class="rounded-lg border border-border p-4" @if ($voided) data-voided @endif>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 gap-3">
                        <span class="mt-0.5 shrink-0 text-muted-foreground">{{ svg('lucide-'.($icons[$event->type] ?? 'circle'), 'size-5', ['aria-hidden' => 'true']) }}</span>
                        <div class="min-w-0 {{ $voided ? 'line-through text-muted-foreground' : '' }}">
                            <p class="font-medium">{{ $event->label() }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ $event->occurred_at->timezone($tz)->format('M d, Y g:i A') }}
                                @if ($event->city || $event->address)
                                    · {{ collect([$event->address, $event->city, $event->state_code])->filter()->implode(', ') }}
                                @endif
                                @if ($event->recordedBy)
                                    · by {{ $event->recordedBy->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($voided)
                            <span class="badge" data-tone="neutral">Voided</span>
                        @elseif ($purchase)
                            <span class="badge" data-tone="{{ $purchase->isConfirmed() ? 'success' : 'warning' }}">{{ $purchase->isConfirmed() ? 'Done' : 'Receipt missing' }}</span>
                        @endif
                        @if ($canChange)
                            <x-ui.button variant="ghost" size="sm" icon="paperclip" data-dialog-open="trip-event-attach-dialog"
                                data-attach-action="{{ route('trip-events.attachments.store', $event) }}" data-attach-label="{{ $event->label() }}"
                                data-attach-slots="{{ json_encode(collect(\App\Services\Operations\TripEventService::TYPE_SLOTS[$event->type])->mapWithKeys(fn ($slot) => [$slot => \App\Services\Operations\TripEventService::SLOTS[$slot][1]])) }}">Add files</x-ui.button>
                            <x-ui.button variant="ghost" size="sm" icon="ban" data-dialog-open="trip-event-void-dialog"
                                data-void-action="{{ route('trip-events.void', $event) }}" data-void-label="{{ $event->label() }}">Void</x-ui.button>
                        @endif
                    </div>
                </div>

                <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-3 {{ $voided ? 'line-through text-muted-foreground' : '' }}">
                    @if ($event->type === 'police_stop')
                        <div><dt class="text-muted-foreground">Ticket issued</dt><dd>{{ $yesNo($event->ticket_issued) }}</dd></div>
                    @elseif ($event->type === 'weigh_station')
                        <div><dt class="text-muted-foreground">Weight OK</dt><dd>{{ $yesNo($event->weight_ok) }}</dd></div>
                    @elseif ($event->type === 'accident')
                        <div><dt class="text-muted-foreground">Injuries</dt><dd class="tabular-nums">{{ $event->injuries_count }}</dd></div>
                        <div><dt class="text-muted-foreground">Fatalities</dt><dd class="tabular-nums">{{ $event->fatalities_count }}</dd></div>
                        <div><dt class="text-muted-foreground">Hazmat released</dt><dd>{{ $yesNo($event->hazmat_released) }}</dd></div>
                    @elseif ($purchase)
                        <div><dt class="text-muted-foreground">Fuel</dt><dd class="tabular-nums">{{ rtrim(rtrim((string) $purchase->gallons, '0'), '.') }} gal × ${{ $purchase->price_per_gallon }}{{ $purchase->fuelType ? ' · '.$purchase->fuelType->name : '' }}</dd></div>
                        <div><dt class="text-muted-foreground">Total</dt><dd class="tabular-nums">${{ number_format((float) $purchase->total_amount, 2) }} {{ $purchase->currency }}</dd></div>
                        <div><dt class="text-muted-foreground">Station · odometer</dt><dd>{{ $purchase->location_name ?: '—' }} · {{ $purchase->odometer !== null ? number_format($purchase->odometer) : '—' }}</dd></div>
                    @endif
                    @if ($event->reason)
                        <div class="sm:col-span-3"><dt class="text-muted-foreground">{{ $event->type === 'stop' ? 'Reason' : 'Notes' }}</dt><dd class="whitespace-pre-line">{{ $event->reason }}</dd></div>
                    @endif
                </dl>

                @if ($voided)
                    <p class="mt-3 text-sm">Voided {{ $event->voided_at->timezone($tz)->format('M d, Y g:i A') }}: {{ $event->void_reason }}</p>
                @endif

                @if ($files->isNotEmpty())
                    <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                        @foreach ($files as $document)
                            <li>
                                <a href="{{ route('trip-events.attachments.show', [$event, $document]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 underline underline-offset-2">
                                    {{ svg('lucide-file-text', 'size-4', ['aria-hidden' => 'true']) }}{{ $document->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ol>
@endif
