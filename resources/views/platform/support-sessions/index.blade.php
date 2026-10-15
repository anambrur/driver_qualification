@extends('layouts.platform')

@section('title', 'Support sessions')

@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $input = 'h-9 rounded-lg border border-gray-300 px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $button = 'rounded-lg bg-brand-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-600';
    $state = fn ($s) => match (true) {
        $s->status === 'requested' && ! $s->isOpen() => 'expired',
        $s->status === 'requested' => 'waiting for '.collect([$s->tenant_approved_at ? null : 'the owner', $s->platform_approved_at ? null : 'a second Platform Owner'])->filter()->join(' and '),
        $s->status === 'approved' && ! $s->isOpen() => 'expired',
        default => $s->status,
    };
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Support sessions</h1>
            <p class="text-sm text-gray-500">The only way into a company's data. The company owner and a second Platform Owner must both approve. Every request in a session is audited.</p>
        </div>
        @can('platform.support.request')
            <a href="{{ route('platform.support-sessions.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Request access</a>
        @endcan
    </div>

    @if ($awaiting->isNotEmpty())
        <section class="{{ $card }} mb-4">
            <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Waiting for your approval</h2>
            <table class="min-w-full text-left text-sm">
                <tbody class="divide-y divide-gray-100 align-top dark:divide-gray-800">
                    @foreach ($awaiting as $session)
                        <tr class="text-gray-700 dark:text-gray-300">
                            <td class="py-2 pr-4">#{{ $session->id }} · {{ $session->company?->company_name }}</td>
                            <td class="py-2 pr-4">{{ $session->requester?->name }} · {{ $session->scope === 'read_write' ? 'read and write' : 'read only' }}</td>
                            <td class="py-2 pr-4">{{ $session->reason }}</td>
                            <td class="py-2">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('platform.support-sessions.approve', $session) }}">
                                        @csrf
                                        <button class="{{ $button }}">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('platform.support-sessions.reject', $session) }}" class="flex gap-2">
                                        @csrf
                                        <input name="reason" placeholder="Why not" class="{{ $input }}" required maxlength="1000">
                                        <button class="text-sm text-red-600 hover:underline">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section class="{{ $card }}">
        <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">My requests</h2>
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-gray-500">
                <tr><th class="py-2 pr-4">#</th><th class="py-2 pr-4">Company</th><th class="py-2 pr-4">Scope</th><th class="py-2 pr-4">State</th><th class="py-2 pr-4">Ends</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($mine as $session)
                    <tr class="text-gray-700 dark:text-gray-300">
                        <td class="py-2 pr-4">{{ $session->id }}</td>
                        <td class="py-2 pr-4">{{ $session->company?->company_name }}</td>
                        <td class="py-2 pr-4">{{ $session->scope === 'read_write' ? 'read and write' : 'read only' }}</td>
                        <td class="py-2 pr-4">{{ $state($session) }}{{ $session->end_reason ? ' ('.$session->end_reason.')' : '' }}</td>
                        <td class="py-2 pr-4">{{ $session->expires_at?->format('M j, g:i A') ?? '—' }}</td>
                        <td class="py-2">
                            @if ($session->status === 'approved' && $session->isOpen())
                                <form method="POST" action="{{ route('platform.support-sessions.start', $session) }}">
                                    @csrf
                                    <button class="{{ $button }}">Start session</button>
                                </form>
                            @elseif ($session->status === 'active')
                                <form method="POST" action="{{ route('platform.support-sessions.end', $session) }}">
                                    @csrf
                                    <button class="text-sm text-red-600 hover:underline">End session</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-gray-500">No requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
