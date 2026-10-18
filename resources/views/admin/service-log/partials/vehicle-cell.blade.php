{{-- DataTables cell: plain partial, rendered per row. --}}
@if ($vehicle)
    <div class="flex items-center gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">{{ svg('lucide-truck', 'size-4', ['aria-hidden' => 'true']) }}</span>
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 truncate font-medium text-foreground">
                {{ $vehicle->unit_no }}<span class="font-normal text-muted-foreground">· {{ trim($vehicle->year.' '.$vehicle->make.' '.$vehicle->model) }}</span>
                @if ($documents > 0)
                    {{ svg('lucide-paperclip', 'size-3.5 shrink-0 text-muted-foreground', ['aria-label' => $documents.' '.\Illuminate\Support\Str::plural('document', $documents), 'role' => 'img']) }}
                @endif
            </p>
            <p class="truncate font-mono text-xs text-muted-foreground">VIN {{ $vehicle->vin }}</p>
        </div>
    </div>
@else
    <span class="text-muted-foreground">Deleted vehicle</span>
@endif
