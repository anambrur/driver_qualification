{{-- DataTables cell: plain partial, rendered per row. Name/id go in data-* only, never inline JS (DRV-01). --}}
<div class="flex items-center justify-end gap-1">
    <a href="{{ route('admin.driver.show', $driver->id) }}" class="btn" data-variant="ghost" data-size="icon-sm" aria-label="View {{ $driver->first_name }}" title="View">{{ svg('lucide-eye', '', ['aria-hidden' => 'true']) }}</a>
    <a href="{{ route('admin.driver.edit', $driver->id) }}" class="btn" data-variant="ghost" data-size="icon-sm" aria-label="Edit {{ $driver->first_name }}" title="Edit">{{ svg('lucide-pencil', '', ['aria-hidden' => 'true']) }}</a>
    <button type="button" class="btn text-destructive" data-variant="ghost" data-size="icon-sm" title="Delete" aria-label="Delete {{ $driver->first_name }}"
        data-action="delete-driver" data-driver-id="{{ $driver->id }}" data-driver-name="{{ $driver->first_name.' '.$driver->last_name }}">{{ svg('lucide-trash-2', '', ['aria-hidden' => 'true']) }}</button>
</div>
