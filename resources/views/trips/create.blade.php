{{-- New trip (req 4.1): details only; the stops are added on the trip page. --}}
@extends('layouts.main-layout')

@section('title', 'New trip')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="New trip" description="Save the details first, then add the start and drop-off on the map.">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Trips', 'href' => route('trips.index')], ['label' => 'New trip']]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <form method="POST" action="{{ route('trips.store') }}" class="max-w-4xl space-y-6">
            @csrf
            @include('trips.partials.details-fields', ['numberEditable' => true])
            <div class="flex justify-end gap-2">
                <x-ui.button variant="outline" :href="route('trips.index')">Back</x-ui.button>
                <x-ui.button type="submit" icon="check">Create trip</x-ui.button>
            </div>
        </form>
    </div>
@endsection
