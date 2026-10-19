@php
    $sessionId = app(\App\Tenancy\TenantContext::class)->supportSessionId();
    $supportSession = $sessionId ? \App\Models\PlatformSupportSession::query()->with('requester:id,name')->find($sessionId) : null;
@endphp
@if ($supportSession)
    {{-- Shown on every tenant page inside a support session (04 §4.3) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-warning/50 bg-warning/15 px-6 py-3 text-sm text-foreground" role="status">
        <span>
            <strong>Support session</strong> by {{ $supportSession->requester?->name }}
            ({{ $supportSession->scope === 'read_write' ? 'read and write' : 'read only' }}), ends {{ $supportSession->expires_at?->format('g:i A T') }}.
            Every page is logged.
        </span>
        <form method="POST" action="{{ route('platform.support-sessions.end', $supportSession) }}">
            @csrf
            <button class="btn" data-variant="outline" data-size="sm">End session</button>
        </form>
    </div>
@endif
