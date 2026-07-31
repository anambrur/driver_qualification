<!-- Driver Document Upload Modal (compact, wider layout) -->
<div id="uploadDocumentModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog"
    aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 py-4">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500/75" aria-hidden="true" onclick="closeUploadModal()"></div>

        <!-- Modal panel -->
        <div
            class="relative w-full max-w-4xl overflow-hidden text-left bg-white rounded-xl shadow-xl dark:bg-gray-800">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center min-w-0">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-brand-50 dark:bg-brand-900/20 shrink-0">
                        <i class="text-sm text-brand-600 fas fa-upload dark:text-brand-400"></i>
                    </div>
                    <div class="ml-2.5 min-w-0">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate" id="upload-modal-title">
                            Upload Document
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate" id="upload-modal-subtitle">
                            Driver Document Upload
                        </p>
                    </div>
                </div>
                <button type="button" onclick="closeUploadModal()"
                    class="ml-3 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 shrink-0">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <form id="uploadDocumentForm" enctype="multipart/form-data" class="px-5 py-4">
                <!-- Hidden Fields -->
                <input type="hidden" id="upload_document_type_id" name="document_type_id">
                <input type="hidden" id="upload_asset_type" name="asset_type" value="driver">
                <input type="hidden" id="upload_asset_id" name="asset_id">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Left column -->
                    <div class="space-y-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400" id="upload-info-text">
                            Upload driver compliance documents. Select a driver or apply to all.
                        </p>

                        <!-- Select Drivers -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                <span id="select-asset-label">Select Drivers</span>
                            </label>
                            <select id="upload_selected_asset" name="selected_asset"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="">Loading...</option>
                            </select>
                        </div>

                        <!-- Upload to All Checkbox -->
                        <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                            <input type="checkbox" id="upload_to_all" name="upload_to_all"
                                class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300" id="upload-all-label">
                                Upload to All Drivers
                            </span>
                        </label>

                        <!-- Dates side by side -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="upload_file_date"
                                    class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                    File Date
                                </label>
                                <input type="date" id="upload_file_date" name="file_date"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div>
                                <label for="upload_expiry_date"
                                    class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Expiration Date
                                </label>
                                <input type="date" id="upload_expiry_date" name="expiry_date"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    min="{{ date('Y-m-d') }}">
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="upload_description"
                                class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                Description
                            </label>
                            <textarea id="upload_description" name="description" rows="2"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white resize-none"
                                placeholder="Optional notes..."></textarea>
                        </div>
                    </div>

                    <!-- Right column: file upload -->
                    <div class="space-y-3">
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                Upload File <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="file" id="upload_file" name="file" accept=".jpg,.jpeg,.png,.pdf" required
                                    class="hidden">
                                <div id="dropzone"
                                    class="flex flex-col items-center justify-center w-full px-4 py-6 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer hover:border-brand-400 dark:border-gray-600 dark:hover:border-brand-500 transition-colors min-h-[140px]"
                                    onclick="document.getElementById('upload_file').click()">
                                    <i class="mb-2 text-2xl text-gray-400 fas fa-cloud-upload-alt"></i>
                                    <p class="mb-0.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Drop file here or click to browse
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        JPG, PNG, PDF up to 20MB
                                    </p>
                                </div>

                                <!-- File Preview -->
                                <div id="filePreview" class="hidden">
                                    <div
                                        class="flex items-center justify-between p-3 border border-gray-200 rounded-lg dark:border-gray-700 min-h-[140px]">
                                        <div class="flex items-center min-w-0">
                                            <i class="mr-3 text-xl text-brand-600 fas fa-file-alt dark:text-brand-400 shrink-0"></i>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate" id="fileName">
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400" id="fileSize"></p>
                                            </div>
                                        </div>
                                        <button type="button" onclick="removeFile()"
                                            class="ml-2 text-red-500 hover:text-red-700 shrink-0">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Error Display -->
                        <div id="uploadError"
                            class="hidden p-2.5 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/10 dark:border-red-800">
                            <div class="flex items-start">
                                <i class="mt-0.5 mr-2 text-red-500 fas fa-exclamation-circle text-sm"></i>
                                <p class="text-xs text-red-800 dark:text-red-300" id="uploadErrorText"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end pt-3 mt-4 space-x-2 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeUploadModal()"
                        class="px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" id="uploadSubmitBtn"
                        class="px-3.5 py-2 text-sm font-medium text-white bg-brand-600 border border-transparent rounded-lg hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="mr-1.5 fas fa-upload"></i>Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let uploadModalData = {
        driverId: null,
        documentTypeId: null,
        documentTypeName: ''
    };

    // Open upload modal
    function openUploadModal(driverId, documentTypeId, assetType = 'driver') {
        uploadModalData = {
            driverId,
            documentTypeId,
            documentTypeName: ''
        };

        // Set hidden fields
        document.getElementById('upload_document_type_id').value = documentTypeId;
        document.getElementById('upload_asset_type').value = assetType;
        document.getElementById('upload_asset_id').value = driverId;

        // Load drivers for dropdown
        loadDriversForUpload(documentTypeId);

        // Show modal
        document.getElementById('uploadDocumentModal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    // Load drivers for the dropdown
    function loadDriversForUpload(documentTypeId) {
        const endpoint = `/admin/compliance/drivers/list?document_type_id=${documentTypeId}`;

        fetch(endpoint)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('upload_selected_asset');
                const label = document.getElementById('select-asset-label');
                const uploadAllLabel = document.getElementById('upload-all-label');
                const subtitle = document.getElementById('upload-modal-subtitle');
                const infoText = document.getElementById('upload-info-text');

                // Update labels
                label.textContent = 'Select Drivers';
                uploadAllLabel.textContent = 'Upload to All Drivers';

                if (data.success) {
                    const documentTypeName = data.document_type_name || 'Document';
                    uploadModalData.documentTypeName = documentTypeName;

                    subtitle.textContent = `${documentTypeName} for Driver`;
                    infoText.textContent =
                        `Upload ${documentTypeName}. Select a driver or apply to all.`;

                    // Populate dropdown
                    select.innerHTML = data.assets.map(asset =>
                        `<option value="${asset.id}" ${asset.id == uploadModalData.driverId ? 'selected' : ''}>
                        ${asset.full_name}${asset.has_document ? ' (Has Document)' : ' (Missing)'}
                    </option>`
                    ).join('');
                } else {
                    showUploadError('Failed to load drivers');
                }
            })
            .catch(error => {
                showUploadError('Error loading drivers: ' + error.message);
            });
    }

    // Close upload modal
    function closeUploadModal() {
        document.getElementById('uploadDocumentModal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');

        // Reset form
        document.getElementById('uploadDocumentForm').reset();
        removeFile();
        hideUploadError();

        const select = document.getElementById('upload_selected_asset');
        if (select) {
            select.disabled = false;
            select.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    // Handle file selection
    document.getElementById('upload_file')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file size (20MB)
            if (file.size > 20 * 1024 * 1024) {
                showUploadError('File size must be less than 20MB');
                this.value = '';
                return;
            }

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                showUploadError('Only JPG, PNG, and PDF files are allowed');
                this.value = '';
                return;
            }

            // Show file preview
            document.getElementById('dropzone').classList.add('hidden');
            document.getElementById('filePreview').classList.remove('hidden');
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            hideUploadError();
        }
    });

    // Remove selected file
    function removeFile() {
        document.getElementById('upload_file').value = '';
        document.getElementById('dropzone').classList.remove('hidden');
        document.getElementById('filePreview').classList.add('hidden');
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Drag and drop functionality
    const dropzone = document.getElementById('dropzone');

    dropzone?.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('border-brand-500', 'bg-brand-50', 'dark:bg-brand-900/10');
    });

    dropzone?.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropzone.classList.remove('border-brand-500', 'bg-brand-50', 'dark:bg-brand-900/10');
    });

    dropzone?.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('border-brand-500', 'bg-brand-50', 'dark:bg-brand-900/10');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('upload_file').files = files;
            document.getElementById('upload_file').dispatchEvent(new Event('change'));
        }
    });

    // Toggle upload to all checkbox
    document.getElementById('upload_to_all')?.addEventListener('change', function() {
        const select = document.getElementById('upload_selected_asset');
        select.disabled = this.checked;
        if (this.checked) {
            select.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            select.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    });

    // Handle form submission
    document.getElementById('uploadDocumentForm')?.addEventListener('submit', function(e) {
        e.preventDefault();

        const submitBtn = document.getElementById('uploadSubmitBtn');
        const formData = new FormData(this);

        // Validate
        if (!document.getElementById('upload_file').files[0]) {
            showUploadError('Please select a file to upload');
            return;
        }

        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="mr-1.5 fas fa-spinner fa-spin"></i>Uploading...';
        hideUploadError();

        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            showUploadError('Security token not found. Please refresh the page.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="mr-1.5 fas fa-upload"></i>Upload Document';
            return;
        }

        // Submit form
        fetch('/admin/compliance/driver-documents/upload', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Document uploaded successfully', 'success');
                    closeUploadModal();

                    // Refresh the page after a short delay
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showUploadError(data.message || 'Failed to upload document');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="mr-1.5 fas fa-upload"></i>Upload Document';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showUploadError('Error uploading document: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="mr-1.5 fas fa-upload"></i>Upload Document';
            });
    });

    // Show upload error
    function showUploadError(message) {
        const errorDiv = document.getElementById('uploadError');
        const errorText = document.getElementById('uploadErrorText');
        errorText.textContent = message;
        errorDiv.classList.remove('hidden');
    }

    // Hide upload error
    function hideUploadError() {
        document.getElementById('uploadError').classList.add('hidden');
    }
</script>
