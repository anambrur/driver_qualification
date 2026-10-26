{{-- Plain partial (DataTables row): edit, activate or deactivate, delete --}}
<div class="flex items-center justify-end gap-1">
    <a href="{{ route('shippers.edit', $shipper) }}" class="btn" data-variant="ghost" data-size="sm">{{ svg('lucide-pencil', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Edit</a>
    <form method="POST" action="{{ route($shipper->is_active ? 'shippers.deactivate' : 'shippers.activate', $shipper) }}">
        @csrf
        <button type="submit" class="btn" data-variant="ghost" data-size="sm">{{ $shipper->is_active ? 'Deactivate' : 'Activate' }}</button>
    </form>
    <form method="POST" action="{{ route('shippers.destroy', $shipper) }}"
        data-confirm="Delete this shipper?" data-confirm-button="Delete" data-confirm-text="{{ $shipper->name }} is removed from the list. Trips that use it keep it.">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn text-destructive" data-variant="ghost" data-size="sm" aria-label="Delete {{ $shipper->name }}">{{ svg('lucide-trash-2', '', ['aria-hidden' => 'true']) }}</button>
    </form>
</div>
