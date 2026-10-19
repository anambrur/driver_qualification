<x-ui.card title="Files" description="Uploaded compliance documents for this driver." flush>
    @if ($uploadedDocuments->isEmpty())
        <x-ui.empty-state icon="folder-open" title="No documents uploaded yet" description="Use Complete on the Qualifications tab to upload files." class="mx-6 border-0 p-6!" />
    @else
        <x-ui.table>
            <thead>
                <tr>
                    <th class="pl-6">Document</th>
                    <th>Filed</th>
                    <th>Expires</th>
                    <th>Updated</th>
                    <th class="pr-6 text-right"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($uploadedDocuments as $document)
                    @php
                        $ext = strtolower(pathinfo((string) $document->file?->path, PATHINFO_EXTENSION));
                        $typeName = $document->documentType?->name ?? 'Document';
                    @endphp
                    <tr>
                        <td class="pl-6" data-primary>
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                    {{ svg(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'lucide-file-image' : 'lucide-file-text', 'size-4', ['aria-hidden' => 'true']) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $typeName }}</p>
                                    @if ($document->description)
                                        <p class="truncate text-xs text-muted-foreground">{{ $document->description }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td data-label="Filed">{{ $document->file_date ? \Carbon\Carbon::parse($document->file_date)->format('M d, Y') : '—' }}</td>
                        <td data-label="Expires">
                            @if ($document->expiry_date)
                                {{ \Carbon\Carbon::parse($document->expiry_date)->format('M d, Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Updated">{{ $document->updated_at?->format('M d, Y') ?? '—' }}</td>
                        <td class="pr-6 text-right" data-label="Actions">
                            <div class="inline-flex items-center gap-1">
                                <x-ui.button :href="route('admin.compliance.driver.documents.view', $document->id)" target="_blank" rel="noopener" variant="ghost" size="icon-sm" icon="eye" aria-label="View {{ $typeName }}" title="View" />
                                <x-ui.button :href="route('admin.compliance.driver.documents.download', $document->id)" variant="ghost" size="icon-sm" icon="download" aria-label="Download {{ $typeName }}" title="Download" />
                                @can('drivers.edit')
                                    <x-ui.button variant="ghost" size="icon-sm" icon="trash-2" class="text-destructive" aria-label="Delete {{ $typeName }}" title="Delete"
                                        data-action="delete-document" data-document-id="{{ $document->id }}" data-document-name="{{ $typeName }}" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    @endif
</x-ui.card>
