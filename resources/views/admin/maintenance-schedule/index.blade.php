{{-- resources/views/admin/maintenance-schedule/index.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Maintenance Schedules')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Maintenance Schedules</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Set up maintenance schedules for your fleet assets</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="refresh-table"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <button onclick="showCreateModal()"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                        <i class="fas fa-plus mr-2"></i>Create New Schedule
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="p-4 mb-6 bg-white rounded-lg shadow-sm dark:bg-gray-800">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
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

                <!-- Schedule Type Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Schedule Type
                    </label>
                    <select id="filter-type"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">All Types</option>
                        <option value="date">By Date</option>
                        <option value="mileage">By Mileage</option>
                        <option value="engine_hours">By Engine Hours</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Status
                    </label>
                    <select id="filter-status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="completed">Completed</option>
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

        <!-- Schedules Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col justify-between sm:flex-row sm:items-center">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        <i class="fas fa-calendar-alt mr-2"></i>Maintenance Schedules
                    </h3>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 sm:mt-0">
                        Total: <span id="total-records" class="font-medium">Loading...</span> schedules
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">
                <div class="p-5 sm:p-6">
                    <div class="overflow-hidden">
                        <table id="schedules-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        #</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Schedule</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Vehicle</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Interval</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Next Due</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Status</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Created</th>
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
    @include('admin.maintenance-schedule.partials.form-modal')

    <!-- View Details Modal -->
    @include('admin.maintenance-schedule.partials.view-modal')

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var table = $('#schedules-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.maintenance-schedule.index') }}',
                    type: 'GET',
                    data: function(d) {
                        d.vehicle_id = $('#filter-vehicle').val();
                        d.category_id = $('#filter-category').val();
                        d.schedule_type = $('#filter-type').val();
                        d.status = $('#filter-status').val();
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '3%'
                    },
                    {
                        data: 'schedule_info',
                        name: 'title',
                        orderable: true,
                        searchable: true,
                        width: '20%'
                    },
                    {
                        data: 'vehicle_info',
                        name: 'vehicle_id',
                        orderable: true,
                        searchable: true,
                        width: '20%'
                    },
                    {
                        data: 'interval',
                        name: 'interval_days',
                        orderable: true,
                        searchable: false,
                        width: '15%'
                    },
                    {
                        data: 'next_due',
                        name: 'next_due_date',
                        orderable: true,
                        searchable: false,
                        width: '20%'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: true,
                        searchable: false,
                        width: '8%'
                    },
                    {
                        data: 'created_at_formatted',
                        name: 'created_at',
                        orderable: true,
                        searchable: false,
                        width: '10%'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '10%'
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search schedules...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    emptyTable: "No schedules found",
                    processing: '<div class="spinner-border text-brand-500"></div> Loading...'
                },
                order: [
                    [4, 'asc']
                ],
                drawCallback: function(settings) {
                    var info = this.api().page.info();
                    $('#total-records').text(info.recordsTotal);
                }
            });

            $('#refresh-table').on('click', function() {
                table.ajax.reload();
            });

            $('#closeModal, #closeViewModal').on('click', function() {
                $('#scheduleModal').addClass('hidden');
                $('#viewScheduleModal').addClass('hidden');
                resetForm();
            });

            $(document).on('click', function(event) {
                if ($(event.target).hasClass('fixed')) {
                    $('#scheduleModal').addClass('hidden');
                    $('#viewScheduleModal').addClass('hidden');
                    resetForm();
                }
            });

            $('#submitForm').on('click', function() {
                submitScheduleForm();
            });

            $('#vehicle_id').on('change', function() {
                var vehicleId = $(this).val();
                if (vehicleId) {
                    $.ajax({
                        url: '{{ route('admin.maintenance-schedule.get-vehicle-details', ':id') }}'
                            .replace(
                                ':id', vehicleId),
                        type: 'GET',
                        success: function(response) {
                            if (response.success) {
                                // You can use this to pre-fill last due values if needed
                                
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
                $('#filter-type').val('');
                $('#filter-status').val('');
                table.ajax.reload();
            }
        });

        // Global variables
        let dropdownDataCache = null;

        // Load dropdown data with caching
        function loadDropdownData(callback) {

            if (dropdownDataCache) {
                populateDropdowns(dropdownDataCache);
                if (callback) callback();
                return;
            }

            $.ajax({
                url: '{{ route('admin.maintenance-schedule.dropdown-data') }}',
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
            vehicleSelect.empty().append('<option value="">All Vehicles (Global Schedule)</option>');
            response.vehicles.forEach(function(vehicle) {
                vehicleSelect.append('<option value="' + vehicle.id + '">' + vehicle.unit_no + ' - ' +
                    vehicle.year + ' ' + vehicle.make + ' ' + vehicle.model + '</option>');
            });

            // Populate categories
            var categorySelect = $('#maintenance_category_id');
            categorySelect.empty().append('<option value="">Select Category</option>');
            response.maintenanceCategories.forEach(function(category) {
                categorySelect.append('<option value="' + category.id + '">' + category.name + '</option>');
            });
        }

        // Show create modal
        function showCreateModal() {
            $('#modalTitle').text('Create New Schedule');
            $('#submitText').text('Create Schedule');
            $('#schedule_id').val('');
            resetFormErrors();

            // Load dropdown data first, then reset form
            loadDropdownData(function() {
                resetForm();
                selectScheduleType('date');
            });

            $('#scheduleModal').removeClass('hidden');
        }

        // Edit schedule
        function editSchedule(id) {

            resetFormErrors();
            $('#modalTitle').html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading...');
            $('#submitText').html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading');
            $('#scheduleModal').removeClass('hidden');

            // First load dropdown data, then load schedule data
            loadDropdownData(function() {
                loadScheduleData(id);
            });
        }

        // Load schedule data for editing
        function loadScheduleData(id) {
            $.ajax({
                url: '{{ route('admin.maintenance-schedule.edit', ':id') }}'.replace(':id', id),
                type: 'GET',
                success: function(response) {
                 
                    if (response.success) {
                        $('#modalTitle').text('Edit Schedule');
                        $('#submitText').text('Update Schedule');
                        $('#schedule_id').val(id);

                        // Fill form fields
                        fillFormFields(response.data);
                    } else {
                        showToast('Failed to load schedule data', 'error');
                        $('#scheduleModal').addClass('hidden');
                    }
                },
                error: function(xhr) {
                    console.error('Edit error:', xhr);
                    showToast('Failed to load schedule data', 'error');
                    $('#scheduleModal').addClass('hidden');
                }
            });
        }

        // Fill form fields with data
        function fillFormFields(data) {
            
            // Set basic fields
            if (data.maintenance_category_id) {
                $('#maintenance_category_id').val(data.maintenance_category_id);
            }

            $('#title').val(data.title || '');

            if (data.vehicle_id) {
                $('#vehicle_id').val(data.vehicle_id);
            }

            // Set schedule type and interval
            if (data.schedule_type) {
                selectScheduleType(data.schedule_type);

                // Small delay to ensure interval fields are visible
                setTimeout(function() {
                    if (data.schedule_type === 'date' && data.interval_days) {
                        $('#interval_days').val(data.interval_days);
                    } else if (data.schedule_type === 'mileage' && data.interval_miles) {
                        $('#interval_miles').val(data.interval_miles);
                    } else if (data.schedule_type === 'engine_hours' && data.interval_hours) {
                        $('#interval_hours').val(data.interval_hours);
                    }
                }, 100);
            }

            // Set description and notes
            $('#description').val(data.description || '');
            $('#notes').val(data.notes || '');

            // Set status
            if (data.status) {
                $('#status').val(data.status);
            }
        }

        // View schedule details
        function viewSchedule(id) {
            $.ajax({
                url: '{{ route('admin.maintenance-schedule.show', ':id') }}'.replace(':id', id),
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        $('#view_category').text(data.maintenance_category?.name || 'N/A');
                        $('#view_title').text(data.title || 'N/A');
                        $('#view_vehicle').text(data.vehicle ? data.vehicle.full_name : 'All Vehicles');
                        $('#view_type').text(data.schedule_type_label);
                        $('#view_interval').text(data.interval_text);
                        $('#view_next_due').text(data.next_due_text);
                        $('#view_description').text(data.description || 'No description provided');
                        $('#view_notes').text(data.notes || 'No notes provided');
                        $('#view_status').html(data.status_badge);

                        if (data.is_due) {
                            $('#view_due_status').html('<span class="text-xs text-red-500">Due now!</span>');
                            $('#mark-completed-btn').removeClass('hidden').data('id', id);
                        } else {
                            $('#view_due_status').empty();
                            $('#mark-completed-btn').addClass('hidden');
                        }

                        $('#viewScheduleModal').removeClass('hidden');
                    }
                },
                error: function(xhr) {
                    showToast('Failed to load schedule details', 'error');
                }
            });
        }

        // Mark schedule as completed
        function markScheduleCompleted() {
            let id = $('#mark-completed-btn').data('id');

            Swal.fire({
                title: 'Mark as Completed?',
                text: 'This will update the last due date and calculate the next due date',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Yes, mark completed'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route('admin.maintenance-schedule.mark-completed', ':id') }}'.replace(
                            ':id', id),
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                showToast(response.message, 'success');
                                $('#viewScheduleModal').addClass('hidden');
                                $('#schedules-table').DataTable().ajax.reload();
                            }
                        },
                        error: function() {
                            showToast('Failed to mark schedule as completed', 'error');
                        }
                    });
                }
            });
        }

        // Submit form
        function submitScheduleForm() {
            var formData = new FormData(document.getElementById('scheduleForm'));
            var id = $('#schedule_id').val();
            var url = id ? '{{ route('admin.maintenance-schedule.update', ':id') }}'.replace(':id', id) :
                '{{ route('admin.maintenance-schedule.store') }}';

            if (id) {
                formData.append('_method', 'PUT');
            }

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
                        $('#scheduleModal').addClass('hidden');
                        $('#schedules-table').DataTable().ajax.reload();
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
                            $('#' + key + '_error').html(value[0]);
                        });
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        showToast(xhr.responseJSON.message, 'error');
                    } else {
                        showToast('An error occurred. Please try again.', 'error');
                    }
                }
            });
        }

        // Delete schedule
        function deleteSchedule(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
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
                        url: '{{ route('admin.maintenance-schedule.destroy', ':id') }}'.replace(':id', id),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                $('#schedules-table').DataTable().ajax.reload();
                                Swal.fire('Deleted!', response.message, 'success');
                            }
                        },
                        error: function(xhr) {
                            Swal.close();
                            let message = xhr.responseJSON?.message || 'Failed to delete schedule';
                            Swal.fire('Error!', message, 'error');
                        }
                    });
                }
            });
        }

        // Select schedule type
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

        // Reset form
        function resetForm() {
            $('#scheduleForm')[0].reset();
            $('#schedule_id').val('');
            resetFormErrors();
            selectScheduleType('date');
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
                    icon: 'fa-check-circle'
                },
                error: {
                    bg: 'bg-red-500',
                    icon: 'fa-exclamation-circle'
                },
                info: {
                    bg: 'bg-blue-500',
                    icon: 'fa-info-circle'
                },
                warning: {
                    bg: 'bg-yellow-500',
                    icon: 'fa-exclamation-triangle'
                }
            };

            const toastType = types[type] || types.success;
            const toastId = 'toast-' + Date.now();

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className =
                `${toastType.bg} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 transform transition-all duration-300 translate-x-full`;
            toast.innerHTML = `
            <i class="fas ${toastType.icon} text-xl"></i>
            <p class="font-medium">${message}</p>
            <button onclick="document.getElementById('${toastId}').remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        `;

            document.getElementById('toast-container').appendChild(toast);

            setTimeout(() => toast.classList.remove('translate-x-full'), 10);
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Make functions global
        window.showCreateModal = showCreateModal;
        window.editSchedule = editSchedule;
        window.viewSchedule = viewSchedule;
        window.deleteSchedule = deleteSchedule;
        window.markScheduleCompleted = markScheduleCompleted;
        window.selectScheduleType = selectScheduleType;
        window.showToast = showToast;
    </script>
@endpush

@push('styles')
    <style>
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

        .schedule-type-option {
            transition: all 0.2s ease;
        }

        .schedule-type-option:hover {
            background-color: #f3f4f6;
        }

        .dark .schedule-type-option:hover {
            background-color: #374151;
        }

        .schedule-type-option.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .dark .schedule-type-option.selected {
            border-color: #3b82f6;
            background-color: #1e3a8a;
        }
    </style>
@endpush
