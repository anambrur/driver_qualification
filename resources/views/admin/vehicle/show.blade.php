@extends('layouts.main-layout')

@section('title', 'Vehicle details')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="'Vehicle '.$vehicle->unit_no">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Vehicles', 'href' => route('admin.vehicle.index')], ['label' => $vehicle->unit_no]]" />
            </x-slot:breadcrumbs>
            <x-slot:actions>
                <x-ui.button :href="route('admin.vehicle.index')" variant="outline" icon="arrow-left">Back</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @include('admin.vehicle.partials.asset-summary', ['asset' => $vehicle, 'icon' => 'truck', 'photoRoute' => 'admin.vehicle.photo'])

        <x-ui.card title="Specifications" class="mt-6">
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.detail label="Vehicle type" :value="$vehicle->vehicleType?->name" />
                <x-ui.detail label="Vehicle group" :value="$vehicle->vehicleGroup?->name" />
                <x-ui.detail label="Fuel type" :value="$vehicle->fuelType?->name" />
                <x-ui.detail label="Owned by" :value="$vehicle->owned_by" />
                <x-ui.detail label="Color" :value="$vehicle->color" />
                <x-ui.detail label="Title number" :value="$vehicle->title_no" />
                <x-ui.detail label="Odometer" :value="number_format((int) $vehicle->odometer).' mi'" />
                <x-ui.detail label="Engine hours" :value="$vehicle->engine_hours !== null ? number_format($vehicle->engine_hours).' hrs' : null" />
                <x-ui.detail label="GVW" :value="$vehicle->gvw ? number_format($vehicle->gvw).' lbs' : null" />
                <x-ui.detail label="Axles" :value="$vehicle->no_axles" />
                <x-ui.detail label="Configuration" :value="$vehicle->configuration ? ucfirst($vehicle->configuration) : null" />
                <x-ui.detail label="Wheel base" :value="$vehicle->wheel_base ? $vehicle->wheel_base.' in' : null" />
                <x-ui.detail label="Tire size" :value="$vehicle->tire_size" />
                <x-ui.detail label="Size / dimension" :value="$vehicle->size_dimension" />
                <x-ui.detail label="Engine" :value="$vehicle->engine_type" />
                <x-ui.detail label="Transmission" :value="$vehicle->transmission" />
                <x-ui.detail label="Suspension" :value="$vehicle->suspension" />
                <x-ui.detail label="Notes" :value="$vehicle->notes" class="whitespace-pre-line sm:col-span-2 lg:col-span-4" />
            </dl>
        </x-ui.card>
    </div>
@endsection
