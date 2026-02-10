@extends('layouts.main-layout')

@section('title', 'Document Types')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Document Types</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage document types for different modules</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" onclick="openCreateModal()"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                        <i class="fas fa-plus mr-2"></i>Add Document Type
                    </button>
                </div>
            </div>
        </div>

        @if ($errors->has('system_error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700 dark:text-red-400">{{ $errors->first('system_error') }}</p>
                </div>
            </div>
        @endif

        <!-- Document Types Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col justify-between sm:flex-row sm:items-center">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        <i class="fas fa-file-alt mr-2"></i>Document Types List
                    </h3>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 sm:mt-0">
                        Total: <span id="total-records" class="font-medium">Loading...</span> document types
                    </div>
                </div>

                
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">
                <div class="p-5 sm:p-6">
                    <div class="overflow-hidden">
                        <table id="documentTypesTable" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        #
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Name
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Module
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Status
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Created At
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
    <div id="documentTypeModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title"
        role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

            <!-- Modal panel -->
            <div
                class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-900">
                <form id="documentTypeForm" method="POST">
                    @csrf
                    <input type="hidden" id="documentTypeId" name="id">

                    <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90" id="modalTitle">
                            Create Document Type
                        </h3>
                    </div>

                    <div class="px-5 py-4 sm:px-6 sm:py-5">
                        <div class="space-y-4">
                            <!-- Name -->
                            <div>
                                <label for="name"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Document Type Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="name" name="name" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="e.g., Driver License">
                                <p id="nameError" class="mt-1 text-sm text-red-500 hidden"></p>
                            </div>

                            <!-- Module -->
                            <div>
                                <label for="module"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Module <span class="text-red-500">*</span>
                                </label>
                                <div class="relative z-20 bg-transparent">
                                    <select id="module" name="module" required
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                            Select Module
                                        </option>
                                        @foreach ($modules as $key => $module)
                                            <option value="{{ $key }}">{{ $module }}</option>
                                        @endforeach
                                    </select>
                                    <span
                                        class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </div>
                                <p id="moduleError" class="mt-1 text-sm text-red-500 hidden"></p>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Status <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="status" value="1" checked
                                            class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-400">Active</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="status" value="0"
                                            class="h-4 w-4 border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-400">Inactive</span>
                                    </label>
                                </div>
                                <p id="statusError" class="mt-1 text-sm text-red-500 hidden"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-5 py-4 sm:px-6 sm:py-5 border-t border-gray-100 dark:border-gray-800">
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal()"
                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                                Cancel
                            </button>
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                                <span id="submitButtonText">Create</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#documentTypesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.settings.document-types.index') }}',
                    data: function(d) {
                        d.module_filter = $('#moduleFilter').val();
                        d.status_filter = $('#statusFilter').val();
                    },
                    dataSrc: function(json) {
                        // Update total records
                        $('#total-records').text(json.recordsTotal);
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX Error:', error);
                        console.error('Response:', xhr.responseText);

                        // Show error message
                        showToast('Failed to load document types. Please try again.', 'error');
                    }
                },
                columns: [
                    // Column 0: Index
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '5%',
                        className: 'text-center'
                    },
                    // Column 1: Name
                    {
                        data: 'name',
                        name: 'name',
                        width: '20%',
                        searchable: true,
                        orderable: true
                    },
                    // Column 2: Module
                    {
                        data: 'module',
                        name: 'module',
                        width: '15%',
                        searchable: true,
                        orderable: false
                    },
                    // Column 3: Status
                    {
                        data: 'status',
                        name: 'status',
                        width: '15%',
                        searchable: false,
                        orderable: false,
                        className: 'text-center'
                    },
                    // Column 4: Created At
                    {
                        data: 'created_at',
                        name: 'created_at',
                        width: '15%',
                        searchable: false,
                        orderable: true
                    },
                    // Column 5: Actions
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '10%',
                        className: 'text-center'
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search document types...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No document types found",
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
                }
            });

            // Apply filters on change
            $('#moduleFilter, #statusFilter').on('change', function() {
                table.draw();
            });

            // Clear filters
            $('#clearFilters').on('click', function() {
                $('#moduleFilter').val('');
                $('#statusFilter').val('');
                table.draw();
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
        });

        // Toast notification function (from company index)
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

            const container = document.getElementById('toast-container') || document.body;
            container.appendChild(toast);

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

        // Modal Functions
        function openCreateModal() {
            $('#modalTitle').text('Create Document Type');
            $('#submitButtonText').text('Create');
            $('#documentTypeForm')[0].reset();
            $('#documentTypeId').val('');
            $('input[name="status"][value="1"]').prop('checked', true);
            hideAllErrors();
            $('#documentTypeModal').removeClass('hidden').addClass('block');
        }

        function openEditModal(id) {
            $.ajax({
                url: '{{ url('admin/settings/document-types') }}/' + id,
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#modalTitle').text('Edit Document Type');
                        $('#submitButtonText').text('Update');
                        $('#documentTypeId').val(response.data.id);
                        $('#name').val(response.data.name);
                        $('#module').val(response.data.module);
                        $('input[name="status"][value="' + (response.data.status ? '1' : '0') + '"]').prop(
                            'checked', true);
                        hideAllErrors();
                        $('#documentTypeModal').removeClass('hidden').addClass('block');
                    } else {
                        showToast(response.message || 'Failed to load document type', 'error');
                    }
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.message || 'Failed to load document type', 'error');
                }
            });
        }

        function closeModal() {
            $('#documentTypeModal').removeClass('block').addClass('hidden');
        }

        function openDeleteModal(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
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
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteDocumentType(id);
                }
            });
        }

        // Form Submission
        $('#documentTypeForm').on('submit', function(e) {
            e.preventDefault();

            const formData = $(this).serialize();
            const id = $('#documentTypeId').val();
            const url = id ?
                '{{ url('admin/settings/document-types') }}/' + id :
                '{{ route('admin.settings.document-types.store') }}';
            const method = id ? 'PUT' : 'POST';

            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Processing...');
            submitBtn.prop('disabled', true);

            $.ajax({
                url: url,
                method: method,
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        closeModal();
                        $('#documentTypesTable').DataTable().ajax.reload(null, false);
                    } else {
                        showToast(response.message || 'Operation failed', 'error');
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        showFormErrors(errors);
                        showToast('Please fix the errors in the form', 'error');
                    } else {
                        showToast(xhr.responseJSON?.message || 'Operation failed', 'error');
                    }
                },
                complete: function() {
                    submitBtn.html(originalText);
                    submitBtn.prop('disabled', false);
                }
            });
        });

        // Delete Function
        function deleteDocumentType(id) {
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait while we delete the document type.',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '{{ url('admin/settings/document-types') }}/' + id,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    Swal.close();

                    if (response.success) {
                        $('#documentTypesTable').DataTable().ajax.reload(null, false);
                        showToast(response.message, 'success');
                    } else {
                        showToast(response.message || 'Delete failed', 'error');
                    }
                },
                error: function(xhr) {
                    Swal.close();
                    showToast(xhr.responseJSON?.message || 'Delete failed', 'error');
                }
            });
        }

        // Toggle Status
        function toggleStatus(id, currentStatus) {
            const newStatus = currentStatus ? 0 : 1;
            const action = newStatus ? 'deactivate' : 'activate';

            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to ${action} this document type?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Yes, ${action} it!`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ url('admin/settings/document-types') }}/' + id + '/toggle-status',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#documentTypesTable').DataTable().ajax.reload(null, false);
                                showToast(response.message, 'success');
                            } else {
                                showToast(response.message || 'Status update failed', 'error');
                            }
                        },
                        error: function(xhr) {
                            showToast(xhr.responseJSON?.message || 'Status update failed', 'error');
                        }
                    });
                }
            });
        }

        // Helper Functions
        function showFormErrors(errors) {
            hideAllErrors();

            for (const field in errors) {
                const errorElement = $('#' + field + 'Error');
                if (errorElement.length) {
                    errorElement.text(errors[field][0]).removeClass('hidden');
                }
            }
        }

        function hideAllErrors() {
            $('[id$="Error"]').addClass('hidden').text('');
        }

        // Make functions available globally
        window.openCreateModal = openCreateModal;
        window.editDocumentType = openEditModal;
        window.deleteDocumentType = openDeleteModal;
        window.toggleStatus = toggleStatus;
        window.closeModal = closeModal;
    </script>
@endpush
