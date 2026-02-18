{{-- resources/views/admin/service-log/index.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Service Logs')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Service Logs</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Record and track maintenance service details for fleet
                        vehicles</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="refresh-table"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <button onclick="showCreateModal()"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                        <i class="fas fa-plus mr-2"></i>Create New Service Log
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="p-4 mb-6 bg-white rounded-lg shadow-sm dark:bg-gray-800">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <!-- Vehicle Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Vehicle
                    </label>
                    <select id="filter-vehicle"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">All Vehicles</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->unit_no }} - {{ $vehicle->year }}
                                {{ $vehicle->make }} {{ $vehicle->model }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Category
                    </label>
                    <select id="filter-category"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">All Categories</option>
                        @foreach ($maintenanceCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Range -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        From Date
                    </label>
                    <input type="date" id="filter-date-from"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        To Date
                    </label>
                    <input type="date" id="filter-date-to"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Status
                    </label>
                    <select id="filter-status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Filter Actions -->
            <div class="flex justify-end mt-4 space-x-2">
                <button onclick="applyFilters()"
                    class="px-4 py-2 text-white bg-brand-500 rounded-lg hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                <button onclick="clearFilters()"
                    class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                    <i class="fas fa-times mr-2"></i>Clear
                </button>
            </div>
        </div>

        <!-- Service Logs Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col justify-between sm:flex-row sm:items-center">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        <i class="fas fa-clipboard-list mr-2"></i>Maintenance Service Records
                    </h3>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 sm:mt-0">
                        Total: <span id="total-records" class="font-medium">Loading...</span> records
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">
                <div class="p-5 sm:p-6">
                    <div class="overflow-hidden">
                        <table id="service-logs-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        #</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Vehicle Information</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Service Details</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Metrics</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Cost</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Status</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Created At</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                <!-- Data will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    @include('admin.service-log.partials.form-modal')

    <!-- View Details Modal -->
    @include('admin.service-log.partials.view-modal')

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
@endsection

@push('styles')
    <style>
        /* Custom styles for DataTables */
        .dataTables_wrapper {
            position: relative;
        }

        .dataTables_filter {
            float: right;
            margin-bottom: 1rem;
        }

        .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            background-color: white;
        }

        .dark .dataTables_filter input {
            background-color: #1f2937;
            border-color: #4b5563;
            color: white;
        }

        .dataTables_length {
            float: left;
            margin-bottom: 1rem;
        }

        .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 2rem 0.5rem 0.75rem;
            background-color: white;
        }

        .dark .dataTables_length select {
            background-color: #1f2937;
            border-color: #4b5563;
            color: white;
        }

        .dataTables_info {
            padding-top: 0.75rem !important;
            color: #6b7280;
        }

        .dark .dataTables_info {
            color: #9ca3af;
        }

        .dataTables_paginate {
            padding-top: 0.75rem !important;
        }

        /* Form styles */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Modal styles */
        .modal-lg {
            max-width: 800px;
        }

        /* Category checkbox styles */
        .category-checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
        }

        .dark .category-checkbox-group {
            border-color: #4b5563;
        }

        /* Document preview */
        .document-preview {
            position: relative;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .document-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.375rem;
        }

        .document-preview .remove-doc {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }

        /* File upload area */
        #service-dropzone {
            border: 2px dashed #e5e7eb;
            border-radius: 0.5rem;
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        #service-dropzone:hover {
            border-color: #3b82f6;
            background: #f3f4f6;
        }

        .dark #service-dropzone {
            border-color: #4b5563;
            background: #1f2937;
        }

        .dark #service-dropzone:hover {
            border-color: #3b82f6;
            background: #2d3748;
        }

        #service-dropzone i {
            font-size: 2rem;
            color: #9ca3af;
            margin-bottom: 0.5rem;
        }

        .dark #service-dropzone i {
            color: #6b7280;
        }

        #service-dropzone .file-info {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .dark #service-dropzone .file-info {
            color: #9ca3af;
        }

        /* File preview items */
        .file-preview-item {
            transition: all 0.2s ease;
        }

        .file-preview-item:hover {
            background-color: #f9fafb;
        }

        .dark .file-preview-item:hover {
            background-color: #374151;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#service-logs-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.service-log.index') }}',
                    type: 'GET',
                    data: function(d) {
                        d.vehicle_id = $('#filter-vehicle').val();
                        d.category_id = $('#filter-category').val();
                        d.date_from = $('#filter-date-from').val();
                        d.date_to = $('#filter-date-to').val();
                        d.status = $('#filter-status').val();
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX Error:', error);
                        showToast('Failed to load service logs', 'error');
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '3%',
                        className: 'text-center'
                    },
                    {
                        data: 'vehicle_info',
                        name: 'vehicle_id',
                        orderable: true,
                        searchable: true,
                        width: '22%'
                    },
                    {
                        data: 'service_info',
                        name: 'service_date',
                        orderable: true,
                        searchable: false,
                        width: '20%'
                    },
                    {
                        data: 'metrics',
                        name: 'odometer_at_service',
                        orderable: true,
                        searchable: false,
                        width: '15%'
                    },
                    {
                        data: 'cost',
                        name: 'total_cost',
                        orderable: true,
                        searchable: false,
                        width: '10%',
                        className: 'text-right'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: true,
                        searchable: false,
                        width: '8%',
                        className: 'text-center'
                    },
                    {
                        data: 'created_at_formatted',
                        name: 'created_at',
                        orderable: true,
                        searchable: false,
                        width: '10%',
                        className: 'text-center'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '12%',
                        className: 'text-center'
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search service logs...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No service logs found",
                    zeroRecords: "No matching records found",
                    processing: '<div class="spinner-border text-brand-500" role="status"></div> Loading...',
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        previous: '<i class="fas fa-angle-left"></i>'
                    }
                },
                responsive: true,
                autoWidth: false,
                dom: '<"flex flex-col lg:flex-row lg:items-center lg:justify-between"<"mb-4"l><"mb-4"f>>rt<"flex flex-col lg:flex-row lg:items-center lg:justify-between"<"mb-4"i><"mb-4"p>>',
                lengthMenu: [10, 25, 50, 100],
                pageLength: 10,
                order: [
                    [2, 'desc']
                ],
                drawCallback: function(settings) {
                    var info = this.api().page.info();
                    $('#total-records').text(info.recordsTotal);
                    updateTableDarkMode();
                },
                initComplete: function() {
                    var searchInput = $('.dataTables_filter input');
                    searchInput.addClass(
                        'border border-gray-300 rounded-lg px-4 py-2 w-full lg:w-64 focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:focus:ring-brand-400'
                    );
                    searchInput.wrap('<div class="relative"></div>');
                    searchInput.before(
                        '<i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>'
                    );
                    searchInput.css('padding-left', '2.5rem');

                    var lengthSelect = $('.dataTables_length select');
                    lengthSelect.addClass(
                        'border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:focus:ring-brand-400'
                    );

                    var info = this.api().page.info();
                    $('#total-records').text(info.recordsTotal);
                }
            });

            function updateTableDarkMode() {
                $('.dataTables_length select').addClass('dark:bg-gray-800 dark:border-gray-700 dark:text-white');
                $('.dataTables_filter input').addClass('dark:bg-gray-800 dark:border-gray-700 dark:text-white');
                $('.dataTables_info').addClass('dark:text-gray-400');
                $('.dataTables_paginate .paginate_button').addClass(
                    'dark:text-gray-400 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800');
            }

            updateTableDarkMode();

            if (typeof MutationObserver !== 'undefined') {
                const darkModeObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            setTimeout(updateTableDarkMode, 100);
                        }
                    });
                });

                darkModeObserver.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }

            $('#refresh-table').on('click', function() {
                table.ajax.reload(null, false);
                showToast('Table refreshed successfully', 'success');
            });

            $('#closeModal, #closeViewModal').on('click', function() {
                $('#serviceLogModal').addClass('hidden');
                $('#viewServiceLogModal').addClass('hidden');
                resetForm();
            });

            $(document).on('click', function(event) {
                if ($(event.target).hasClass('fixed')) {
                    $('#serviceLogModal').addClass('hidden');
                    $('#viewServiceLogModal').addClass('hidden');
                    resetForm();
                }
            });

            $('#submitForm').on('click', function() {
                submitServiceLogForm();
            });

            $('#vehicle_id').on('change', function() {
                var vehicleId = $(this).val();
                if (vehicleId) {
                    $.ajax({
                        url: '{{ route('admin.service-log.get-vehicle-details', ':id') }}'.replace(
                            ':id', vehicleId),
                        type: 'GET',
                        success: function(response) {
                            if (response.success) {
                                $('#current_odometer').val(response.data.current_odometer);
                                $('#odometer_at_service').attr('max', response.data
                                    .current_odometer);
                            }
                        }
                    });
                }
            });

            window.applyFilters = function() {
                table.ajax.reload();
            }

            window.clearFilters = function() {
                $('#filter-vehicle').val('');
                $('#filter-category').val('');
                $('#filter-date-from').val('');
                $('#filter-date-to').val('');
                $('#filter-status').val('');
                table.ajax.reload();
            }
        });

        // Global variables
        let selectedFiles = [];
        let dropdownDataCache = null; // Cache dropdown data

        // Load dropdown data with caching
        function loadDropdownData(callback) {
            if (dropdownDataCache) {
                populateDropdowns(dropdownDataCache);
                if (callback) callback();
                return;
            }

            $.ajax({
                url: '{{ route('admin.service-log.dropdown-data') }}',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        dropdownDataCache = response;
                        populateDropdowns(response);
                        if (callback) callback();
                    } else {
                        showToast('Failed to load form data', 'error');
                    }
                },
                error: function(xhr) {
                    console.error('Failed to load dropdown data:', xhr);
                    showToast('Failed to load form data', 'error');
                }
            });
        }

        // Populate dropdowns with data
        function populateDropdowns(response) {
            // Populate vehicles
            var vehicleSelect = $('#vehicle_id');
            vehicleSelect.empty().append('<option value="">Select Vehicle</option>');
            response.vehicles.forEach(function(vehicle) {
                vehicleSelect.append('<option value="' + vehicle.id + '">' + vehicle.unit_no + ' - ' +
                    vehicle.year + ' ' + vehicle.make + ' ' + vehicle.model + '</option>');
            });

            // Populate categories
            var categoriesContainer = $('#categories-container');
            categoriesContainer.empty();
            response.maintenanceCategories.forEach(function(category) {
                categoriesContainer.append(`
                <div class="flex items-center">
                    <input type="checkbox" name="maintenance_categories[]" id="category_${category.id}" value="${category.id}" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                    <label for="category_${category.id}" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">${category.name}</label>
                </div>
            `);
            });
        }

        // Show create modal
        function showCreateModal() {
            $('#modalTitle').text('Create New Service Log');
            $('#submitText').text('Save Service Log');
            $('#service_log_id').val('');
            resetFormErrors();
            resetForm();

            // Load dropdown data and then reset form
            loadDropdownData(function() {
                // Reset form after dropdowns are loaded
                resetForm();
            });

            // Reset file selection
            selectedFiles = [];
            updateFilePreview();

            $('#serviceLogModal').removeClass('hidden');
        }

        // Show edit modal
        function editServiceLog(id) {
            resetFormErrors();

            $('#modalTitle').html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading...');
            $('#submitText').html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading');
            $('#serviceLogModal').removeClass('hidden');

            // Reset file selection
            selectedFiles = [];
            updateFilePreview();

            // First load dropdown data, then load service log data
            loadDropdownData(function() {
                loadServiceLogData(id);
            });
        }

        // Load service log data for editing
        function loadServiceLogData(id) {
            $.ajax({
                url: '{{ route('admin.service-log.edit', ':id') }}'.replace(':id', id),
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#modalTitle').text('Edit Service Log');
                        $('#submitText').text('Update Service Log');
                        $('#service_log_id').val(id);

                        // Fill form fields
                        fillFormFields(response.data);

                        if (response.data.documents && response.data.documents.length > 0) {
                            renderExistingDocuments(response.data.documents);
                        }
                    } else {
                        showToast('Failed to load service log data', 'error');
                        $('#serviceLogModal').addClass('hidden');
                    }
                },
                error: function(xhr) {
                    console.error('Edit error:', xhr);
                    showToast('Failed to load service log data', 'error');
                    $('#serviceLogModal').addClass('hidden');
                }
            });
        }

        // Fill form fields with data
        function fillFormFields(data) {
            // Set vehicle selection
            if (data.vehicle_id) {
                $('#vehicle_id').val(data.vehicle_id);
            }

            // Set service date
            if (data.service_date) {
                $('#service_date').val(data.service_date.split('T')[0]);
            }

            // Set maintenance notes
            $('#maintenance_notes').val(data.maintenance_notes || '');

            // Set metrics
            $('#odometer_at_service').val(data.odometer_at_service || '');
            $('#current_odometer').val(data.current_odometer || '');
            $('#engine_hours_at_service').val(data.engine_hours_at_service || '');
            $('#current_engine_hours').val(data.current_engine_hours || '');

            // Set cost
            $('#total_cost').val(data.total_cost || '');

            // Set status
            $('#status').val(data.status || 'completed');

            // Set categories (checkboxes)
            $('input[name="maintenance_categories[]"]').prop('checked', false);
            if (data.maintenance_categories && Array.isArray(data.maintenance_categories)) {
                data.maintenance_categories.forEach(function(cat) {
                    let catId = typeof cat === 'object' ? cat.id : cat;
                    $('#category_' + catId).prop('checked', true);
                });
            }
        }

        // View service log details
        function viewServiceLog(id) {
            $.ajax({
                url: '{{ route('admin.service-log.show', ':id') }}'.replace(':id', id),
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const vehicle = response.vehicle_info;

                        $('#view_vehicle').text(vehicle ? vehicle.full_name : 'N/A');
                        $('#view_service_date').text(data.service_date ? new Date(data.service_date)
                            .toLocaleDateString() : 'N/A');

                        let categoriesHtml = '';
                        if (data.maintenance_categories && data.maintenance_categories.length > 0) {
                            data.maintenance_categories.forEach(function(cat) {
                                categoriesHtml +=
                                    '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs mr-1">' +
                                    cat.name + '</span>';
                            });
                        } else {
                            categoriesHtml = '<span class="text-gray-500">None selected</span>';
                        }
                        $('#view_categories').html(categoriesHtml);

                        $('#view_notes').text(data.maintenance_notes || 'No notes provided');
                        $('#view_odometer_service').text(data.odometer_at_service ? data.odometer_at_service
                            .toLocaleString() + ' mi' : '0 mi');
                        $('#view_current_odometer').text(data.current_odometer ? data.current_odometer
                            .toLocaleString() + ' mi' : '0 mi');

                        if (data.engine_hours_at_service) {
                            $('#view_engine_service').text(data.engine_hours_at_service.toLocaleString() +
                                ' hrs');
                            $('#view_engine_row').show();
                        } else {
                            $('#view_engine_row').hide();
                        }

                        if (data.current_engine_hours) {
                            $('#view_current_engine').text(data.current_engine_hours.toLocaleString() + ' hrs');
                            $('#view_current_engine_row').show();
                        } else {
                            $('#view_current_engine_row').hide();
                        }

                        $('#view_total_cost').text('$' + parseFloat(data.total_cost).toFixed(2));

                        const statusBadge = {
                                'completed': '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Completed</span>',
                                'pending': '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">Pending</span>',
                                'cancelled': '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Cancelled</span>'
                            } [data.status] ||
                            '<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">' + data
                            .status + '</span>';

                        $('#view_status').html(statusBadge);

                        if (data.documents && data.documents.length > 0) {
                            let docsHtml = '<div class="grid grid-cols-2 gap-2">';
                            data.documents.forEach(function(doc) {
                                let downloadUrl = "{{ url('admin/service-log/document') }}/" + doc.id +
                                    "/download";
                                let fileIcon = doc.file_icon || 'fa-file-alt text-gray-500';

                                docsHtml += `
                                <div class="flex items-center p-2 border rounded">
                                    <i class="fas ${fileIcon} mr-2"></i>
                                    <span class="text-sm truncate flex-1">${doc.original_name}</span>
                                    <a href="${downloadUrl}" class="text-blue-500 hover:text-blue-700 ml-2" target="_blank">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            `;
                            });
                            docsHtml += '</div>';
                            $('#view_documents').html(docsHtml);
                        } else {
                            $('#view_documents').html('<p class="text-gray-500">No documents uploaded</p>');
                        }

                        $('#viewServiceLogModal').removeClass('hidden');
                    } else {
                        showToast('Failed to load service log details', 'error');
                    }
                },
                error: function(xhr) {
                    console.error('View error:', xhr);
                    showToast('Failed to load service log details', 'error');
                }
            });
        }

        // File input change handler
        $(document).on('change', '#service_documents', function(e) {
            const files = Array.from(e.target.files);

            const validFiles = files.filter(file => {
                if (file.size > 10 * 1024 * 1024) {
                    showToast(`File ${file.name} exceeds 10MB limit`, 'error');
                    return false;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];
                if (!allowedTypes.includes(file.type) && !file.name.match(
                        /\.(jpg|jpeg|png|pdf|doc|docx)$/i)) {
                    showToast(`File ${file.name} has invalid type. Allowed: PDF, JPG, PNG, DOC, DOCX`,
                        'error');
                    return false;
                }

                return true;
            });

            selectedFiles = [...selectedFiles, ...validFiles];
            updateFilePreview();
            $(this).val('');
        });

        // Drag and drop handlers
        const dropzone = document.getElementById('service-dropzone');
        if (dropzone) {
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('border-brand-500', 'bg-brand-50', 'dark:bg-brand-900/10');
            });

            dropzone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropzone.classList.remove('border-brand-500', 'bg-brand-50', 'dark:bg-brand-900/10');
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('border-brand-500', 'bg-brand-50', 'dark:bg-brand-900/10');

                const files = Array.from(e.dataTransfer.files);

                const validFiles = files.filter(file => {
                    if (file.size > 10 * 1024 * 1024) {
                        showToast(`File ${file.name} exceeds 10MB limit`, 'error');
                        return false;
                    }

                    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ];
                    if (!allowedTypes.includes(file.type) && !file.name.match(
                            /\.(jpg|jpeg|png|pdf|doc|docx)$/i)) {
                        showToast(`File ${file.name} has invalid type`, 'error');
                        return false;
                    }

                    return true;
                });

                selectedFiles = [...selectedFiles, ...validFiles];
                updateFilePreview();
            });
        }

        // Update file preview
        function updateFilePreview() {
            const container = $('#service-file-preview-container');
            container.empty();

            if (selectedFiles.length === 0) {
                return;
            }

            selectedFiles.forEach((file, index) => {
                const fileIcon = getFileIcon(file.name);
                const fileSize = formatFileSize(file.size);

                const previewHtml = `
                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg dark:border-gray-700 file-preview-item" data-file-index="${index}">
                    <div class="flex items-center flex-1">
                        <i class="mr-3 text-2xl ${fileIcon}"></i>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-xs">${file.name}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">${fileSize}</p>
                        </div>
                    </div>
                    <button type="button" onclick="removeFile(${index})" class="text-red-500 hover:text-red-700 ml-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

                container.append(previewHtml);
            });
        }

        // Remove file
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFilePreview();

            if (selectedFiles.length === 0) {
                $('#service_documents').val('');
            }
        }

        // Get file icon based on extension
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();

            const icons = {
                'pdf': 'fa-file-pdf text-red-500',
                'doc': 'fa-file-word text-blue-500',
                'docx': 'fa-file-word text-blue-500',
                'jpg': 'fa-file-image text-purple-500',
                'jpeg': 'fa-file-image text-purple-500',
                'png': 'fa-file-image text-purple-500'
            };

            return icons[ext] || 'fa-file-alt text-gray-500';
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Submit form
        function submitServiceLogForm() {
            var formData = new FormData(document.getElementById('serviceLogForm'));
            var id = $('#service_log_id').val();
            var url = id ? '{{ route('admin.service-log.update', ':id') }}'.replace(':id', id) :
                '{{ route('admin.service-log.store') }}';

            if (id) {
                formData.append('_method', 'PUT');
            }

            var selectedCategories = [];
            $('input[name="maintenance_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });

            // Clear existing categories and add selected ones
            formData.delete('maintenance_categories[]');
            selectedCategories.forEach(function(catId) {
                formData.append('maintenance_categories[]', catId);
            });

            // Add new files
            selectedFiles.forEach((file, index) => {
                formData.append('new_documents[' + index + ']', file);
            });

            resetFormErrors();

            var originalText = $('#submitText').html();
            $('#submitText').html('<i class="fas fa-spinner fa-spin mr-2"></i>Processing...');

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#submitText').html(originalText);
                    if (response.success) {
                        $('#serviceLogModal').addClass('hidden');
                        $('#service-logs-table').DataTable().ajax.reload();
                        showToast(response.message, 'success');
                        resetForm();
                    } else {
                        showToast(response.message || 'Operation failed', 'error');
                    }
                },
                error: function(xhr) {
                    $('#submitText').html(originalText);
                    if (xhr.status === 422) {
                        var errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            if (key === 'maintenance_categories') {
                                $('#categories_error').html(value[0]);
                            } else if (key.startsWith('new_documents.')) {
                                $('#documents_error').html(value[0]);
                            } else {
                                $('#' + key + '_error').html(value[0]);
                            }
                        });
                        var firstError = Object.keys(errors)[0];
                        if (firstError) {
                            $('#' + firstError).focus();
                        }
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        showToast(xhr.responseJSON.message, 'error');
                    } else {
                        showToast('An error occurred. Please try again.', 'error');
                    }
                }
            });
        }

        // Delete service log
        function deleteServiceLog(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deleting...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: '{{ route('admin.service-log.destroy', ':id') }}'.replace(':id', id),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                $('#service-logs-table').DataTable().ajax.reload();
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonColor: '#3085d6',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.close();
                            let errorMessage = 'Failed to delete service log.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                title: 'Error!',
                                text: errorMessage,
                                icon: 'error',
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    });
                }
            });
        }

        // Render existing documents
        function renderExistingDocuments(documents) {
            var container = $('#existing-documents');
            container.empty();

            if (documents.length > 0) {
                documents.forEach(function(doc) {
                    let fileIcon = getFileIcon(doc.original_name);
                    container.append(`
                    <div class="document-preview" data-id="${doc.id}">
                        <div class="file-icon">
                            <i class="fas ${fileIcon} text-4xl"></i>
                        </div>
                        <div class="text-xs mt-1 max-w-[100px] truncate">${doc.original_name}</div>
                        <div class="remove-doc" onclick="deleteDocument(${doc.id})">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                `);
                });
            }
        }

        // Delete document
        function deleteDocument(docId) {
            Swal.fire({
                title: 'Remove Document?',
                text: "This action cannot be undone",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, remove it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route('admin.service-log.delete-document', ':id') }}'.replace(':id',
                            docId),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('.document-preview[data-id="' + docId + '"]').remove();
                                showToast('Document removed successfully', 'success');
                            } else {
                                showToast('Failed to remove document', 'error');
                            }
                        },
                        error: function() {
                            showToast('Failed to remove document', 'error');
                        }
                    });
                }
            });
        }

        // Reset form
        function resetForm() {
            $('#serviceLogForm')[0].reset();
            $('#service_log_id').val('');
            resetFormErrors();

            // Uncheck all categories
            $('input[name="maintenance_categories[]"]').prop('checked', false);

            $('#existing-documents').empty();

            selectedFiles = [];
            updateFilePreview();
        }

        // Reset form errors
        function resetFormErrors() {
            $('.text-red-600').html('');
        }

        // Toast notification function
        function showToast(message, type = 'success') {
            const types = {
                success: {
                    bg: 'bg-green-500',
                    icon: 'fa-check-circle',
                    border: 'border-green-600'
                },
                error: {
                    bg: 'bg-red-500',
                    icon: 'fa-exclamation-circle',
                    border: 'border-red-600'
                },
                info: {
                    bg: 'bg-blue-500',
                    icon: 'fa-info-circle',
                    border: 'border-blue-600'
                },
                warning: {
                    bg: 'bg-yellow-500',
                    icon: 'fa-exclamation-triangle',
                    border: 'border-yellow-600'
                }
            };

            const toastType = types[type] || types.success;

            const toastId = 'toast-' + Date.now();
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className =
                `${toastType.bg} ${toastType.border} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 transform transition-all duration-300 translate-x-full`;
            toast.innerHTML = `
            <i class="fas ${toastType.icon} text-xl"></i>
            <div>
                <p class="font-medium">${message}</p>
            </div>
            <button onclick="document.getElementById('${toastId}').remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        `;

            document.getElementById('toast-container').appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('translate-x-full');
                toast.classList.add('translate-x-0');
            }, 10);

            setTimeout(() => {
                toast.classList.remove('translate-x-0');
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (document.getElementById(toastId)) {
                        document.getElementById(toastId).remove();
                    }
                }, 300);
            }, 3000);
        }

        // Make functions available globally
        window.showCreateModal = showCreateModal;
        window.editServiceLog = editServiceLog;
        window.viewServiceLog = viewServiceLog;
        window.deleteServiceLog = deleteServiceLog;
        window.deleteDocument = deleteDocument;
        window.removeFile = removeFile;
        window.showToast = showToast;
    </script>
@endpush
