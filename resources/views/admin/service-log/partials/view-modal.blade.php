{{-- resources/views/admin/service-log/partials/view-modal.blade.php --}}
<div id="viewServiceLogModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div
            class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full dark:bg-gray-800">
            <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="w-full mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            <i class="fas fa-clipboard-list mr-2"></i>Service Log Details
                        </h3>

                        <div class="mt-6 space-y-4">
                            <!-- Service Information -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Service
                                    Information</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Vehicle</div>
                                        <div id="view_vehicle"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Service Date</div>
                                        <div id="view_service_date"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Categories</div>
                                        <div id="view_categories" class="flex flex-wrap gap-2 mt-1"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Maintenance Notes -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Maintenance
                                    Notes</h4>
                                <p id="view_notes" class="text-sm text-gray-600 dark:text-gray-300"></p>
                            </div>

                            <!-- Vehicle Metrics -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Vehicle Metrics
                                </h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Odometer at Service</div>
                                        <div id="view_odometer_service"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Current Odometer</div>
                                        <div id="view_current_odometer"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                    <div id="view_engine_row" class="hidden">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Engine Hours at Service
                                        </div>
                                        <div id="view_engine_service"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                    <div id="view_current_engine_row" class="hidden">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Current Engine Hours</div>
                                        <div id="view_current_engine"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cost & Status -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Total Cost</div>
                                        <div id="view_total_cost"
                                            class="text-lg font-bold text-gray-900 dark:text-white"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Status</div>
                                        <div id="view_status" class="mt-1"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Attached
                                    Documents</h4>
                                <div id="view_documents" class="space-y-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700">
                <button type="button" id="closeViewModal"
                    class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-300 dark:border-gray-500 dark:hover:bg-gray-500">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
