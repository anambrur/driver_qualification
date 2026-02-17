{{-- resources/views/admin/maintenance-schedule/partials/view-modal.blade.php --}}
<div id="viewScheduleModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div
            class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full dark:bg-gray-800">
            <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="w-full mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                            <i class="fas fa-calendar-alt mr-2"></i>Schedule Details
                        </h3>

                        <div class="mt-6 space-y-4">
                            <!-- Basic Information -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Basic
                                    Information</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Category</div>
                                        <div id="view_category"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Title</div>
                                        <div id="view_title" class="text-sm font-medium text-gray-900 dark:text-white">
                                        </div>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Vehicle</div>
                                        <div id="view_vehicle"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Schedule Type & Interval -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Schedule
                                    Settings</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Type</div>
                                        <div id="view_type" class="text-sm font-medium text-gray-900 dark:text-white">
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Interval</div>
                                        <div id="view_interval"
                                            class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Next Due -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Next Due</h4>
                                <div id="view_next_due" class="text-sm font-medium text-gray-900 dark:text-white"></div>
                                <div id="view_due_status" class="mt-2"></div>
                            </div>

                            <!-- Description & Notes -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Description &
                                    Notes</h4>
                                <div class="space-y-3">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Description</div>
                                        <div id="view_description" class="text-sm text-gray-900 dark:text-white mt-1">
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Internal Notes</div>
                                        <div id="view_notes" class="text-sm text-gray-900 dark:text-white mt-1"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Status</div>
                                        <div id="view_status" class="mt-1"></div>
                                    </div>
                                    <button id="mark-completed-btn" onclick="markScheduleCompleted()"
                                        class="hidden px-3 py-1 text-sm text-green-600 bg-green-100 rounded-md hover:bg-green-200">
                                        <i class="fas fa-check mr-1"></i>Mark Completed
                                    </button>
                                </div>
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
