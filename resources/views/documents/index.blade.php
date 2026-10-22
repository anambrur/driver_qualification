@extends('layouts.main-layout')

@section('title', 'Documents')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Documents" description="Company documents in your own folders. Driver, vehicle and trailer folders are on their pages." />

        @include('documents.partials.browser', ['browser' => $browser])
    </div>
@endsection
