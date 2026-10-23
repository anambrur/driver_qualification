@extends('layouts.main-layout')

@section('title', 'Documents')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Documents" description="Company documents in your own folders. Driver, vehicle and trailer folders are on their pages.">
            @feature('zip_export')
                @if (app(\App\Services\Exports\ExportService::class)->canRequest(auth()->user(), 'zip_company'))
                    <x-slot:actions>
                        <form action="{{ route('exports.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="type" value="zip_company">
                            <x-ui.button type="submit" variant="outline" icon="archive">Download company ZIP</x-ui.button>
                        </form>
                    </x-slot:actions>
                @endif
            @endfeature
        </x-ui.page-header>

        @include('documents.partials.browser', ['browser' => $browser])
    </div>
@endsection
