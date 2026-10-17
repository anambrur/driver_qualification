{{-- DataTables cell: plain partial, rendered per row. The name goes in data-* only, never inline JS (LKP-02). --}}
<div class="flex items-center justify-end gap-1">
    <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" title="Edit" aria-label="Edit"
        data-action="edit" data-id="{{ $row->id }}">{{ svg('lucide-pencil', '', ['aria-hidden' => 'true']) }}</button>
    <button type="button" class="btn text-destructive" data-variant="ghost" data-size="icon-sm" title="Delete" aria-label="Delete"
        data-action="delete" data-id="{{ $row->id }}" data-name="{{ $row->name }}">{{ svg('lucide-trash-2', '', ['aria-hidden' => 'true']) }}</button>
</div>
