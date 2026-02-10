@extends('layouts.main-layout')

@section('title', 'Equipment Types')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Equipment Types</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage all equipment types in your system</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="refresh-table"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <button onclick="showCreateModal()"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                        <i class="fas fa-plus mr-2"></i>Add New Equipment Type
                    </button>
                </div>
            </div>
        </div>

        <!-- Equipment Types Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col justify-between sm:flex-row sm:items-center">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        <i class="fas fa-tools mr-2"></i>Equipment Types List
                    </h3>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 sm:mt-0">
                        Total: <span id="total-records" class="font-medium">Loading...</span> equipment types
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">
                <div class="p-5 sm:p-6">
                    <div class="overflow-hidden">
                        <table id="equipment-types-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        #
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Equipment Type Name
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Equipment Count
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Created At
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Updated At
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Actions
                                    </th>
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
    <div id="equipmentTypeModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div
                class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 id="modalTitle" class="text-lg font-medium leading-6 text-gray-900 dark:text-white"></h3>
                            <div class="mt-4">
                                <form id="equipmentTypeForm">
                                    @csrf
                                    <input type="hidden" id="equipment_type_id" name="id">
                                    <div>
                                        <label for="name"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Equipment Type Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="name" id="name" required
                                            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            placeholder="Enter equipment type (e.g., Trailer, Forklift, Crane, Generator)">
                                        <div id="name_error" class="mt-1 text-sm text-red-600"></div>
                                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Enter a descriptive name for the equipment type
                                        </p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700">
                    <button type="button" id="submitForm"
                        class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white border border-transparent rounded-md shadow-sm bg-brand-600 hover:bg-brand-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <span id="submitText">Save</span>
                    </button>
                    <button type="button" id="closeModal"
                        class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-300 dark:border-gray-500 dark:hover:bg-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

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
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#equipment-types-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.equipment.type.index') }}',
                    type: 'GET',
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX Error:', error);
                        showToast('Failed to load equipment types', 'error');
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '5%',
                        className: 'text-center'
                    },
                    {
                        data: 'name',
                        name: 'name',
                        width: '30%',
                        searchable: true,
                        orderable: true
                    },
                    {
                        data: 'equipment_count',
                        name: 'equipment_count',
                        orderable: false,
                        searchable: false,
                        width: '15%',
                        className: 'text-center'
                    },
                    {
                        data: 'created_at_formatted',
                        name: 'created_at',
                        width: '15%',
                        searchable: false,
                        orderable: true,
                        className: 'text-center'
                    },
                    {
                        data: 'updated_at_formatted',
                        name: 'updated_at',
                        width: '15%',
                        searchable: false,
                        orderable: true,
                        className: 'text-center'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '20%',
                        className: 'text-center'
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search equipment types...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No equipment types found",
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
                drawCallback: function(settings) {
                    // Update total records
                    var info = this.api().page.info();
                    $('#total-records').text(info.recordsTotal);

                    // Update dark mode classes
                    updateTableDarkMode();
                },
                initComplete: function() {
                    // Style the search input
                    var searchInput = $('.dataTables_filter input');
                    searchInput.addClass(
                        'border border-gray-300 rounded-lg px-4 py-2 w-full lg:w-64 focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:focus:ring-brand-400'
                    );
                    searchInput.wrap('<div class="relative"></div>');
                    searchInput.before(
                        '<i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>'
                    );
                    searchInput.css('padding-left', '2.5rem');

                    // Style the length select
                    var lengthSelect = $('.dataTables_length select');
                    lengthSelect.addClass(
                        'border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:focus:ring-brand-400'
                    );

                    // Set initial total records
                    var info = this.api().page.info();
                    $('#total-records').text(info.recordsTotal);
                }
            });

            // Function to update dark mode classes
            function updateTableDarkMode() {
                $('.dataTables_length select').addClass('dark:bg-gray-800 dark:border-gray-700 dark:text-white');
                $('.dataTables_filter input').addClass('dark:bg-gray-800 dark:border-gray-700 dark:text-white');
                $('.dataTables_info').addClass('dark:text-gray-400');
                $('.dataTables_paginate .paginate_button').addClass(
                    'dark:text-gray-400 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800');
            }

            // Initial dark mode setup
            updateTableDarkMode();

            // Watch for dark mode changes
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

            // Refresh table button
            $('#refresh-table').on('click', function() {
                table.ajax.reload(null, false);
                showToast('Table refreshed successfully', 'success');
            });

            // Modal handlers
            $('#closeModal').on('click', function() {
                $('#equipmentTypeModal').addClass('hidden');
                resetForm();
            });

            // Close modal when clicking outside
            $(document).on('click', function(event) {
                if ($(event.target).hasClass('fixed')) {
                    $('#equipmentTypeModal').addClass('hidden');
                    resetForm();
                }
            });

            // Submit form
            $('#submitForm').on('click', function() {
                submitEquipmentTypeForm();
            });

            // Enter key to submit form
            $('#equipmentTypeModal').on('keypress', function(e) {
                if (e.which === 13 && !$(e.target).is('textarea')) {
                    e.preventDefault();
                    submitEquipmentTypeForm();
                }
            });
        });

        // Show create modal
        function showCreateModal() {
            $('#modalTitle').text('Add New Equipment Type');
            $('#submitText').text('Save');
            $('#equipment_type_id').val('');
            resetFormErrors();
            $('#equipmentTypeModal').removeClass('hidden');
            $('#name').focus();
        }

        // Show edit modal
        function editEquipmentType(id) {
            resetFormErrors();

            // Show loading
            $('#modalTitle').html('Loading...');
            $('#submitText').html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading');
            $('#equipmentTypeModal').removeClass('hidden');

            $.ajax({
                url: '{{ route('admin.equipment.type.edit', ':id') }}'.replace(':id', id),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#modalTitle').text('Edit Equipment Type');
                        $('#submitText').text('Update');
                        $('#equipment_type_id').val(id);
                        $('#name').val(response.data.name);
                    } else {
                        showToast(response.message || 'Failed to load equipment type data', 'error');
                        $('#equipmentTypeModal').addClass('hidden');
                    }
                },
                error: function(xhr) {
                    console.error('Edit error:', xhr);
                    showToast('Failed to load equipment type data', 'error');
                    $('#equipmentTypeModal').addClass('hidden');
                }
            });
        }

        // Submit form
        function submitEquipmentTypeForm() {
            var formData = $('#equipmentTypeForm').serialize();
            var url = $('#equipment_type_id').val() ?
                '{{ route('admin.equipment.type.update', ':id') }}'.replace(':id', $('#equipment_type_id').val()) :
                '{{ route('admin.equipment.type.store') }}';
            var method = $('#equipment_type_id').val() ? 'PUT' : 'POST';

            resetFormErrors();

            // Show loading
            var originalText = $('#submitText').html();
            $('#submitText').html('<i class="fas fa-spinner fa-spin mr-2"></i>Processing...');

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                headers: {
                    'X-HTTP-Method-Override': method,
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#submitText').html(originalText);
                    if (response.success) {
                        $('#equipmentTypeModal').addClass('hidden');
                        $('#equipment-types-table').DataTable().ajax.reload();
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
                        // Focus on first error field
                        if (errors.name) {
                            $('#name').focus();
                        }
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        showToast(xhr.responseJSON.message, 'error');
                    } else {
                        showToast('An error occurred. Please try again.', 'error');
                    }
                }
            });
        }

        // Delete equipment type
        function deleteEquipmentType(id, name) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this! Equipment type: " + name,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Deleting...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: '{{ route('admin.equipment.type.destroy', ':id') }}'.replace(':id', id),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                $('#equipment-types-table').DataTable().ajax.reload();
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonColor: '#3085d6',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    showClass: {
                                        popup: 'animate__animated animate__fadeInDown'
                                    },
                                    hideClass: {
                                        popup: 'animate__animated animate__fadeOutUp'
                                    }
                                });
                            } else {
                                Swal.fire({
                                    title: 'Cannot Delete!',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonColor: '#3085d6'
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.close();
                            let errorMessage = 'Failed to delete equipment type.';
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

        // Reset form
        function resetForm() {
            $('#equipmentTypeForm')[0].reset();
            $('#equipment_type_id').val('');
            resetFormErrors();
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

            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
                toast.classList.add('translate-x-0');
            }, 10);

            // Auto remove after 3 seconds
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
        window.editEquipmentType = editEquipmentType;
        window.deleteEquipmentType = deleteEquipmentType;
        window.showCreateModal = showCreateModal;
    </script>
@endpush
