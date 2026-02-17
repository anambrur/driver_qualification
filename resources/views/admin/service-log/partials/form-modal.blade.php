{{-- resources/views/admin/service-log/partials/form-modal.blade.php --}}
<div id="serviceLogModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div
            class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full dark:bg-gray-800">
            <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="w-full mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 id="modalTitle" class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Create
                            New Service Log</h3>
                        <div class="mt-4">
                            <form id="serviceLogForm" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" id="service_log_id" name="id">

                                <!-- Section 1: Service Information -->
                                <div class="mb-6">
                                    <h4
                                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                        <i class="fas fa-info-circle mr-2"></i>1. Service Information
                                    </h4>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <!-- Service Date -->
                                        <div>
                                            <label for="service_date"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Service Date <span class="text-red-500">*</span>
                                            </label>
                                            <input type="date" name="service_date" id="service_date" required
                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <div id="service_date_error" class="mt-1 text-sm text-red-600"></div>
                                        </div>

                                        <!-- Vehicle -->
                                        <div>
                                            <label for="vehicle_id"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Vehicle <span class="text-red-500">*</span>
                                            </label>
                                            <select name="vehicle_id" id="vehicle_id" required
                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                <option value="">Select Vehicle</option>
                                            </select>
                                            <div id="vehicle_id_error" class="mt-1 text-sm text-red-600"></div>
                                        </div>
                                    </div>

                                    <!-- Maintenance Categories -->
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Maintenance Categories <span class="text-red-500">*</span>
                                        </label>
                                        <div class="text-xs text-gray-500 mb-2">Select all categories that apply to this
                                            service</div>
                                        <div id="categories-container"
                                            class="category-checkbox-group grid grid-cols-2 gap-2 p-3 border border-gray-300 rounded-md dark:border-gray-600">
                                            <!-- Categories will be loaded via AJAX -->
                                        </div>
                                        <div id="categories_error" class="mt-1 text-sm text-red-600"></div>
                                    </div>
                                </div>

                                <!-- Section 2: Service Details -->
                                <div class="mb-6">
                                    <h4
                                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                        <i class="fas fa-wrench mr-2"></i>2. Service Details
                                    </h4>

                                    <!-- Maintenance Notes -->
                                    <div class="mb-4">
                                        <label for="maintenance_notes"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Maintenance Notes
                                        </label>
                                        <div class="text-xs text-gray-500 mb-1">Describe the maintenance work performed,
                                            parts replaced, observations, etc...</div>
                                        <textarea name="maintenance_notes" id="maintenance_notes" rows="3"
                                            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            placeholder="Detailed notes help track service history and future maintenance needs..."></textarea>
                                        <div id="maintenance_notes_error" class="mt-1 text-sm text-red-600"></div>
                                    </div>

                                    <!-- Vehicle Metrics -->
                                    <div class="mb-4">
                                        <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Vehicle
                                            Metrics</h5>
                                        <div class="text-xs text-gray-500 mb-2">Track mileage and engine hours for
                                            maintenance scheduling</div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <!-- Odometer Readings -->
                                            <div>
                                                <label for="odometer_at_service"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Odometer at Service <span class="text-red-500">*</span>
                                                </label>
                                                <div class="text-xs text-gray-500 mb-1">Vehicle mileage when service was
                                                    performed</div>
                                                <input type="number" name="odometer_at_service"
                                                    id="odometer_at_service" required min="0"
                                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                    placeholder="0">
                                                <div id="odometer_at_service_error" class="mt-1 text-sm text-red-600">
                                                </div>
                                            </div>

                                            <div>
                                                <label for="current_odometer"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Current Odometer <span class="text-red-500">*</span>
                                                </label>
                                                <div class="text-xs text-gray-500 mb-1">Current vehicle odometer reading
                                                    (updates vehicle record)</div>
                                                <input type="number" name="current_odometer" id="current_odometer"
                                                    required min="0"
                                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                    placeholder="0">
                                                <div id="current_odometer_error" class="mt-1 text-sm text-red-600">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 mt-3 md:grid-cols-2">
                                            <!-- Engine Hours -->
                                            <div>
                                                <label for="engine_hours_at_service"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Engine Hours at Service
                                                </label>
                                                <div class="text-xs text-gray-500 mb-1">Engine runtime when service was
                                                    performed</div>
                                                <input type="number" name="engine_hours_at_service"
                                                    id="engine_hours_at_service" min="0"
                                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                    placeholder="0 (Optional)">
                                                <div id="engine_hours_at_service_error"
                                                    class="mt-1 text-sm text-red-600"></div>
                                            </div>

                                            <div>
                                                <label for="current_engine_hours"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Current Engine Hours
                                                </label>
                                                <div class="text-xs text-gray-500 mb-1">Current vehicle engine hours
                                                    (updates vehicle record)</div>
                                                <input type="number" name="current_engine_hours"
                                                    id="current_engine_hours" min="0"
                                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                    placeholder="0 (Optional)">
                                                <div id="current_engine_hours_error"
                                                    class="mt-1 text-sm text-red-600"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 3: Service Cost -->
                                <div class="mb-6">
                                    <h4
                                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                        <i class="fas fa-dollar-sign mr-2"></i>3. Service Cost
                                    </h4>

                                    <div class="max-w-md">
                                        <label for="total_cost"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Total Cost <span class="text-red-500">*</span>
                                        </label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div
                                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input type="number" name="total_cost" id="total_cost" required
                                                min="0" step="0.01"
                                                class="block w-full pl-7 pr-12 py-2 border border-gray-300 rounded-md focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                placeholder="0.00">
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">Include parts, labor, taxes, and any
                                            additional fees</div>
                                        <div id="total_cost_error" class="mt-1 text-sm text-red-600"></div>
                                    </div>
                                </div>

                                <!-- Section 4: Documentation -->
                                <div class="mb-6">
                                    <h4
                                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                        <i class="fas fa-file-alt mr-2"></i>4. Documentation
                                    </h4>

                                    <!-- Status -->
                                    <div class="mb-4 max-w-md">
                                        <label for="status"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Status <span class="text-red-500">*</span>
                                        </label>
                                        <select name="status" id="status" required
                                            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <option value="completed">Completed</option>
                                            <option value="pending">Pending</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                        <div id="status_error" class="mt-1 text-sm text-red-600"></div>
                                    </div>

                                    <!-- Existing Documents -->
                                    <div id="existing-documents" class="flex flex-wrap gap-4 mb-4">
                                        <!-- Existing documents will be loaded here -->
                                    </div>

                                    <!-- Document Upload (Simplified like Driver Modal) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Service Documents
                                        </label>
                                        <div class="text-xs text-gray-500 mb-2">Upload receipts, photos, and service
                                            documentation</div>

                                        <!-- Hidden file input -->
                                        <input type="file" id="service_documents" name="documents[]" multiple
                                            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="hidden">

                                        <!-- Dropzone area (custom styled, not Dropzone.js) -->
                                        <div id="service-dropzone"
                                            class="flex flex-col items-center justify-center w-full px-4 py-8 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer hover:border-brand-400 dark:border-gray-600 dark:hover:border-brand-500 transition-colors mb-3"
                                            onclick="document.getElementById('service_documents').click()">
                                            <i class="mb-3 text-3xl text-gray-400 fas fa-cloud-upload-alt"></i>
                                            <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Click to browse or drag and drop
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                PDF, JPG, PNG, DOC, DOCX up to 10MB each
                                            </p>
                                        </div>

                                        <!-- File preview container -->
                                        <div id="service-file-preview-container" class="space-y-2">
                                            <!-- Files will be previewed here -->
                                        </div>

                                        <div id="documents_error" class="mt-1 text-sm text-red-600"></div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700">
                <button type="button" id="submitForm"
                    class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white border border-transparent rounded-md shadow-sm bg-brand-600 hover:bg-brand-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 sm:ml-3 sm:w-auto sm:text-sm">
                    <span id="submitText">Save Service Log</span>
                </button>
                <button type="button" id="closeModal"
                    class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-300 dark:border-gray-500 dark:hover:bg-gray-500">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
