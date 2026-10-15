@php
    $sessionId = app(\App\Tenancy\TenantContext::class)->supportSessionId();
    $supportSession = $sessionId ? \App\Models\PlatformSupportSession::query()->with('requester:id,name')->find($sessionId) : null;
@endphp
@if ($supportSession)
    {{-- Shown on every tenant page inside a support session (04 §4.3) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-300 bg-amber-50 px-6 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100" role="status">
        <span>
            <strong>Support session</strong> by {{ $supportSession->requester?->name }}
            ({{ $supportSession->scope === 'read_write' ? 'read and write' : 'read only' }}), ends {{ $supportSession->expires_at?->format('g:i A T') }}.
            Every page is logged.
        </span>
        <form method="POST" action="{{ route('platform.support-sessions.end', $supportSession) }}">
            @csrf
            <button class="rounded-lg border border-amber-400 px-3 py-1 font-medium hover:bg-amber-100 dark:hover:bg-amber-800">End session</button>
        </form>
    </div>
@endif
