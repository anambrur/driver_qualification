@extends('layouts.platform')

@section('title', 'Support sessions')

@php
    $state = fn ($s) => match (true) {
        $s->status === 'requested' && ! $s->isOpen() => 'expired',
        $s->status === 'requested' => 'waiting for '.collect([$s->tenant_approved_at ? null : 'the owner', $s->platform_approved_at ? null : 'a second Platform Owner'])->filter()->join(' and '),
        $s->status === 'approved' && ! $s->isOpen() => 'expired',
        default => $s->status,
    };
    $scope = fn ($s) => $s->scope === 'read_write' ? 'Read and write' : 'Read only';
@endphp

@section('content')
    <x-ui.page-header title="Support sessions" description="The only way into a company's data. The company owner and a second Platform Owner must both approve. Every request in a session is audited.">
        @can('platform.support.request')
            <x-slot:actions>
                <x-ui.button icon="life-buoy" :href="route('platform.support-sessions.create')">Request access</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <div class="space-y-6">
        @if ($awaiting->isNotEmpty())
            <x-ui.card title="Waiting for your approval" description="Requests from other staff. Approving makes you the second Platform Owner.">
                <x-ui.table>
                    <thead>
                        <tr><th>Request</th><th>Requested by</th><th>Reason</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($awaiting as $session)
                            <tr>
                                <td data-label="Request" data-primary>
                                    @include('platform.partials.cell', ['title' => $session->company?->company_name, 'sub' => '#'.$session->id.' · '.$scope($session)])
                                </td>
                                <td data-label="Requested by">{{ $session->requester?->name }}</td>
                                <td data-label="Reason" class="max-w-md whitespace-normal">{{ $session->reason }}</td>
                                <td class="text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('platform.support-sessions.approve', $session) }}">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" icon="check">Approve</x-ui.button>
                                        </form>
                                        <form method="POST" action="{{ route('platform.support-sessions.reject', $session) }}" class="flex gap-2">
                                            @csrf
                                            <x-ui.input name="reason" :id="'reject-'.$session->id" placeholder="Why not" required maxlength="1000" class="w-36!" aria-label="Reason for rejecting request #{{ $session->id }}" />
                                            <x-ui.button type="submit" variant="ghost" size="sm">Reject</x-ui.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @endif

        <x-ui.card title="My requests" description="Your last 50 requests. Start a session once both approvals are in.">
            @if ($mine->isEmpty())
                <x-ui.empty-state icon="life-buoy" title="No requests yet" class="py-6" />
            @else
                <x-ui.table>
                    <thead>
                        <tr><th>Company</th><th>Access</th><th>State</th><th>Ends</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($mine as $session)
                            <tr>
                                <td data-label="Company" data-primary>
                                    @include('platform.partials.cell', ['title' => $session->company?->company_name, 'sub' => '#'.$session->id])
                                </td>
                                <td data-label="Access">{{ $scope($session) }}</td>
                                <td data-label="State">
                                    {{ ucfirst($state($session)) }}
                                    @if ($session->end_reason)
                                        <span class="block text-xs text-muted-foreground">{{ $session->end_reason }}</span>
                                    @endif
                                </td>
                                <td data-label="Ends" class="whitespace-nowrap text-muted-foreground">{{ $session->expires_at?->format('M j, g:i A') ?? '—' }}</td>
                                <td class="text-right">
                                    @if ($session->status === 'approved' && $session->isOpen())
                                        <form method="POST" action="{{ route('platform.support-sessions.start', $session) }}">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" icon="log-in">Start session</x-ui.button>
                                        </form>
                                    @elseif ($session->status === 'active')
                                        <form method="POST" action="{{ route('platform.support-sessions.end', $session) }}">
                                            @csrf
                                            <x-ui.button type="submit" variant="outline" size="sm" icon="log-out">End session</x-ui.button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
@endsection
