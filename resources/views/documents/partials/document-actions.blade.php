{{-- DataTables cell. Ids and labels go in data-* only, never inline JS. Needs $document, $canMove, $canDelete. --}}
<div class="flex items-center justify-end gap-1">
    <a href="{{ route('documents.download', $document) }}" class="btn" data-variant="ghost" data-size="icon-sm" title="Download" aria-label="Download {{ $document->title }}">
        {{ svg('lucide-download', '', ['aria-hidden' => 'true']) }}
    </a>
    @if ($canMove)
        <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" title="Move" aria-label="Move {{ $document->title }}"
            data-move-document="{{ route('documents.move', $document) }}" data-name="{{ $document->title }}">{{ svg('lucide-folder-input', '', ['aria-hidden' => 'true']) }}</button>
    @endif
    @if ($canDelete)
        <form action="{{ route('documents.destroy', $document) }}" method="POST" data-confirm="Delete “{{ $document->title }}”?" data-confirm-text="The document leaves this folder. Its file is kept for the retention period." data-confirm-button="Delete">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn text-destructive" data-variant="ghost" data-size="icon-sm" title="Delete" aria-label="Delete {{ $document->title }}">{{ svg('lucide-trash-2', '', ['aria-hidden' => 'true']) }}</button>
        </form>
    @endif
</div>
