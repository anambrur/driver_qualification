{{-- resources/views/admin/maintenance-schedule/partials/form-modal.blade.php --}}
<div id="scheduleModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div
            class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full dark:bg-gray-800">
            <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="w-full mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 id="modalTitle" class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            Create New Schedule
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Set up maintenance schedule for your fleet assets
                        </p>

                        <div class="mt-4">
                            <form id="scheduleForm">
                                @csrf
                                <input type="hidden" id="schedule_id" name="id">

                                <!-- Section 1: Basic Information -->
                                <div class="mb-6">
                                    <h4
                                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                        <i class="fas fa-info-circle mr-2"></i>1. Basic Information
                                    </h4>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <!-- Maintenance Category -->
                                        <div class="md:col-span-2">
                                            <label for="maintenance_category_id"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Maintenance Category <span class="text-red-500">*</span>
                                            </label>
                                            <select name="maintenance_category_id" id="maintenance_category_id" required
                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                <option value="">Select Category</option>
                                            </select>
                                            <div id="maintenance_category_id_error" class="mt-1 text-sm text-red-600">
                                            </div>
                                        </div>

                                        <!-- Title (Optional) -->
                                        <div class="md:col-span-2">
                                            <label for="title"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Schedule Title (Optional)
                                            </label>
                                            <input type="text" name="title" id="title"
                                                placeholder="e.g., 180 Day Inspection"
                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <div id="title_error" class="mt-1 text-sm text-red-600"></div>
                                        </div>

                                        <!-- Vehicle Assignment -->
                                        <div class="md:col-span-2">
                                            <label for="vehicle_id"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Assign to Vehicle
                                            </label>
                                            <select name="vehicle_id" id="vehicle_id"
                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                <option value="">All Vehicles (Global Schedule)</option>
                                            </select>
                                            <p class="text-xs text-gray-500 mt-1">Leave empty for fleet-wide schedule
                                            </p>
                                            <div id="vehicle_id_error" class="mt-1 text-sm text-red-600"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Schedule Type -->
                                <div class="mb-6">
                                    <h4
                                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                        <i class="fas fa-clock mr-2"></i>2. Schedule Type
                                    </h4>
                                    <p class="text-xs text-gray-500 mb-3">Choose how maintenance should repeat</p>

                                    <!-- Schedule Type Selection -->
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-4">
                                        <!-- By Date Option -->
                                        <div id="type-date-option"
                                            class="schedule-type-option p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-brand-300 dark:border-gray-700 dark:hover:border-brand-700"
                                            onclick="selectScheduleType('date')">
                                            <div class="flex items-center mb-2">
                                                <div
                                                    class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center mr-2">
                                                    <div id="type-date-radio"
                                                        class="w-2.5 h-2.5 rounded-full hidden bg-brand-600"></div>
                                                </div>
                                                <span class="font-medium text-gray-900 dark:text-white">By Date</span>
                                            </div>
                                            <p class="text-xs text-gray-500">Calendar intervals</p>
                                            <p class="text-xs text-gray-400 mt-1">e.g., Every 30 days</p>
                                        </div>

                                        <!-- By Mileage Option -->
                                        <div id="type-mileage-option"
                                            class="schedule-type-option p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-brand-300 dark:border-gray-700 dark:hover:border-brand-700"
                                            onclick="selectScheduleType('mileage')">
                                            <div class="flex items-center mb-2">
                                                <div
                                                    class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center mr-2">
                                                    <div id="type-mileage-radio"
                                                        class="w-2.5 h-2.5 rounded-full hidden bg-brand-600"></div>
                                                </div>
                                                <span class="font-medium text-gray-900 dark:text-white">By
                                                    Mileage</span>
                                            </div>
                                            <p class="text-xs text-gray-500">Odometer readings</p>
                                            <p class="text-xs text-gray-400 mt-1">e.g., Every 10K mi</p>
                                        </div>

                                        <!-- By Engine Hours Option -->
                                        <div id="type-engine-option"
                                            class="schedule-type-option p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-brand-300 dark:border-gray-700 dark:hover:border-brand-700"
                                            onclick="selectScheduleType('engine_hours')">
                                            <div class="flex items-center mb-2">
                                                <div
                                                    class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center mr-2">
                                                    <div id="type-engine-radio"
                                                        class="w-2.5 h-2.5 rounded-full hidden bg-brand-600"></div>
                                                </div>
                                                <span class="font-medium text-gray-900 dark:text-white">By Engine
                                                    Hours</span>
                                            </div>
                                            <p class="text-xs text-gray-500">Engine runtime</p>
                                            <p class="text-xs text-gray-400 mt-1">e.g., Every 250 hrs</p>
                                        </div>
                                    </div>

                                    <input type="hidden" name="schedule_type" id="schedule_type" value="date">

                                    <!-- Dynamic Interval Inputs -->
                                    <div id="date-interval" class="interval-input mt-4">
                                        <label for="interval_days"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Interval (Days) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input type="number" name="interval_days" id="interval_days" min="1"
                                                max="9999"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                placeholder="e.g., 30">
                                            <div
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">days</span>
                                            </div>
                                        </div>
                                        <div id="interval_days_error" class="mt-1 text-sm text-red-600"></div>
                                    </div>

                                    <div id="mileage-interval" class="interval-input hidden mt-4">
                                        <label for="interval_miles"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Interval (Miles) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input type="number" name="interval_miles" id="interval_miles"
                                                min="1" max="999999"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                placeholder="e.g., 10000">
                                            <div
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">miles</span>
                                            </div>
                                        </div>
                                        <div id="interval_miles_error" class="mt-1 text-sm text-red-600"></div>
                                    </div>

                                    <div id="engine-interval" class="interval-input hidden mt-4">
                                        <label for="interval_hours"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Interval (Hours) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input type="number" name="interval_hours" id="interval_hours"
                                                min="1" max="999999"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                placeholder="e.g., 250">
                                            <div
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">hours</span>
                                            </div>
                                        </div>
                                        <div id="interval_hours_error" class="mt-1 text-sm text-red-600"></div>
                                    </div>
                                </div>

                                <!-- Section 3: Additional Details -->
                                <div class="mb-6">
                                    <h4
                                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                        <i class="fas fa-pencil-alt mr-2"></i>3. Additional Details
                                    </h4>
                                    <p class="text-xs text-gray-500 mb-3">Optional notes and instructions</p>

                                    <!-- Description & Notes -->
                                    <div class="space-y-4">
                                        <div>
                                            <label for="description"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Description
                                            </label>
                                            <textarea name="description" id="description" rows="3"
                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                placeholder="Add any special instructions, notes, or details about this maintenance schedule..."></textarea>
                                            <div id="description_error" class="mt-1 text-sm text-red-600"></div>
                                        </div>

                                        <div>
                                            <label for="notes"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Internal Notes
                                            </label>
                                            <textarea name="notes" id="notes" rows="2"
                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                placeholder="Additional notes for managers and technicians..."></textarea>
                                            <div id="notes_error" class="mt-1 text-sm text-red-600"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="mb-4">
                                    <label for="status"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Status
                                    </label>
                                    <select name="status" id="status" required
                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <option value="active">Active</option>
                                        <option value="paused">Paused</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                    <div id="status_error" class="mt-1 text-sm text-red-600"></div>
                                </div>

                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    This information will be visible to technicians and managers.
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700">
                <button type="button" id="submitForm"
                    class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white border border-transparent rounded-md shadow-sm bg-brand-600 hover:bg-brand-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 sm:ml-3 sm:w-auto sm:text-sm">
                    <span id="submitText">Create Schedule</span>
                </button>
                <button type="button" id="closeModal"
                    class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-300 dark:border-gray-500 dark:hover:bg-gray-500">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function selectScheduleType(type) {
            // Update hidden input
            $('#schedule_type').val(type);

            // Update radio indicators
            $('.schedule-type-option .w-2.5.h-2.5').addClass('hidden');
            $(`#type-${type}-radio`).removeClass('hidden');

            // Update selected styles
            $('.schedule-type-option').removeClass('border-brand-500 bg-brand-50 dark:bg-brand-900/20');
            $(`#type-${type}-option`).addClass('border-brand-500 bg-brand-50 dark:bg-brand-900/20');

            // Show/hide interval inputs
            $('.interval-input').addClass('hidden');
            if (type === 'date') {
                $('#date-interval').removeClass('hidden');
            } else if (type === 'mileage') {
                $('#mileage-interval').removeClass('hidden');
            } else if (type === 'engine_hours') {
                $('#engine-interval').removeClass('hidden');
            }
        }
    </script>
@endpush
