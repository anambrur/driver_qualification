{{-- A trip (req 4.1–4.6, 5.1–5.3, 6.1, 6.3, 7.1–7.6): assignment and progress, Details, Stops (map), the pre-trip Inspection, Events and its Documents folder. --}}
@extends('layouts.main-layout')

@section('title', 'Trip '.$trip->label())

@section('content')
    @php
        $user = auth()->user();
        $canConfirm = $trip->status === 'draft' && ! $trip->isNumberLocked() && $user->can('trips.confirm-number');
        $canCancel = $trip->canBeCancelled() && $user->can('trips.edit');
        $canDelete = $trip->canBeDeleted() && $user->can('trips.delete');
        $canConfirmEquipment = $trip->status === 'assigned' && $trip->equipment_confirmed_at === null && $user->can('trips.assign');
        $canOperate = $user->can('trips.edit');
        $canPickUp = $canOperate && $trip->status === 'in_progress' && $trip->pickupStop() !== null && ! $trip->isPickedUp();
        $tabs = ['details' => 'Details', 'stops' => 'Stops'] + ($inspections !== null ? ['inspection' => 'Inspection'] : []) + ($events !== null ? ['events' => 'Events'] : []) + ($documentsBrowser ? ['documents' => 'Documents'] : []);
        // A folder link opens the Documents tab even before the page's script runs.
        $openTab = $documentsBrowser && request()->filled('folder') ? 'documents' : 'details';
        // A refused stop-times save opens the Stops tab, where its errors are
        if (collect($errors->keys())->contains(fn ($key) => $key === 'stops' || str_starts_with($key, 'stops.'))) {
            $openTab = 'stops';
        }
        if ($inspections !== null && $errors->has('inspection')) {
            $openTab = 'inspection';
        }
        // A refused event, fuel stop, file or void opens the Events tab (and its dialog)
        if ($events !== null && ($errors->has('event') || in_array(old('_form'), ['event', 'fuel', 'attach', 'void'], true))) {
            $openTab = 'events';
        }
    @endphp
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="'Trip '.$trip->label()" :description="$trip->trip_date->format('M d, Y').($trip->shipper ? ' · '.$trip->shipper->name : '')">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Trips', 'href' => route('trips.index')], ['label' => $trip->label()]]" />
            </x-slot:breadcrumbs>
            <x-slot:actions>
                @if ($canConfirm)
                    <form method="POST" action="{{ route('trips.confirm-number', $trip) }}"
                        data-confirm="Confirm trip {{ $trip->label() }}?" data-confirm-text="The number {{ $trip->trip_number }} and the date {{ $trip->trip_date->format('M d, Y') }} can't change afterwards." data-confirm-button="Confirm">
                        @csrf
                        <x-ui.button type="submit" icon="badge-check">Confirm number and date</x-ui.button>
                    </form>
                @endif
                @if ($canConfirmEquipment)
                    <form method="POST" action="{{ route('trips.equipment.confirm', $trip) }}"
                        data-confirm="Confirm the equipment?" data-confirm-text="Tractor {{ $trip->vehicle?->unit_no }}{{ $trip->trailer ? ' and trailer '.$trip->trailer->unit_no : ', no trailer' }} for {{ trim($trip->driver?->first_name.' '.$trip->driver?->last_name) }}." data-confirm-button="Confirm">
                        @csrf
                        <x-ui.button type="submit" variant="outline" icon="clipboard-check">Confirm equipment</x-ui.button>
                    </form>
                @endif
                @if ($canOperate && $trip->status === 'assigned')
                    @if ($trip->equipment_confirmed_at === null)
                        <x-ui.button icon="play" disabled title="Confirm the tractor and trailer first">Start trip</x-ui.button>
                    @elseif ($preTripBlocker)
                        <x-ui.button icon="play" disabled :title="$preTripBlocker">Start trip</x-ui.button>
                    @else
                        <form method="POST" action="{{ route('trips.start', $trip) }}"
                            data-confirm="Start trip {{ $trip->label() }}?" data-confirm-text="The trip is in progress from now. The driver and vehicle can't start another trip until it is completed." data-confirm-button="Start">
                            @csrf
                            <x-ui.button type="submit" icon="play">Start trip</x-ui.button>
                        </form>
                    @endif
                @endif
                @if ($canPickUp)
                    <form method="POST" action="{{ route('trips.picked-up', $trip) }}">
                        @csrf
                        <x-ui.button type="submit" icon="package-check">Picked up</x-ui.button>
                    </form>
                @endif
                @if ($canOperate && $trip->status === 'in_progress')
                    <x-ui.button :variant="$canPickUp ? 'outline' : null" icon="flag" data-dialog-open="trip-complete-dialog">Complete</x-ui.button>
                @endif
                @if ($canCancel)
                    <x-ui.button variant="outline" icon="circle-x" data-dialog-open="trip-cancel-dialog">Cancel trip</x-ui.button>
                @endif
                @if ($canDelete)
                    <form method="POST" action="{{ route('trips.destroy', $trip) }}"
                        data-confirm="Delete trip {{ $trip->label() }}?" data-confirm-text="The trip is removed from the list. Its number is not used again." data-confirm-button="Delete">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="destructive" icon="trash-2">Delete</x-ui.button>
                    </form>
                @endif
                <x-ui.button :href="route('trips.index')" variant="outline" icon="arrow-left">Back</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @error('trip')
            <x-ui.alert tone="danger" class="mb-6">{{ $message }}</x-ui.alert>
        @enderror
        @if ($preTripBlocker && $canOperate)
            <x-ui.alert tone="warning" class="mb-6">{{ $preTripBlocker }}@if ($inspections !== null) <a href="#inspection" class="font-medium underline underline-offset-2">Open the inspection</a>@endif</x-ui.alert>
        @endif
        @error('actual_distance_miles')
            <x-ui.alert tone="danger" class="mb-6">{{ $message }}</x-ui.alert>
        @enderror

        <div class="mb-6 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
            @include('partials.ui.status-badge', ['status' => $trip->status])
            <span>{{ $trip->isNumberLocked() ? 'Number confirmed '.$trip->number_confirmed_at->format('M d, Y') : 'Number not confirmed yet' }}</span>
            @if ($trip->status === 'draft' && $trip->isNumberLocked())
                <span>· Add a start and a drop-off to plan it.</span>
            @endif
        </div>

        <x-ui.tabs id="trip-tabs" :tabs="$tabs" :selected="$openTab">
            <x-ui.tab-panel tabs="trip-tabs" name="details" :selected="$openTab === 'details'" class="mt-4">
                @if ($trip->status !== 'draft')
                    <div class="mb-6 max-w-4xl">
                        @include('trips.partials.assignment')
                    </div>
                @endif
                @if ($canEdit)
                    <form method="POST" action="{{ route('trips.update', $trip) }}" class="max-w-4xl space-y-6">
                        @csrf
                        @method('PUT')
                        @include('trips.partials.details-fields', ['numberEditable' => $trip->status === 'draft' && ! $trip->isNumberLocked()])
                        <div class="flex justify-end">
                            <x-ui.button type="submit" icon="check">Save details</x-ui.button>
                        </div>
                    </form>
                @else
                    @include('trips.partials.details-view')
                @endif
            </x-ui.tab-panel>
            <x-ui.tab-panel tabs="trip-tabs" name="stops" :selected="$openTab === 'stops'" class="mt-4 max-w-4xl">
                @include('trips.partials.stops')
            </x-ui.tab-panel>
            @if ($inspections !== null)
                <x-ui.tab-panel tabs="trip-tabs" name="inspection" :selected="$openTab === 'inspection'" class="mt-4 max-w-4xl">
                    @include('trips.partials.inspection')
                </x-ui.tab-panel>
            @endif
            @if ($events !== null)
                <x-ui.tab-panel tabs="trip-tabs" name="events" :selected="$openTab === 'events'" class="mt-4 max-w-4xl">
                    @include('trips.partials.events')
                </x-ui.tab-panel>
            @endif
            @if ($documentsBrowser)
                <x-ui.tab-panel tabs="trip-tabs" name="documents" :selected="$openTab === 'documents'" class="mt-4">
                    @include('documents.partials.browser', ['browser' => $documentsBrowser, 'empty' => 'This trip has no folder yet.'])
                </x-ui.tab-panel>
            @endif
        </x-ui.tabs>
    </div>

    @if ($canCancel)
        <x-ui.dialog id="trip-cancel-dialog" :title="'Cancel trip '.$trip->label().'?'" description="A cancelled trip can't be planned or assigned again. It stays on the list." icon="circle-x" size="sm">
            <form id="trip-cancel-form" method="POST" action="{{ route('trips.cancel', $trip) }}" class="grid gap-4">
                @csrf
                <x-ui.field label="Reason" name="cancel_reason" required>
                    <x-ui.textarea name="cancel_reason" rows="3" maxlength="500" required />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" data-dialog-close>Keep trip</button>
                <button type="submit" form="trip-cancel-form" class="btn" data-variant="destructive">Cancel trip</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endif

    @if ($canOperate && $trip->status === 'in_progress')
        <x-ui.dialog id="trip-complete-dialog" :title="'Complete trip '.$trip->label().'?'" description="The drop-off is reached. A completed trip can't change any more." icon="flag" size="sm">
            <form id="trip-complete-form" method="POST" action="{{ route('trips.complete', $trip) }}" class="grid gap-4">
                @csrf
                <x-ui.field label="Actual miles" name="actual_distance_miles" hint="Optional. Prefilled with the planned miles.">
                    <x-ui.input type="number" name="actual_distance_miles" :value="$trip->planned_distance_miles" min="0" max="9999999.9" step="0.1" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" data-dialog-close>Not yet</button>
                <button type="submit" form="trip-complete-form" class="btn">Complete trip</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endif

    @if ($canRecordEvents)
        @include('trips.partials.event-dialogs')
    @endif
@endsection

@include('documents.partials.hash-tabs', ['tabs' => 'trip-tabs'])
