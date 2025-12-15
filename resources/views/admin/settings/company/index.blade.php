@extends('layouts.main-layout')

@section('title', 'Companies')

@section('content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Companies</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage all companies in your system</p>
                </div>
                <a href="{{ route('admin.settings.company.create') }}" 
                   class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                    <i class="fas fa-plus mr-2"></i>Add New Company
                </a>
            </div>
        </div>

        <!-- Companies Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    <i class="fas fa-list mr-2"></i>Company List
                </h3>
            </div>
            
            <div class="border-t border-gray-100 dark:border-gray-800">
                <div class="p-5 sm:p-6">
                    <div class="overflow-hidden">
                        <table id="companies-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Logo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Company Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Phone</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">City</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</th>
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
@endsection

@push('scripts')
    <!-- Include SweetAlert2 -->
    {{-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> --}}
    
    <!-- DataTables Scripts -->
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#companies-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('admin.settings.company') }}",
                columns: [
                    { 
                        data: 'DT_RowIndex', 
                        name: 'DT_RowIndex', 
                        orderable: false, 
                        searchable: false,
                        className: 'px-4 py-3 text-sm text-gray-900 dark:text-white/90'
                    },
                    { 
                        data: 'logo', 
                        name: 'logo', 
                        orderable: false, 
                        searchable: false,
                        className: 'px-4 py-3'
                    },
                    { 
                        data: 'company_name', 
                        name: 'company_name',
                        className: 'px-4 py-3 text-sm text-gray-900 dark:text-white/90'
                    },
                    { 
                        data: 'user_email', 
                        name: 'user.email',
                        className: 'px-4 py-3 text-sm text-gray-900 dark:text-white/90'
                    },
                    { 
                        data: 'phone', 
                        name: 'phone',
                        className: 'px-4 py-3 text-sm text-gray-900 dark:text-white/90'
                    },
                    { 
                        data: 'city', 
                        name: 'city',
                        className: 'px-4 py-3 text-sm text-gray-900 dark:text-white/90'
                    },
                    { 
                        data: 'status', 
                        name: 'status',
                        className: 'px-4 py-3 text-sm'
                    },
                    { 
                        data: 'action', 
                        name: 'action', 
                        orderable: false, 
                        searchable: false,
                        className: 'px-4 py-3 text-sm'
                    }
                ],
                language: {
                    search: "Search:",
                    searchPlaceholder: "Search companies...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No companies found",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                responsive: true,
                autoWidth: false,
                drawCallback: function(settings) {
                    // Update dark mode classes after table redraw
                    updateTableDarkMode();
                }
            });

            // Function to update dark mode classes for DataTables
            function updateTableDarkMode() {
                $('.dataTables_length select').addClass('dark:bg-gray-800 dark:border-gray-700 dark:text-white');
                $('.dataTables_filter input').addClass('dark:bg-gray-800 dark:border-gray-700 dark:text-white');
                $('.dataTables_info').addClass('dark:text-gray-400');
                $('.dataTables_paginate .paginate_button').addClass('dark:text-gray-400 dark:border-gray-700');
            }

            // Initial dark mode setup
            updateTableDarkMode();

            // Watch for dark mode changes (if you have dark mode toggle)
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
        });

        // Delete company function with SweetAlert2
        function deleteCompany(companyId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this! This will delete the company and associated user account.",
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
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait while we delete the company.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Perform AJAX delete
                    $.ajax({
                        url: '/admin/settings/company/' + companyId,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.close();
                            
                            if (response.success) {
                                // Reload DataTable
                                $('#companies-table').DataTable().ajax.reload();
                                
                                // Show success message
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonColor: '#3085d6',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonColor: '#3085d6'
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.close();
                            
                            let errorMessage = 'Failed to delete company. Please try again.';
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

    </script>

    
@endpush