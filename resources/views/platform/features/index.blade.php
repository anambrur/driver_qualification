@extends('layouts.platform')

@section('title', 'Features')

@section('content')
    <h1 class="mb-2 text-xl font-semibold text-gray-800 dark:text-white/90">Features</h1>
    <p class="mb-6 text-sm text-gray-500">General: plans may include it. Override only: only a company override grants it. Disabled: nobody has it. Changes apply to every company at once.</p>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                <tr><th class="px-4 py-3">Feature</th><th class="px-4 py-3">Group</th><th class="px-4 py-3">Kind</th><th class="px-4 py-3">Availability</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($features as $feature)
                    <tr class="text-gray-700 dark:text-gray-300">
                        <td class="px-4 py-3">{{ $feature->name }} <span class="text-xs text-gray-500">{{ $feature->key }}</span></td>
                        <td class="px-4 py-3">{{ $feature->group }}</td>
                        <td class="px-4 py-3">{{ $feature->kind }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('platform.features.update', $feature->key) }}" class="flex gap-2">
                                @csrf
                                @method('PUT')
                                <select name="availability" class="h-9 rounded-lg border border-gray-300 px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    @foreach ($availability as $option)
                                        <option value="{{ $option }}" @selected($feature->availability === $option)>{{ str_replace('_', ' ', $option) }}</option>
                                    @endforeach
                                </select>
                                <button class="text-sm text-brand-600 hover:underline">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
