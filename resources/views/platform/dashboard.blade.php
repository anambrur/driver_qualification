@extends('layouts.platform')

@section('title', 'Dashboard')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">Platform overview</h1>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">Companies</h2>
            <dl class="grid grid-cols-3 gap-3">
                @foreach (['active', 'suspended', 'deactivated'] as $status)
                    <div>
                        <dt class="text-xs capitalize text-gray-500">{{ $status }}</dt>
                        <dd class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $companies[$status] ?? 0 }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">Open subscriptions</h2>
            <dl class="grid grid-cols-3 gap-3">
                @forelse ($subscriptions as $status => $total)
                    <div>
                        <dt class="text-xs text-gray-500">{{ str_replace('_', ' ', $status) }}</dt>
                        <dd class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $total }}</dd>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">None yet.</p>
                @endforelse
            </dl>
        </div>
    </div>
@endsection
