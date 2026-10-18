{{-- DataTables cell (schedules, service logs). Ids and names go in data-* only, never inline JS. --}}
<div class="flex items-center justify-end gap-1">
    <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" title="View details" aria-label="View details"
        data-action="view" data-id="{{ $id }}">{{ svg('lucide-eye', '', ['aria-hidden' => 'true']) }}</button>
    @if ($can['edit'])
        <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" title="Edit" aria-label="Edit"
            data-action="edit" data-id="{{ $id }}">{{ svg('lucide-pencil', '', ['aria-hidden' => 'true']) }}</button>
    @endif
    @if ($can['delete'])
        <button type="button" class="btn text-destructive" data-variant="ghost" data-size="icon-sm" title="Delete" aria-label="Delete"
            data-action="delete" data-id="{{ $id }}" data-name="{{ $name }}">{{ svg('lucide-trash-2', '', ['aria-hidden' => 'true']) }}</button>
    @endif
</div>
