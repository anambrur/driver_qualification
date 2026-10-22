{{-- DataTables cell: file icon, title and original type. Needs $document (file loaded). --}}
@php($ext = strtolower(pathinfo((string) $document->file?->path, PATHINFO_EXTENSION)))
<div class="flex min-w-0 items-center gap-3">
    <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
        {{ svg(in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? 'lucide-file-image' : (in_array($ext, ['xls', 'xlsx', 'csv'], true) ? 'lucide-file-spreadsheet' : 'lucide-file-text'), 'size-4', ['aria-hidden' => 'true']) }}
    </span>
    <div class="min-w-0">
        <p class="truncate font-medium">{{ $document->title }}</p>
        @if ($ext !== '')
            <p class="text-xs uppercase text-muted-foreground">{{ $ext }}</p>
        @endif
    </div>
</div>
