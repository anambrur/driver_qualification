{{-- Overall compliance bar on the compliance dashboards ($overallCompliance is a percentage). --}}
<div>
    <div class="mb-1 flex items-center justify-between text-sm">
        <span class="font-medium text-foreground">Overall Compliance</span>
        <span class="font-semibold tabular-nums text-foreground">{{ $overallCompliance }}%</span>
    </div>
    <div class="h-2 w-full rounded-full bg-muted" role="progressbar" aria-label="Overall Compliance" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $overallCompliance }}">
        <div class="h-full rounded-full bg-primary" style="width: {{ $overallCompliance }}%"></div>
    </div>
</div>
