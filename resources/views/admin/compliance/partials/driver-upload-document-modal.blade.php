<!-- Driver Document Upload Modal -->
<div id="uploadDocumentModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog"
    aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div>

        <!-- Center modal -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div
            class="inline-block w-full overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl dark:bg-gray-800">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-brand-50 dark:bg-brand-900/20">
                        <i class="text-lg text-brand-600 fas fa-upload dark:text-brand-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="upload-modal-title">
                            Upload Document
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="upload-modal-subtitle">
                            Driver Document Upload
                        </p>
                    </div>
                </div>
                <button type="button" onclick="closeUploadModal()"
                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <form id="uploadDocumentForm" enctype="multipart/form-data" class="px-6 py-5">
                <!-- Info Alert -->
                <div
                    class="flex items-start p-3 mb-5 rounded-lg bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800">
                    <i class="mt-0.5 mr-3 text-blue-500 fas fa-info-circle"></i>
                    <p class="text-sm text-blue-800 dark:text-blue-300" id="upload-info-text">
                        Upload driver compliance documents. You can select multiple drivers or upload to all drivers at
                        once.
                    </p>
                </div>

                <!-- Hidden Fields -->
                <input type="hidden" id="upload_document_type_id" name="document_type_id">
                <input type="hidden" id="upload_asset_type" name="asset_type" value="driver">
                <input type="hidden" id="upload_asset_id" name="asset_id">

                <!-- Select Drivers -->
                <div class="mb-5">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <span id="select-asset-label">Select Drivers</span>
                    </label>
                    <select id="upload_selected_asset" name="selected_asset"
                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="">Loading...</option>
                    </select>
                </div>

                <!-- Upload to All Checkbox -->
                <div class="mb-5">
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" id="upload_to_all" name="upload_to_all"
                            class="w-4 h-4 mt-0.5 text-brand-600 border-gray-300 rounded focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300" id="upload-all-label">
                                Upload to All Drivers
                            </span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                This will override individual driver selection and apply to all drivers in your fleet
                            </p>
                        </div>
                    </label>
                </div>

                <!-- File Date (Optional) -->
                <div class="mb-5">
                    <label for="upload_file_date"
                        class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        File Date (Optional)
                    </label>
                    <input type="date" id="upload_file_date" name="file_date"
                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>

                <!-- Expiration Date (Optional) -->
                <div class="mb-5">
                    <label for="upload_expiry_date"
                        class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Expiration Date (Optional)
                    </label>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">When does this document expire? (Leave
                        blank for non-expiring documents)</p>
                    <input type="date" id="upload_expiry_date" name="expiry_date"
                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        min="{{ date('Y-m-d') }}">
                </div>

                <!-- Description (Optional) -->
                <div class="mb-5">
                    <label for="upload_description"
                        class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Description (Optional)
                    </label>
                    <textarea id="upload_description" name="description" rows="4"
                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white resize-none"
                        placeholder="Add any additional notes about this document..."></textarea>
                </div>

                <!-- Upload File -->
                <div class="mb-5">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Upload File
                    </label>
                    <div class="relative">
                        <input type="file" id="upload_file" name="file" accept=".jpg,.jpeg,.png,.pdf" required
                            class="hidden">
                        <div id="dropzone"
                            class="flex flex-col items-center justify-center w-full px-4 py-8 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer hover:border-brand-400 dark:border-gray-600 dark:hover:border-brand-500 transition-colors"
                            onclick="document.getElementById('upload_file').click()">
                            <i class="mb-3 text-3xl text-gray-400 fas fa-cloud-upload-alt"></i>
                            <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Drop file here or click to browse
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                JPG, PNG, PDF up to 20MB
                            </p>
                        </div>

                        <!-- File Preview -->
                        <div id="filePreview" class="hidden mt-3">
                            <div
                                class="flex items-center justify-between p-3 border border-gray-200 rounded-lg dark:border-gray-700">
                                <div class="flex items-center">
                                    <i class="mr-3 text-2xl text-brand-600 fas fa-file-alt dark:text-brand-400"></i>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white" id="fileName">
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400" id="fileSize"></p>
                                    </div>
                                </div>
                                <button type="button" onclick="removeFile()"
                                    class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Display -->
                <div id="uploadError"
                    class="hidden p-3 mb-4 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/10 dark:border-red-800">
                    <div class="flex items-start">
                        <i class="mt-0.5 mr-3 text-red-500 fas fa-exclamation-circle"></i>
                        <p class="text-sm text-red-800 dark:text-red-300" id="uploadErrorText"></p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end pt-4 space-x-3 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeUploadModal()"
                        class="px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors duration-200 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-gray-500/20 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                        <i class="mr-2 fas fa-times"></i>Cancel
                    </button>
                    <button type="submit" id="uploadSubmitBtn"
                        class="px-4 py-2.5 text-sm font-medium text-white transition-colors duration-200 bg-brand-600 border border-transparent rounded-lg hover:bg-brand-700 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="mr-2 fas fa-upload"></i>Upload Document
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
                        `Upload ${documentTypeName} documents. You can select multiple drivers or upload to all drivers at once.`;

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
        submitBtn.innerHTML = '<i class="mr-2 fas fa-spinner fa-spin"></i>Uploading...';
        hideUploadError();

        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            showUploadError('Security token not found. Please refresh the page.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="mr-2 fas fa-upload"></i>Upload Document';
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
                    submitBtn.innerHTML = '<i class="mr-2 fas fa-upload"></i>Upload Document';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showUploadError('Error uploading document: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="mr-2 fas fa-upload"></i>Upload Document';
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
