@extends('layouts.main-layout')

@section('title', 'Driver Management')

@section('content')
    <div class="mx-auto max-w-7xl p-4 pb-20 md:p-6 md:pb-6">
        <div class="space-y-5 sm:space-y-6">
            <!-- Header Card -->
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="px-5 py-4 sm:px-6 sm:py-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Driver Management
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Manage and monitor your drivers
                            </p>
                        </div>

                        <!-- Add Driver Button -->
                        <a href="{{ route('admin.driver.create') }}"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:bg-brand-500 dark:hover:bg-brand-600">
                            <i class="fas fa-plus mr-2 text-xs"></i>
                            Add Driver
                        </a>
                    </div>
                </div>
            </div>

            <!-- DataTable Card -->
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 sm:p-6">
                    <!-- Search Box -->
                    {{-- <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="w-full sm:w-auto sm:max-w-sm">
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="search" id="driver-search"
                                    placeholder="Search drivers by name, company, etc..."
                                    class="block w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-500 focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-brand-500">
                            </div>
                        </div>

                        <!-- Mobile View Toggle -->
                        <div class="flex items-center gap-2 sm:hidden">
                            <span class="text-sm text-gray-600 dark:text-gray-400">View:</span>
                            <button id="toggle-view"
                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-gray-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                <i class="fas fa-list mr-2"></i>Table
                            </button>
                        </div>
                    </div> --}}

                    <!-- DataTable Container -->
                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800 p-4">
                        <!-- Desktop Table View -->
                        <div id="desktop-table-view">
                            <div class="overflow-x-auto">
                                <table id="drivers-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                                        <tr>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                #
                                            </th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                Full Name
                                            </th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                Status
                                            </th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                State
                                            </th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                License Exp.
                                            </th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                Medical Exp.
                                            </th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                Hire Date
                                            </th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900/50">
                                        <!-- Data will be loaded by DataTables -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mobile Card View (Hidden by default) -->
                        <div id="mobile-card-view" class="hidden p-4">
                            <div id="mobile-drivers-container" class="space-y-4">
                                <!-- Mobile cards will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Table Info (will be populated by DataTables) -->
                    <div id="table-info" class="mt-4 text-sm text-gray-600 dark:text-gray-400"></div>

                    <!-- Loading State -->
                    <div id="table-loading" class="mt-4 text-center py-8">
                        <div
                            class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-gray-300 border-r-brand-500">
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading drivers...</p>
                    </div>

                    <!-- Legend for Mobile View -->
                    <div class="mt-4 hidden flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400 sm:hidden"
                        id="status-legend">
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-green-500"></span> Active
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span> Inactive
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-yellow-500"></span> On Leave
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            let table;
            let isMobileView = window.innerWidth < 640;

            // Hide loading initially
            $('#table-loading').hide();

            // Toggle between table and card view on mobile
            $('#toggle-view').on('click', function() {
                const isTableView = $('#desktop-table-view').is(':visible');
                if (isTableView) {
                    $('#desktop-table-view').addClass('hidden');
                    $('#mobile-card-view').removeClass('hidden');
                    $('#toggle-view').html('<i class="fas fa-table mr-2"></i>Table');
                    if (table) {
                        renderMobileCards();
                    }
                } else {
                    $('#desktop-table-view').removeClass('hidden');
                    $('#mobile-card-view').addClass('hidden');
                    $('#toggle-view').html('<i class="fas fa-list mr-2"></i>Cards');
                }
            });

            // Initialize DataTable - SIMPLIFIED VERSION FIRST
            function initializeDataTable() {
                try {
                    // console.log('Initializing DataTable...');

                    // Show loading
                    $('#table-loading').show();

                    table = $('#drivers-table').DataTable({
                        processing: true,
                        serverSide: true,
                        ajax: {
                            url: "{{ route('admin.driver.index') }}",
                            type: 'GET',
                            data: function(d) {
                                // Add any additional parameters here
                            },
                            error: function(xhr, error, thrown) {
                                // console.error('AJAX Error:', error, thrown);
                                $('#table-loading').html(
                                    '<div class="text-center py-8"><div class="text-red-500 dark:text-red-400">Error loading data. Please try again.</div></div>'
                                );
                            }
                        },
                        columns: [{
                                data: 'DT_RowIndex',
                                name: 'id',
                                orderable: true,
                                searchable: false,
                                className: 'px-4 py-3 text-sm text-gray-900 dark:text-white'
                            },
                            {
                                data: 'full_name',
                                name: 'first_name',
                                orderable: true,
                                searchable: true,
                                className: 'px-4 py-3 text-sm text-gray-900 dark:text-white font-medium'
                            },
                            {
                                data: 'status',
                                name: 'status',
                                orderable: true,
                                searchable: true,
                                className: 'px-4 py-3 text-sm'
                            },
                            {
                                data: 'state',
                                name: 'state',
                                orderable: true,
                                searchable: true,
                                className: 'px-4 py-3 text-sm text-gray-900 dark:text-white'
                            },
                            {
                                data: 'license_expiration_date',
                                name: 'license_expiration_date',
                                orderable: false, // Set to false since it's a computed column
                                searchable: false,
                                className: 'px-4 py-3 text-sm text-gray-900 dark:text-white',
                                render: function(data) {
                                    return data || 'N/A';
                                }
                            },
                            {
                                data: 'medical_certificate_expiration_date',
                                name: 'medical_certificate_expiration_date',
                                orderable: true,
                                searchable: false,
                                className: 'px-4 py-3 text-sm text-gray-900 dark:text-white',
                                render: function(data) {
                                    return data || 'N/A';
                                }
                            },
                            {
                                data: 'hired_at',
                                name: 'hired_at',
                                orderable: true,
                                searchable: false,
                                className: 'px-4 py-3 text-sm text-gray-900 dark:text-white'
                            },
                            {
                                data: 'action',
                                name: 'action',
                                orderable: false,
                                searchable: false,
                                className: 'px-4 py-3 text-sm'
                            }
                        ],
                        order: [
                            [1, 'asc']
                        ],
                        lengthMenu: [
                            [10, 25, 50, 100],
                            [10, 25, 50, 100]
                        ],
                        pageLength: 10,
                        responsive: true, // Enable basic responsive
                        autoWidth: false,
                        language: {
                            emptyTable: "No drivers found",
                            zeroRecords: "No matching drivers found",
                            info: "Showing _START_ to _END_ of _TOTAL_ drivers",
                            infoEmpty: "Showing 0 to 0 of 0 drivers",
                            infoFiltered: "(filtered from _MAX_ total drivers)",
                            search: "",
                            searchPlaceholder: "Search...",
                            lengthMenu: "Show _MENU_",
                            paginate: {
                                first: '<i class="fas fa-angle-double-left"></i>',
                                last: '<i class="fas fa-angle-double-right"></i>',
                                next: '<i class="fas fa-angle-right"></i>',
                                previous: '<i class="fas fa-angle-left"></i>'
                            }
                        },
                        dom: '<"flex flex-col sm:flex-row sm:items-center justify-between mb-4"<"mb-2 sm:mb-0"l><"mb-2 sm:mb-0"f>>rt<"flex flex-col sm:flex-row sm:items-center justify-between mt-4"<"mb-2 sm:mb-0"i><"sm:text-right"p>>',
                        initComplete: function(settings, json) {
                            // console.log('DataTable initialized successfully');
                            $('#table-loading').hide();
                            applyTableStyles();

                            // Hide DataTables default processing indicator
                            $('.dataTables_processing').addClass('hidden');

                            // Show status legend on mobile
                            if (window.innerWidth < 640) {
                                $('#status-legend').removeClass('hidden');
                            }
                        },
                        drawCallback: function(settings) {
                            applyTableStyles();

                            // Update table info
                            const info = this.api().page.info();
                            $('#table-info').html(
                                `Showing ${info.start + 1} to ${info.end} of ${info.recordsTotal} drivers`
                            );

                            // If in mobile card view, update cards
                            if ($('#mobile-card-view').is(':visible') && !$('#mobile-card-view')
                                .hasClass('hidden')) {
                                renderMobileCards();
                            }
                        },
                        error: function(xhr, error, thrown) {
                            // console.error('DataTables Error:', error, thrown);
                            $('#table-loading').html(
                                '<div class="text-center py-8"><div class="text-red-500 dark:text-red-400 p-4">Error loading data. Please refresh the page.</div></div>'
                            );
                        }
                    });

                    // console.log('DataTable created successfully');

                } catch (error) {
                    // console.error('Error initializing DataTable:', error);
                    $('#table-loading').html(
                        '<div class="text-center py-8"><div class="text-red-500 dark:text-red-400 p-4">Error initializing table. Please check console.</div></div>'
                    );
                }
            }

            // Function to apply table styles
            function applyTableStyles() {
                // Apply styles to DataTables elements
                $('.dataTables_length select').addClass(
                    'rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-theme-xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white'
                );

                $('.dataTables_filter input').addClass(
                    'rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-500 focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-400'
                );

                $('.dataTables_paginate .paginate_button').addClass(
                    'inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-gray-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                );

                $('.dataTables_paginate .paginate_button.current').addClass(
                    '!bg-brand-500 !text-white !border-brand-500 hover:!bg-brand-600 dark:hover:!bg-brand-600'
                );

                $('.dataTables_paginate .paginate_button.disabled').addClass(
                    'opacity-50 cursor-not-allowed hover:bg-white dark:hover:bg-gray-800'
                );
            }

            // Function to render mobile cards
            function renderMobileCards() {
                if (!table) return;

                try {
                    const data = table.rows().data().toArray();
                    const container = $('#mobile-drivers-container');
                    container.empty();

                    if (data.length === 0) {
                        container.html(
                            '<div class="text-center py-8 text-gray-500 dark:text-gray-400">No drivers found</div>'
                        );
                        return;
                    }

                    data.forEach((row, index) => {
                        let statusClasses =
                            'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium uppercase tracking-wider whitespace-nowrap';
                        let dotClass = 'h-2 w-2 rounded-full';
                        let statusText = 'Active';

                        if (row.status === 'inactive') {
                            statusClasses +=
                                ' bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
                            dotClass += ' bg-red-500';
                            statusText = 'Inactive';
                        } else if (row.status === 'on_leave') {
                            statusClasses +=
                                ' bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
                            dotClass += ' bg-yellow-500';
                            statusText = 'On Leave';
                        } else {
                            statusClasses +=
                                ' bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
                            dotClass += ' bg-green-500';
                        }

                        const card = `
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
                                <div class="flex justify-between items-start mb-3 pb-3 border-b border-gray-200 dark:border-gray-700">
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">${row.full_name || 'N/A'}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Driver #${row.DT_RowIndex || index + 1}</p>
                                    </div>
                                    <span class="${statusClasses}">
                                        <span class="${dotClass}"></span>
                                        ${statusText}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">State</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${row.state || 'N/A'}</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">License Exp.</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${row.license_expiration_date || 'N/A'}</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Medical Exp.</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${row.medical_certificate_expiration_date || 'N/A'}</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Hire Date</span>
                                        <span class="font-medium text-gray-900 dark:text-white">${row.hired_at || 'N/A'}</span>
                                    </div>
                                </div>
                                <div class="mt-4 flex gap-2">
                                    ${row.action || '<span class="text-sm text-gray-500 dark:text-gray-400 italic">No actions available</span>'}
                                </div>
                            </div>
                        `;
                        container.append(card);
                    });
                } catch (error) {
                    // console.error('Error rendering mobile cards:', error);
                    $('#mobile-drivers-container').html(
                        '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4"><div class="text-red-600 dark:text-red-400">Error displaying data</div></div>'
                    );
                }
            }

            // Initialize DataTable
            initializeDataTable();

            // Debounced search for better performance
            let searchTimeout;
            $('#driver-search').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (table) {
                        table.search(this.value).draw();
                    }
                }, 500);
            });

            // Clear search on escape key
            $('#driver-search').on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $(this).val('');
                    if (table) {
                        table.search('').draw();
                    }
                }
            });

            // Handle window resize
            let resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    const newIsMobile = window.innerWidth < 640;

                    if (newIsMobile !== isMobileView) {
                        isMobileView = newIsMobile;

                        if (isMobileView) {
                            $('#status-legend').removeClass('hidden');
                        } else {
                            $('#status-legend').addClass('hidden');
                        }
                    }

                    if (table) {
                        table.columns.adjust();
                    }
                }, 250);
            });

            // Initial check for mobile
            if (isMobileView) {
                $('#status-legend').removeClass('hidden');
            }
        });

        // Enhanced delete driver function with SweetAlert
        function deleteDriver(id, name) {
            Swal.fire({
                title: 'Delete Driver?',
                html: `Are you sure you want to delete <strong>${name}</strong>?<br>This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-lg px-4 py-2',
                    cancelButton: 'rounded-lg px-4 py-2'
                },
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`/admin/driver/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(response.statusText);
                            }
                            return response.json();
                        })
                        .catch(error => {
                            Swal.showValidationMessage(
                                `Request failed: ${error}`
                            );
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'Driver has been deleted.',
                        icon: 'success',
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'rounded-2xl',
                            confirmButton: 'rounded-lg px-4 py-2'
                        }
                    }).then(() => {
                        if (typeof table !== 'undefined') {
                            table.ajax.reload(null, false);
                        } else {
                            location.reload();
                        }
                    });
                }
            });
        }
    </script>
@endpush
