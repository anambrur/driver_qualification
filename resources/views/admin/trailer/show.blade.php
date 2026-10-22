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

        @if ($documentsBrowser)
            {{-- A folder link opens the Documents tab even before the page's script runs. --}}
            @php($openTab = request()->filled('folder') ? 'documents' : 'overview')
            <x-ui.tabs id="asset-tabs" :tabs="['overview' => 'Overview', 'documents' => 'Documents']" :selected="$openTab" class="mt-6">
                <x-ui.tab-panel tabs="asset-tabs" name="overview" :selected="$openTab === 'overview'" class="mt-4">
                    @include('admin.trailer.partials.specifications')
                </x-ui.tab-panel>
                <x-ui.tab-panel tabs="asset-tabs" name="documents" :selected="$openTab === 'documents'" class="mt-4">
                    @include('documents.partials.browser', ['browser' => $documentsBrowser, 'virtual' => $virtualDocuments])
                </x-ui.tab-panel>
            </x-ui.tabs>
        @else
            <div class="mt-6">
                @include('admin.trailer.partials.specifications')
            </div>
        @endif
    </div>
@endsection

@if ($documentsBrowser)
    @include('documents.partials.hash-tabs', ['tabs' => 'asset-tabs'])
@endif
