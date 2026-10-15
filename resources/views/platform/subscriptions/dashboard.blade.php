@extends('layouts.platform')

@section('title', 'Subscriptions')

@php($card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">Subscriptions</h1>

    <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="{{ $card }}"><p class="text-xs text-gray-500">Revenue (all time)</p><p class="text-2xl font-semibold text-gray-800 dark:text-white/90">${{ number_format($revenueTotal, 2) }}</p></div>
        <div class="{{ $card }}"><p class="text-xs text-gray-500">Revenue this month</p><p class="text-2xl font-semibold text-gray-800 dark:text-white/90">${{ number_format($revenueMonth, 2) }}</p></div>
        <div class="{{ $card }}"><p class="text-xs text-gray-500">Ending in 7 days</p><p class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $endingSoon }}</p></div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach (['By status' => $byStatus, 'By source' => $bySource] as $title => $counts)
            <div class="{{ $card }}">
                <h2 class="mb-2 text-sm font-medium text-gray-500">{{ $title }} (open)</h2>
                @forelse ($counts as $key => $total)
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', $key) }}: {{ $total }}</p>
                @empty
                    <p class="text-sm text-gray-500">None.</p>
                @endforelse
            </div>
        @endforeach
    </div>

    <div class="{{ $card }}">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent</h2>
            <a href="{{ route('platform.subscriptions.index') }}" class="text-sm text-brand-600 hover:underline">All subscriptions</a>
        </div>
        @include('platform.subscriptions.table', ['subscriptions' => $recent])
    </div>
@endsection
