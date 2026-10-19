{{-- The driver assigned to a vehicle or trailer row ($asset['driver'] may be null). --}}
<span aria-hidden="true">•</span>
<span class="inline-flex items-center gap-1">
    {{ svg('lucide-user', 'size-3.5', ['aria-hidden' => 'true']) }}
    @if ($asset['driver'])
        {{ $asset['driver']->first_name }} {{ $asset['driver']->last_name }}
    @else
        No assigned driver
    @endif
</span>
