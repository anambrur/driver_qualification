{{-- DataTables cell: label/value lines; an empty value shows as a dash. --}}
<dl class="grid gap-0.5 text-sm">
    @foreach ($rows as $label => $value)
        <div class="flex gap-1.5"><dt class="text-muted-foreground">{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
    @endforeach
</dl>
