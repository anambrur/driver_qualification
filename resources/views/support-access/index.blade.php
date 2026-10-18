@extends('layouts.main-layout')

@section('title', 'Support access')

@section('content')
    <div class="p-4 mx-auto max-w-6xl md:p-6">
        <h1 class="mb-2 text-2xl font-bold text-foreground dark:text-white/90">Support access</h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Platform support can only see your records in a session you approve, and every page they open is logged.
            You can refuse a request, and end a session at any time.
        </p>

        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">{{ session('success') }}</div>
        @endif

        <div class="overflow-x-auto rounded-2xl border border-border bg-card p-5 dark:bg-white/[0.03]">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-muted-foreground">
                    <tr><th class="py-2 pr-4">Requested</th><th class="py-2 pr-4">By</th><th class="py-2 pr-4">Access</th><th class="py-2 pr-4">Reason</th><th class="py-2 pr-4">State</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-border align-top">
                    @forelse ($sessions as $session)
                        <tr class="text-foreground">
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $session->requested_at?->format('M j, Y g:i A') }}</td>
                            <td class="py-2 pr-4">{{ $session->requester?->name }}</td>
                            <td class="py-2 pr-4">{{ $session->scope === 'read_write' ? 'View and change' : 'View only' }}</td>
                            <td class="py-2 pr-4">{{ $session->reason }}</td>
                            <td class="py-2 pr-4">
                                @if ($session->status === 'active')
                                    Active, ends {{ $session->expires_at?->format('g:i A') }}
                                @elseif ($session->isOpen() && $session->tenant_approved_at)
                                    Approved by you{{ $session->status === 'approved' ? ', not started' : ', waiting for the platform' }}
                                @elseif ($session->isOpen())
                                    Waiting for you
                                @else
                                    {{ in_array($session->status, ['requested', 'approved'], true) ? 'expired' : $session->status }}{{ $session->end_reason ? ' ('.$session->end_reason.')' : '' }}
                                @endif
                            </td>
                            <td class="py-2">
                                <div class="flex flex-wrap gap-2">
                                    @if ($session->isOpen() && ! $session->tenant_approved_at)
                                        <form method="POST" action="{{ route('support-access.approve', $session) }}">
                                            @csrf
                                            <button class="rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary/90">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('support-access.reject', $session) }}">
                                            @csrf
                                            <button class="rounded-lg border border-input px-3 py-1.5 text-sm">Refuse</button>
                                        </form>
                                    @elseif ($session->status === 'active' || $session->isOpen())
                                        <form method="POST" action="{{ route('support-access.revoke', $session) }}">
                                            @csrf
                                            <button class="rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-600">{{ $session->status === 'active' ? 'End now' : 'Withdraw approval' }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-muted-foreground">Platform support has not asked for access.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
