@extends('layouts.main-layout')

@section('title', 'Support access')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Support access"
            description="Platform support can only see your records in a session you approve, and every page they open is logged. You can refuse a request, and end a session at any time." />

        <x-ui.flash />

        @if ($sessions->isEmpty())
            <x-ui.empty-state icon="life-buoy" title="No requests" description="Platform support has not asked for access." />
        @else
            <x-ui.card flush class="py-0!">
                <x-ui.table>
                    <thead>
                        <tr><th>Requested</th><th>By</th><th>Access</th><th>Reason</th><th>State</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            @php
                                [$tone, $state] = match (true) {
                                    $session->status === 'active' => ['success', 'Active, ends '.$session->expires_at?->format('g:i A')],
                                    $session->isOpen() && (bool) $session->tenant_approved_at => ['info', 'Approved by you'.($session->status === 'approved' ? ', not started' : ', waiting for the platform')],
                                    $session->isOpen() => ['warning', 'Waiting for you'],
                                    default => ['neutral', ucfirst(in_array($session->status, ['requested', 'approved'], true) ? 'expired' : $session->status).($session->end_reason ? ' ('.$session->end_reason.')' : '')],
                                };
                            @endphp
                            <tr>
                                <td data-label="Requested" data-primary class="whitespace-nowrap">{{ $session->requested_at?->format('M j, Y g:i A') }}</td>
                                <td data-label="By">{{ $session->requester?->name }}</td>
                                <td data-label="Access">{{ $session->scope === 'read_write' ? 'View and change' : 'View only' }}</td>
                                <td data-label="Reason" class="whitespace-normal">{{ $session->reason }}</td>
                                <td data-label="State"><x-ui.badge :tone="$tone" dot>{{ $state }}</x-ui.badge></td>
                                <td class="text-right">
                                    <div class="flex flex-wrap justify-end gap-1">
                                        @if ($session->isOpen() && ! $session->tenant_approved_at)
                                            <form method="POST" action="{{ route('support-access.approve', $session) }}">
                                                @csrf
                                                <x-ui.button type="submit" size="sm" icon="check">Approve</x-ui.button>
                                            </form>
                                            <form method="POST" action="{{ route('support-access.reject', $session) }}"
                                                data-confirm="Refuse this request?" data-confirm-button="Refuse"
                                                data-confirm-text="{{ $session->requester?->name ?? 'Platform support' }} can't open your records for this request.">
                                                @csrf
                                                <x-ui.button type="submit" size="sm" variant="outline">Refuse</x-ui.button>
                                            </form>
                                        @elseif ($session->status === 'active' || $session->isOpen())
                                            <form method="POST" action="{{ route('support-access.revoke', $session) }}"
                                                data-confirm="{{ $session->status === 'active' ? 'End this support session now?' : 'Withdraw your approval?' }}"
                                                data-confirm-button="{{ $session->status === 'active' ? 'End now' : 'Withdraw' }}"
                                                data-confirm-text="Platform support loses access to your records straight away.">
                                                @csrf
                                                <x-ui.button type="submit" size="sm" variant="outline" class="text-destructive">{{ $session->status === 'active' ? 'End now' : 'Withdraw approval' }}</x-ui.button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @endif
    </div>
@endsection
