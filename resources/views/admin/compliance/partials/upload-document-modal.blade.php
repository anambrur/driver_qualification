{{--
    Vehicle and trailer compliance document upload (compliance > fleet). The page opens it with
    openUploadModal(assetId, documentTypeId, 'vehicle'|'trailer') and may call closeUploadModal().
--}}
<x-ui.dialog id="uploadDocumentModal" title="Upload document" description="Fleet document upload" icon="upload" size="xl" :close-on-overlay="false">
    <form id="uploadDocumentForm" enctype="multipart/form-data" class="grid gap-4 lg:grid-cols-2" novalidate>
        <input type="hidden" id="upload_document_type_id" name="document_type_id">
        <input type="hidden" id="upload_asset_type" name="asset_type" value="vehicle">
        <input type="hidden" id="upload_asset_id" name="asset_id">
        <input type="hidden" id="upload_company_id" name="company_id">

        <x-ui.alert tone="info" class="lg:col-span-2"><span id="upload-info-text">Upload documents for this document type.</span></x-ui.alert>

        <div class="grid content-start gap-4">
            <div class="field gap-2">
                <label for="upload_selected_asset" class="label" id="select-asset-label">Select Vehicles</label>
                <select id="upload_selected_asset" name="selected_asset" class="select w-full">
                    <option value="">Loading…</option>
                </select>
            </div>
            <label for="upload_to_all" class="label inline-flex items-start gap-3 font-normal">
                <input type="checkbox" name="upload_to_all" value="1" id="upload_to_all" class="input mt-0.5">
                <span>
                    <span class="block font-medium text-foreground" id="upload-all-label">Upload to All Vehicles</span>
                    <span class="block text-xs text-muted-foreground">This will override individual selection and apply to all of this company</span>
                </span>
            </label>
            <x-ui.field label="Expiration date" name="expiry_date" for="upload_expiry_date" hint="When does this document expire?" required>
                <x-ui.input type="date" id="upload_expiry_date" name="expiry_date" min="{{ date('Y-m-d') }}" required />
            </x-ui.field>
            <x-ui.field label="Description" name="description" for="upload_description">
                <x-ui.textarea id="upload_description" name="description" rows="3" class="w-full" placeholder="Add any additional notes about this document…" />
            </x-ui.field>
        </div>

        <div class="grid content-start gap-3">
            <span class="label">File <span class="text-destructive" aria-hidden="true">*</span></span>
            <label for="upload_file" id="dropzone"
                class="flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border p-6 text-center transition-colors hover:bg-muted/50 data-[over]:border-primary data-[over]:bg-primary/5">
                {{ svg('lucide-cloud-upload', 'size-8 text-muted-foreground', ['aria-hidden' => 'true']) }}
                <span class="text-sm font-medium">Drop file here or click to browse</span>
                <span class="text-xs text-muted-foreground">JPG, PNG, PDF up to 20MB</span>
                <input type="file" id="upload_file" name="file" accept=".jpg,.jpeg,.png,.pdf" required class="sr-only">
            </label>
            <div id="filePreview" class="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2" hidden>
                <div class="flex min-w-0 items-center gap-2">
                    {{ svg('lucide-file-text', 'size-5 shrink-0 text-muted-foreground', ['aria-hidden' => 'true']) }}
                    <div class="min-w-0">
                        <p id="fileName" class="truncate text-sm font-medium"></p>
                        <p id="fileSize" class="text-xs text-muted-foreground"></p>
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
            {{ svg('lucide-upload', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Upload Document
        </button>
    </x-slot:footer>
</x-ui.dialog>

<script>
    (() => {
        const dialog = document.getElementById('uploadDocumentModal');
        const form = document.getElementById('uploadDocumentForm');
        const select = document.getElementById('upload_selected_asset');
        const uploadAll = document.getElementById('upload_to_all');
        const fileInput = document.getElementById('upload_file');
        const dropzone = document.getElementById('dropzone');
        const preview = document.getElementById('filePreview');
        const submit = document.getElementById('uploadSubmitBtn');
        const subtitle = dialog.querySelector('header p');
        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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

        window.openUploadModal = (assetId, documentTypeId, assetType = 'vehicle') => {
            form.reset();
            window.removeFile();
            showError('');
            document.getElementById('upload_document_type_id').value = documentTypeId;
            document.getElementById('upload_asset_type').value = assetType;
            document.getElementById('upload_asset_id').value = assetId;
            select.replaceChildren(new Option('Loading…', ''));
            select.disabled = false;
            dialog.showModal();
            loadAssets(assetType, assetId, documentTypeId);
        };

        async function loadAssets(assetType, selectedAssetId, documentTypeId) {
            const label = assetType === 'vehicle' ? 'Vehicle' : 'Trailer';
            const plural = assetType === 'vehicle' ? 'Vehicles' : 'Trailers';
            const params = new URLSearchParams({ document_type_id: documentTypeId, asset_id: selectedAssetId ?? '' });
            const endpoint = assetType === 'vehicle' ? '/admin/compliance/vehicles/list' : '/admin/compliance/trailers/list';
            document.getElementById('select-asset-label').textContent = `Select ${plural}`;
            document.getElementById('upload-all-label').textContent = `Upload to All ${plural}`;

            try {
                const response = await fetch(`${endpoint}?${params}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Failed to load assets');

                const name = data.document_type_name || 'Document';
                const selected = data.assets.find((asset) => String(asset.id) === String(selectedAssetId));
                subtitle.textContent = `${name} for ${label} #${selected ? selected.unit_no : selectedAssetId}`;
                document.getElementById('upload-info-text').textContent =
                    `Upload documents for ${name}. You can select multiple ${plural.toLowerCase()} or upload to all ${plural.toLowerCase()} at once.`;
                // "Upload to all" covers the assets of one company (PO-02)
                document.getElementById('upload_company_id').value = data.company ? data.company.id : '';
                uploadAll.disabled = !data.company;
                if (data.company) document.getElementById('upload-all-label').textContent = `Upload to All ${plural} of ${data.company.name}`;
                // Options as text: unit numbers come from tenant users
                select.replaceChildren(...data.assets.map((asset) => new Option(
                    label + ' #' + asset.unit_no + (asset.has_document ? ' (Has Document)' : ' (Missing)'), asset.id, false, String(asset.id) === String(selectedAssetId),
                )));
            } catch (error) {
                showError('Error loading assets: ' + error.message);
            }
        }

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) return;
            if (file.size > 20 * 1024 * 1024) return showError('File size must be less than 20MB'), window.removeFile();
            if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type)) return showError('Only JPG, PNG, and PDF files are allowed'), window.removeFile();
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = size(file.size);
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
            if (!fileInput.files[0]) return showError('Please select a file to upload');
            if (!document.getElementById('upload_expiry_date').value) return showError('Please enter an expiration date');

            submit.disabled = true;
            showError('');
            try {
                const response = await fetch('/admin/compliance/documents/upload', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                    body: new FormData(form),
                });
                const data = await response.json().catch(() => ({}));
                // Validation errors (422) carry a message to show
                if (!response.ok || !data.success) throw new Error(data.message || 'Failed to upload document');

                window.closeUploadModal();
                window.notify('success', data.message || 'Document uploaded successfully');
                setTimeout(() => location.reload(), 1500);
            } catch (error) {
                showError(error.message);
            } finally {
                submit.disabled = false;
            }
        });
    })();
</script>
