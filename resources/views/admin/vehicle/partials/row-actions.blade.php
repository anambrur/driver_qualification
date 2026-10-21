{{-- DataTables cell (vehicles, trailers, asset groups). Ids and labels go in data-* only, never inline JS.
     Needs $id, $label, $deleted, $canEdit, $canDelete; $viewUrl (optional) adds a View link. --}}
<div class="flex items-center justify-end gap-1">
    @if ($deleted && $canEdit)
        {{-- A deleted row can't be edited (edit() 404s), only restored --}}
        <button type="button" class="btn" data-variant="outline" data-size="sm" data-action="restore" data-id="{{ $id }}" data-name="{{ $label }}">
            {{ svg('lucide-archive-restore', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Restore
        </button>
    @elseif (! $deleted)
        @if (! empty($viewUrl))
        <a href="{{ $viewUrl }}" class="btn" data-variant="ghost" data-size="icon-sm" title="View" aria-label="View {{ $label }}">{{ svg('lucide-eye', '', ['aria-hidden' => 'true']) }}</a>
        @endif
        @if ($canEdit)
        <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" title="Edit" aria-label="Edit {{ $label }}"
            data-action="edit" data-id="{{ $id }}">{{ svg('lucide-pencil', '', ['aria-hidden' => 'true']) }}</button>
        @endif
        @if ($canDelete)
        <button type="button" class="btn text-destructive" data-variant="ghost" data-size="icon-sm" title="Delete" aria-label="Delete {{ $label }}"
            data-action="delete" data-id="{{ $id }}" data-name="{{ $label }}">{{ svg('lucide-trash-2', '', ['aria-hidden' => 'true']) }}</button>
        @endif
    @endif
</div>
