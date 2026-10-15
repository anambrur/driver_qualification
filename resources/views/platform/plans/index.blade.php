@extends('layouts.platform')

@section('title', 'Plans')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Plans</h1>
        <a href="{{ route('platform.plans.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">New plan</a>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                <tr><th class="px-4 py-3">Plan</th><th class="px-4 py-3">Current prices</th><th class="px-4 py-3">Public</th><th class="px-4 py-3">Active</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($plans as $plan)
                    <tr class="text-gray-700 dark:text-gray-300">
                        <td class="px-4 py-3"><a href="{{ route('platform.plans.edit', $plan) }}" class="font-medium text-brand-600 hover:underline">{{ $plan->name }}</a> <span class="text-xs text-gray-500">{{ $plan->slug }}</span></td>
                        <td class="px-4 py-3">
                            @foreach ($plan->prices->whereNull('archived_at') as $price)
                                <div>{{ $price->amount }} {{ $price->currency }} / {{ $price->interval }}</div>
                            @endforeach
                        </td>
                        <td class="px-4 py-3">{{ $plan->is_public ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">{{ $plan->is_active ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No plans yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
