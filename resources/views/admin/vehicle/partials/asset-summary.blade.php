{{-- Show page card (vehicles, trailers): photo thumbnail, unit and plate. Needs $asset, $icon and
     $photoRoute (the authorized photo route name). --}}
<x-ui.card>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
        @if ($asset->photo_file_id)
            <a href="{{ route($photoRoute, $asset->id) }}" target="_blank" rel="noopener" class="shrink-0">
                <img src="{{ route($photoRoute, $asset->id) }}" alt="Photo of unit {{ $asset->unit_no }}" width="192" height="128"
                    class="h-32 w-48 rounded-lg border object-cover">
            </a>
        @else
            <span class="flex h-32 w-48 shrink-0 items-center justify-center rounded-lg border bg-muted text-muted-foreground">
                {{ svg('lucide-'.$icon, 'size-8', ['aria-label' => 'No photo', 'role' => 'img']) }}
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-lg font-semibold text-foreground">{{ $asset->unit_no }}</p>
                <span class="text-muted-foreground">{{ trim($asset->year.' '.$asset->make.' '.$asset->model) }}</span>
            </div>
            <p class="mt-1 font-mono text-xs text-muted-foreground">VIN {{ $asset->vin }}</p>
            <dl class="mt-4 grid grid-cols-2 gap-4 sm:max-w-sm">
                <x-ui.detail label="Plate number" :value="$asset->plate_number" mono />
                <x-ui.detail label="Plate state" :value="$asset->plate_state_code" />
            </dl>
        </div>
    </div>
</x-ui.card>
