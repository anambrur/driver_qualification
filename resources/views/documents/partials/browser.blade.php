{{--
    Folder browser: tree, breadcrumb, the open folder's documents and the folder and upload dialogs.
    Needs $browser (FolderBrowser), optional $tabs (the page's x-ui.tabs id, so a refused form reopens
    the Documents tab), $empty (text when the entity has no folder yet) and $virtual (the entity's
    typed compliance documents: list of [label, detail, url], shown in its standard folder).
--}}
@php
    $current = $browser['current'];
    $user = auth()->user();
    $canManage = $user->can('folders.manage');
    $canUpload = $user->can('documents.upload');
    $atMaxDepth = count($browser['breadcrumb']) >= \App\Models\Folder::MAX_DEPTH;
    $rootParent = $browser['tree']->first(fn ($folder) => $folder->parent_id === null && $folder->isSystem());
    $moveTargets = collect($browser['paths'])->except($current?->id)->all();
    $virtual ??= [];
@endphp

<div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
    <x-ui.card title="Folders" flush>
        @if ($canManage && ($browser['binding'] === null || $rootParent !== null))
            <x-slot:action>
                <x-ui.button size="sm" variant="outline" icon="folder-plus" data-folder-new="{{ $rootParent?->id }}">New</x-ui.button>
            </x-slot:action>
        @endif
        <nav aria-label="Folders" class="px-3 pb-4">
            @if ($browser['tree']->isEmpty())
                <p class="px-2 text-sm text-muted-foreground">No folders yet.</p>
            @else
                @include('documents.partials.tree', ['parentKey' => 0])
            @endif
        </nav>
    </x-ui.card>

    <div class="min-w-0 space-y-6">
        @if ($current === null)
            <x-ui.empty-state icon="folder-open" :title="$empty ?? 'No folders yet'" :description="isset($empty) ? null : 'Create a folder to start filing documents.'">
                @if ($canManage && $browser['binding'] === null)
                    <x-ui.button size="sm" icon="folder-plus" data-folder-new="">New folder</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <x-ui.breadcrumb :items="collect($browser['breadcrumb'])->map(fn ($folder) => ['label' => $folder->label(), 'href' => \App\Services\Documents\FolderBrowser::url($folder)])->all()" />
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        {{ $current->label() }}
                        @if ($current->isSystem())
                            <x-ui.badge variant="secondary">Standard folder</x-ui.badge>
                        @endif
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canManage && ! $atMaxDepth)
                        <x-ui.button size="sm" variant="outline" icon="folder-plus" data-folder-new="{{ $current->id }}">New subfolder</x-ui.button>
                    @endif
                    @if ($canManage && ! $current->isSystem())
                        <x-ui.button size="sm" variant="outline" icon="pencil" data-dialog-open="folder-rename-dialog">Rename</x-ui.button>
                        <form action="{{ route('documents.folders.destroy', $current) }}" method="POST" data-confirm="Delete the folder “{{ $current->name }}”?" data-confirm-text="Only an empty folder can be deleted." data-confirm-button="Delete">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" size="sm" variant="outline" icon="trash-2" class="text-destructive">Delete</x-ui.button>
                        </form>
                    @endif
                    @if ($canUpload)
                        <x-ui.button size="sm" icon="upload" data-dialog-open="document-upload-dialog">Upload</x-ui.button>
                    @endif
                </div>
            </div>

            @if ($current->isSystem() && $virtual !== [])
                <x-ui.card title="Compliance documents" description="Kept on the compliance pages. Shown here read-only." flush>
                    <x-ui.table>
                        <tbody>
                            @foreach ($virtual as $entry)
                                <tr>
                                    <td class="pl-6" data-primary>
                                        <span class="flex items-center gap-3">
                                            {{ svg('lucide-lock', 'size-4 shrink-0 text-muted-foreground', ['aria-hidden' => 'true']) }}
                                            <span class="font-medium">{{ $entry['label'] }}</span>
                                        </span>
                                    </td>
                                    <td class="text-muted-foreground" data-label="Dates">{{ $entry['detail'] }}</td>
                                    <td class="pr-6 text-right">
                                        <a href="{{ $entry['url'] }}" target="_blank" rel="noopener" class="btn" data-variant="ghost" data-size="sm">
                                            {{ svg('lucide-eye', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </x-ui.card>
            @endif

            <x-ui.datatable id="folder-documents" title="Documents">
                <th>Document</th>
                <th>Type</th>
                <th>Dates</th>
                <th>Size</th>
                <th>Uploaded</th>
                <th class="text-right"><span class="sr-only">Actions</span></th>

                <x-slot:empty>
                    <x-ui.empty-state icon="files" title="No documents in this folder" class="border-0">
                        @if ($canUpload)
                            <x-ui.button size="sm" icon="upload" data-dialog-open="document-upload-dialog">Upload</x-ui.button>
                        @endif
                    </x-ui.empty-state>
                </x-slot:empty>
            </x-ui.datatable>
        @endif
    </div>
</div>

@if ($canManage)
    <x-ui.dialog id="folder-new-dialog" title="New folder" icon="folder-plus" size="sm">
        <form id="folder-new-form" action="{{ route('documents.folders.store') }}" method="POST">
            @csrf
            <input type="hidden" name="_dialog" value="folder-new">
            <input type="hidden" name="parent_id" value="{{ old('parent_id') }}">
            <x-ui.field label="Name" name="name" for="folder-new-name" required>
                <x-ui.input name="name" id="folder-new-name" maxlength="120" required autocomplete="off" />
            </x-ui.field>
        </form>
        <x-slot:footer>
            <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
            <button type="submit" form="folder-new-form" class="btn">{{ svg('lucide-check', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Create folder</button>
        </x-slot:footer>
    </x-ui.dialog>

    @if ($current !== null && ! $current->isSystem())
        <x-ui.dialog id="folder-rename-dialog" title="Rename folder" icon="pencil" size="sm">
            <form id="folder-rename-form" action="{{ route('documents.folders.update', $current) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="_dialog" value="folder-rename">
                <x-ui.field label="Name" name="name" for="folder-rename-name" required>
                    <x-ui.input name="name" id="folder-rename-name" maxlength="120" required autocomplete="off" :value="$current->name" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
                <button type="submit" form="folder-rename-form" class="btn">{{ svg('lucide-check', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Save</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endif
@endif

@if ($canUpload && $current !== null)
    <x-ui.dialog id="document-upload-dialog" title="Upload documents" :description="'Into '.$current->label().'.'" icon="upload" size="lg" :close-on-overlay="false">
        <form id="document-upload-form" action="{{ route('documents.store', $current) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_dialog" value="document-upload">
            @if (old('_dialog') === 'document-upload' && $errors->any())
                <x-ui.alert tone="danger" class="mb-4">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Files" name="files" for="document-upload-files" required class="sm:col-span-2"
                    :hint="'PDF, JPG, PNG, WebP, Word, Excel, CSV or text. Up to '.\App\Http\Requests\Documents\UploadDocumentsRequest::MAX_FILES.' files, 20 MB each.'">
                    <x-ui.input type="file" name="files[]" id="document-upload-files" multiple required
                        accept="{{ collect(\App\Http\Requests\Documents\UploadDocumentsRequest::TYPES)->map(fn ($type) => '.'.$type)->implode(',') }}" />
                </x-ui.field>
                <x-ui.field label="Title" name="title" for="document-upload-title" hint="Optional, for a single file. Otherwise the file name is used." class="sm:col-span-2">
                    <x-ui.input name="title" id="document-upload-title" maxlength="255" autocomplete="off" />
                </x-ui.field>
                <x-ui.field label="Document type" name="document_type_id" for="document-upload-type" class="sm:col-span-2">
                    <x-ui.select name="document_type_id" id="document-upload-type" :options="$browser['documentTypes']->pluck('name', 'id')->all()" placeholder="No type" />
                </x-ui.field>
                <x-ui.field label="Issued on" name="issued_on" for="document-upload-issued">
                    <x-ui.input type="date" name="issued_on" id="document-upload-issued" />
                </x-ui.field>
                <x-ui.field label="Expires on" name="expires_on" for="document-upload-expires">
                    <x-ui.input type="date" name="expires_on" id="document-upload-expires" />
                </x-ui.field>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
            <button type="submit" form="document-upload-form" class="btn">{{ svg('lucide-upload', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Upload</button>
        </x-slot:footer>
    </x-ui.dialog>

    @if ($moveTargets !== [])
        <x-ui.dialog id="document-move-dialog" title="Move document" icon="folder-input" size="sm">
            <form id="document-move-form" action="" method="POST">
                @csrf
                @method('PATCH')
                <p class="mb-4 text-sm text-muted-foreground" data-move-name></p>
                <x-ui.field label="Folder" name="folder_id" for="document-move-folder" required>
                    <x-ui.select name="folder_id" id="document-move-folder" :options="$moveTargets" required />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
                <button type="submit" form="document-move-form" class="btn">{{ svg('lucide-check', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Move</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endif
@endif

@push('scripts')
    <script type="module">
        const reopen = @json(old('_dialog'));
        const tabs = @json($tabs ?? null);

        document.addEventListener('click', (event) => {
            const newFolder = event.target.closest('[data-folder-new]');
            if (newFolder) {
                const form = document.getElementById('folder-new-form');
                form.elements.parent_id.value = newFolder.dataset.folderNew;
                document.getElementById('folder-new-dialog').showModal();
            }

            const move = event.target.closest('[data-move-document]');
            const form = document.getElementById('document-move-form');
            if (move && form) {
                form.action = move.dataset.moveDocument;
                form.querySelector('[data-move-name]').textContent = move.dataset.name;
                document.getElementById('document-move-dialog').showModal();
            }
        });

        @if ($current !== null)
            window.dqTable('#folder-documents', {
                ajax: @json(route('documents.folders.documents', $current)),
                primary: 0,
                order: [[4, 'desc']],
                columns: [
                    { data: 'document', name: 'document' },
                    { data: 'type', orderable: false, searchable: false },
                    { data: 'dates', orderable: false, searchable: false },
                    { data: 'size', orderable: false, searchable: false },
                    { data: 'uploaded', name: 'uploaded', searchable: false },
                    { data: 'action', orderable: false, searchable: false, className: 'text-right' },
                ],
            });
        @endif

        // A refused form comes back to this folder: show the Documents tab and the dialog again.
        if (reopen) {
            if (tabs) document.getElementById(`${tabs}-tab-documents`)?.click();
            document.getElementById(`${reopen}-dialog`)?.showModal();
        }
    </script>
@endpush
