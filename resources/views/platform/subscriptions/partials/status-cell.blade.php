{{-- DataTables cell: the subscription status, and whether it has ended. --}}
<div class="flex flex-wrap items-center gap-1.5">
    @include('partials.ui.status-badge', ['status' => $subscription->status])
    @if ($subscription->ended_at)
        <span class="text-xs text-muted-foreground">ended</span>
    @endif
</div>
