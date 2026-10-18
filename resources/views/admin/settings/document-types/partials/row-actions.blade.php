{{-- DataTables cell: plain partial, rendered per row. Ids and names go in data-* only, never inline JS. --}}
<div class="flex items-center justify-end gap-1">
    @if ($canOptOut)
        <button type="button" class="btn" data-variant="outline" data-size="sm" data-action="company-toggle" data-id="{{ $type->id }}"
            title="{{ $enabled ? 'Stop requiring this document for your company' : 'Require this document for your company' }}">
            {{ $enabled ? 'Switch off' : 'Switch on' }}
        </button>
    @endif
    @if ($canManage)
        <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" title="{{ $type->status ? 'Deactivate' : 'Activate' }}"
            aria-label="{{ $type->status ? 'Deactivate' : 'Activate' }}" data-action="toggle-status" data-id="{{ $type->id }}" data-name="{{ $type->name }}"
            data-active="{{ $type->status ? '1' : '0' }}">{{ svg($type->status ? 'lucide-toggle-right' : 'lucide-toggle-left', '', ['aria-hidden' => 'true']) }}</button>
        <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" title="Edit" aria-label="Edit"
            data-action="edit" data-id="{{ $type->id }}">{{ svg('lucide-pencil', '', ['aria-hidden' => 'true']) }}</button>
        <button type="button" class="btn text-destructive" data-variant="ghost" data-size="icon-sm" title="Delete" aria-label="Delete"
            data-action="delete" data-id="{{ $type->id }}" data-name="{{ $type->name }}">{{ svg('lucide-trash-2', '', ['aria-hidden' => 'true']) }}</button>
    @endif
</div>
