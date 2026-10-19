{{--
    Driver compliance document upload. Pages open it with openUploadModal(driverId, documentTypeId)
    (driver detail, compliance > drivers) and may call closeUploadModal().
--}}
<x-ui.dialog id="uploadDocumentModal" title="Upload document" description="Driver document upload" icon="upload" size="xl" :close-on-overlay="false">
    <form id="uploadDocumentForm" enctype="multipart/form-data" class="grid gap-4 lg:grid-cols-2" novalidate>
        <input type="hidden" name="document_type_id">
        <input type="hidden" name="asset_type" value="driver">
        <input type="hidden" name="asset_id">
        <input type="hidden" name="company_id">

        <div class="grid content-start gap-4">
            <x-ui.field label="Driver" name="selected_asset" for="upload_selected_asset">
                <select id="upload_selected_asset" name="selected_asset" class="select w-full">
                    <option value="">Loading…</option>
                </select>
            </x-ui.field>
            <label for="upload_to_all" class="label inline-flex items-start gap-3 font-normal">
                <input type="checkbox" name="upload_to_all" value="1" id="upload_to_all" class="input mt-0.5">
                <span class="font-medium text-foreground">Upload to all active drivers</span>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field label="File date" name="file_date" for="upload_file_date">
                    <x-ui.input type="date" id="upload_file_date" name="file_date" />
                </x-ui.field>
                <x-ui.field label="Expiration date" name="expiry_date" for="upload_expiry_date">
                    <x-ui.input type="date" id="upload_expiry_date" name="expiry_date" min="{{ date('Y-m-d') }}" />
                </x-ui.field>
            </div>
            <x-ui.field label="Description" name="description" for="upload_description">
                <x-ui.textarea id="upload_description" name="description" rows="2" maxlength="500" class="w-full" placeholder="Optional notes…" />
            </x-ui.field>
        </div>

        <div class="grid content-start gap-3">
            <span class="label">File <span class="text-destructive" aria-hidden="true">*</span></span>
            <label for="upload_file" data-dropzone
                class="flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border p-6 text-center transition-colors hover:bg-muted/50 data-[over]:border-primary data-[over]:bg-primary/5">
                {{ svg('lucide-cloud-upload', 'size-8 text-muted-foreground', ['aria-hidden' => 'true']) }}
                <span class="text-sm font-medium">Drop a file here or click to browse</span>
                <span class="text-xs text-muted-foreground">JPG, PNG or PDF, up to 20 MB</span>
                <input type="file" id="upload_file" name="file" accept=".jpg,.jpeg,.png,.pdf" required class="sr-only">
            </label>
            <div data-file-preview class="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2" hidden>
                <div class="flex min-w-0 items-center gap-2">
                    {{ svg('lucide-file-text', 'size-5 shrink-0 text-muted-foreground', ['aria-hidden' => 'true']) }}
                    <div class="min-w-0">
                        <p data-file-name class="truncate text-sm font-medium"></p>
                        <p data-file-size class="text-xs text-muted-foreground"></p>
                    </div>
                </div>
                <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" aria-label="Remove file" data-upload-action="remove-file">
                    {{ svg('lucide-x', '', ['aria-hidden' => 'true']) }}
                </button>
            </div>
            <x-ui.alert tone="danger" id="uploadError" hidden><span id="uploadErrorText"></span></x-ui.alert>
        </div>
    </form>
    <x-slot:footer>
        <button type="button" class="btn" data-variant="outline" data-upload-action="close">Cancel</button>
        <button type="submit" form="uploadDocumentForm" id="uploadSubmitBtn" class="btn">
            {{ svg('lucide-upload', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Upload document
        </button>
    </x-slot:footer>
</x-ui.dialog>

<script>
    (() => {
        const dialog = document.getElementById('uploadDocumentModal');
        const form = document.getElementById('uploadDocumentForm');
        const select = form.elements.selected_asset;
        const uploadAll = form.elements.upload_to_all;
        const fileInput = form.elements.file;
        const dropzone = form.querySelector('[data-dropzone]');
        const preview = form.querySelector('[data-file-preview]');
        const submit = document.getElementById('uploadSubmitBtn');
        const subtitle = dialog.querySelector('header p');
        const allLabel = uploadAll.closest('label').querySelector('.font-medium');
        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        let driverId = null;

        const showError = (message) => {
            document.getElementById('uploadErrorText').textContent = message;
            document.getElementById('uploadError').hidden = !message;
        };
        const size = (bytes) => bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

        window.showUploadError = showError;

        window.removeFile = () => {
            fileInput.value = '';
            preview.hidden = true;
            dropzone.hidden = false;
        };

        window.closeUploadModal = () => {
            dialog.close();
            form.reset();
            window.removeFile();
            showError('');
            select.disabled = false;
        };

        dialog.addEventListener('click', (event) => {
            const action = event.target.closest('[data-upload-action]')?.dataset.uploadAction;
            if (action === 'remove-file') window.removeFile();
            if (action === 'close') window.closeUploadModal();
        });

        window.openUploadModal = (id, documentTypeId, assetType = 'driver') => {
            driverId = id;
            form.reset();
            window.removeFile();
            showError('');
            form.elements.document_type_id.value = documentTypeId;
            form.elements.asset_type.value = assetType;
            form.elements.asset_id.value = id;
            select.replaceChildren(new Option('Loading…', ''));
            select.disabled = false;
            dialog.showModal();
            loadDrivers(documentTypeId);
        };

        async function loadDrivers(documentTypeId) {
            const params = new URLSearchParams({ document_type_id: documentTypeId, driver_id: driverId ?? '' });
            try {
                const response = await fetch(`{{ route('admin.compliance.drivers.list') }}?${params}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Could not load the drivers.');

                const name = data.document_type_name || 'Document';
                subtitle.textContent = `${name} for a driver`;
                // "Upload to all" covers the active drivers of one company
                form.elements.company_id.value = data.company ? data.company.id : '';
                uploadAll.disabled = !data.company;
                allLabel.textContent = data.company ? `Upload to all active drivers of ${data.company.name}` : 'Upload to all active drivers';
                // Options as text: driver names come from public applicants
                select.replaceChildren(...data.assets.map((asset) => new Option(
                    asset.full_name + (asset.has_document ? ' (has document)' : ' (missing)'), asset.id, false, String(asset.id) === String(driverId),
                )));
            } catch (error) {
                showError(error.message);
            }
        }

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) return;
            if (file.size > 20 * 1024 * 1024) return showError('The file must be 20 MB or smaller.'), window.removeFile();
            if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type)) return showError('Only JPG, PNG and PDF files are allowed.'), window.removeFile();
            form.querySelector('[data-file-name]').textContent = file.name;
            form.querySelector('[data-file-size]').textContent = size(file.size);
            dropzone.hidden = true;
            preview.hidden = false;
            showError('');
        });

        ['dragenter', 'dragover'].forEach((type) => dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            dropzone.dataset.over = '';
        }));
        ['dragleave', 'drop'].forEach((type) => dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            delete dropzone.dataset.over;
        }));
        dropzone.addEventListener('drop', (event) => {
            if (!event.dataTransfer.files.length) return;
            fileInput.files = event.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        });

        uploadAll.addEventListener('change', () => { select.disabled = uploadAll.checked; });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!fileInput.files[0]) return showError('Choose a file to upload.');

            submit.disabled = true;
            showError('');
            try {
                const response = await fetch(@json(route('admin.compliance.driver.documents.upload')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                    body: new FormData(form),
                });
                const data = await response.json().catch(() => ({}));
                // A 422 carries the reason (an expired date, no active drivers…): show it
                if (!response.ok || !data.success) throw new Error(data.message || 'The document could not be uploaded. Please try again.');

                window.closeUploadModal();
                window.notify('success', data.message || 'Document uploaded');
                setTimeout(() => location.reload(), 1200);
            } catch (error) {
                showError(error.message);
            } finally {
                submit.disabled = false;
            }
        });
    })();
</script>
