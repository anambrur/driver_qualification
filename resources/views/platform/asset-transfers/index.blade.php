@extends('layouts.platform')

@section('title', 'Asset transfers')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Asset transfers</h1>
        <a href="{{ route('platform.asset-transfers.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">New transfer</a>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-gray-500">
                <tr><th class="py-2 pr-4">#</th><th class="py-2 pr-4">When</th><th class="py-2 pr-4">Asset</th><th class="py-2 pr-4">From → to</th><th class="py-2 pr-4">Copied</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4">By</th><th class="py-2 pr-4">Reason</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 align-top dark:divide-gray-800">
                @forelse ($transfers as $transfer)
                    <tr class="text-gray-700 dark:text-gray-300">
                        <td class="py-2 pr-4">{{ $transfer->id }}</td>
                        <td class="py-2 pr-4 whitespace-nowrap">{{ $transfer->performed_at?->format('Y-m-d H:i') }}</td>
                        <td class="py-2 pr-4">{{ $transfer->asset_type }}</td>
                        <td class="py-2 pr-4">{{ $transfer->sourceCompany?->company_name }} → {{ $transfer->targetCompany?->company_name }}</td>
                        <td class="py-2 pr-4 text-xs">
                            @foreach ($transfer->copied_counts ?? [] as $what => $count)
                                {{ str_replace('_', ' ', $what) }}: {{ $count }}<br>
                            @endforeach
                        </td>
                        <td class="py-2 pr-4">{{ $transfer->status }}</td>
                        <td class="py-2 pr-4">{{ $transfer->performedBy?->name }}</td>
                        <td class="py-2 pr-4">{{ $transfer->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-4 text-gray-500">No transfers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transfers->links() }}</div>
@endsection
