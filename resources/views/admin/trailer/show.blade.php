@extends('layouts.main-layout')

@section('title', 'Trailer details')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="'Trailer '.$trailer->unit_no">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Trailers', 'href' => route('admin.trailer.index')], ['label' => $trailer->unit_no]]" />
            </x-slot:breadcrumbs>
            <x-slot:actions>
                <x-ui.button :href="route('admin.trailer.index')" variant="outline" icon="arrow-left">Back</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @include('admin.vehicle.partials.asset-summary', ['asset' => $trailer, 'icon' => 'container', 'photoRoute' => 'admin.trailer.photo'])

        <x-ui.card title="Specifications" class="mt-6">
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.detail label="Equipment type" :value="$trailer->equipmentType?->name" />
                <x-ui.detail label="Vehicle group" :value="$trailer->vehicleGroup?->name" />
                <x-ui.detail label="Owned by" :value="$trailer->owned_by" />
                <x-ui.detail label="Color" :value="$trailer->color" />
                <x-ui.detail label="Title number" :value="$trailer->title_no" />
                <x-ui.detail label="GVW" :value="$trailer->gvw ? number_format($trailer->gvw).' lbs' : null" />
                <x-ui.detail label="Tire size" :value="$trailer->tire_size" />
                <x-ui.detail label="Notes" :value="$trailer->notes" class="whitespace-pre-line sm:col-span-2 lg:col-span-4" />
            </dl>
        </x-ui.card>
    </div>
@endsection
