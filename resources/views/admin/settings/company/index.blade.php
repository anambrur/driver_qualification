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
                <div class="flex items-center space-x-2">
                    
                    <a href="{{ route('admin.settings.company.create') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                        <i class="fas fa-plus mr-2"></i>Add New Company
                    </a>
                </div>
            </div>
        </div>

        <!-- Companies Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col justify-between sm:flex-row sm:items-center">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        <i class="fas fa-list mr-2"></i>Company List
                    </h3>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 sm:mt-0">
                        Total: <span id="total-records" class="font-medium">Loading...</span> companies
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">
                <div class="p-5 sm:p-6">
                    <div class="overflow-hidden">
                        <table id="companies-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        #</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Logo</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Company Name</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Email</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Phone</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        City</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Status</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Apply URL</th>
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

        .copy-url-btn {
            transition: all 0.2s ease;
        }

        .copy-url-btn:hover {
            transform: scale(1.1);
        }

        .copy-success {
            color: #10b981 !important;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#companies-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.settings.company') }}',
                    dataSrc: function(json) {
                        // Update total records
                        $('#total-records').text(json.recordsTotal);
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX Error:', error);
                        console.error('Response:', xhr.responseText);
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
                    // Column 1: Logo
                    {
                        data: 'logo',
                        name: 'logo',
                        orderable: false,
                        searchable: false,
                        width: '8%',
                        className: 'text-center'
                    },
                    // Column 2: Company Name
                    {
                        data: 'company_name',
                        name: 'company_name',
                        width: '15%',
                        searchable: true,
                        orderable: true
                    },
                    // Column 3: User Email
                    {
                        data: 'user_email',
                        name: 'user.email',
                        width: '15%',
                        searchable: true,
                        orderable: true
                    },
                    // Column 4: Phone
                    {
                        data: 'phone',
                        name: 'phone',
                        width: '12%',
                        searchable: true,
                        orderable: true
                    },
                    // Column 5: City
                    {
                        data: 'city',
                        name: 'city',
                        width: '12%',
                        searchable: true,
                        orderable: true
                    },
                    // Column 6: Status
                    {
                        data: 'status',
                        name: 'status',
                        width: '10%',
                        searchable: true,
                        orderable: true,
                        className: 'text-center'
                    },
                    // Column 7: Apply URL
                    {
                        data: 'apply_url',
                        name: 'apply_url',
                        orderable: false,
                        searchable: false,
                        width: '12%',
                        className: 'text-center'
                    },
                    // Column 8: Actions
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '11%',
                        className: 'text-center'
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search companies...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No companies found",
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
                    // console.log('✅ DataTable initialized successfully');

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
        });

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

        // Copy URL function with visual feedback
        function copyApplyUrl(url) {
            // Create a temporary input element
            const tempInput = document.createElement('input');
            tempInput.value = url;
            document.body.appendChild(tempInput);

            // Select and copy
            tempInput.select();
            tempInput.setSelectionRange(0, 99999); // For mobile devices

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    // Find and update the copy button
                    $('button.copy-url-btn').each(function() {
                        if ($(this).attr('onclick') && $(this).attr('onclick').includes(url)) {
                            const icon = $(this).find('i');
                            icon.removeClass('fa-copy').addClass('fa-check copy-success');

                            // Reset after 2 seconds
                            setTimeout(() => {
                                icon.removeClass('fa-check copy-success').addClass('fa-copy');
                            }, 2000);
                        }
                    });

                    showToast('URL copied to clipboard!', 'success');
                } else {
                    showToast('Failed to copy URL', 'error');
                }
            } catch (err) {
                console.error('Copy failed:', err);

                // Fallback using Clipboard API
                navigator.clipboard.writeText(url).then(() => {
                    showToast('URL copied to clipboard!', 'success');
                }).catch(() => {
                    showToast('Failed to copy URL', 'error');
                });
            }

            // Remove temporary input
            document.body.removeChild(tempInput);
        }

        // Alternative copy function for modern browsers
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Copied to clipboard!', 'success');
                }).catch(err => {
                    console.error('Clipboard API failed:', err);
                    copyFallback(text);
                });
            } else {
                copyFallback(text);
            }
        }

        function copyFallback(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                showToast('Copied to clipboard!', 'success');
            } catch (err) {
                console.error('Copy failed:', err);
                showToast('Failed to copy to clipboard', 'error');
            }

            document.body.removeChild(textArea);
        }

        // Delete company function
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
                },
                buttonsStyling: false,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait while we delete the company.',
                        allowOutsideClick: false,
                        showConfirmButton: false,
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
                                    showConfirmButton: false,
                                    showClass: {
                                        popup: 'animate__animated animate__fadeIn'
                                    }
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

        // Quick view apply URL function
        function viewApplyUrl(companyId, companyName, url) {
            Swal.fire({
                title: `<strong>Apply URL for ${companyName}</strong>`,
                html: `
                <div class="text-left">
                    <p class="mb-3 text-gray-600 dark:text-gray-300">Copy or visit the application URL:</p>
                    <div class="flex items-center mb-4">
                        <input type="text" 
                               id="apply-url-input" 
                               value="${url}" 
                               readonly
                               class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-brand-400">
                        <button onclick="copySwalUrl()" 
                                id="swal-copy-btn"
                                class="px-5 py-3 bg-brand-500 text-white rounded-r-lg hover:bg-brand-600 transition-colors">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="flex space-x-3">
                        <a href="${url}" 
                           target="_blank" 
                           class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Open Link
                        </a>
                        <button onclick="Swal.close()" 
                                class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <i class="fas fa-times mr-2"></i>
                            Close
                        </button>
                    </div>
                </div>
            `,
                showCloseButton: true,
                showConfirmButton: false,
                width: '500px',
                customClass: {
                    popup: 'rounded-2xl'
                }
            });
        }

        // Copy function for SweetAlert modal
        function copySwalUrl() {
            const copyText = document.getElementById("apply-url-input");
            copyText.select();
            copyText.setSelectionRange(0, 99999);

            const copyBtn = document.getElementById("swal-copy-btn");
            const icon = copyBtn.querySelector('i');

            // Try to copy using modern API first
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(copyText.value).then(() => {
                    icon.className = 'fas fa-check';
                    copyBtn.classList.remove('bg-brand-500', 'hover:bg-brand-600');
                    copyBtn.classList.add('bg-green-500');

                    setTimeout(() => {
                        icon.className = 'fas fa-copy';
                        copyBtn.classList.remove('bg-green-500');
                        copyBtn.classList.add('bg-brand-500', 'hover:bg-brand-600');
                    }, 2000);
                });
            } else {
                // Fallback
                try {
                    document.execCommand('copy');
                    icon.className = 'fas fa-check';
                    copyBtn.classList.remove('bg-brand-500', 'hover:bg-brand-600');
                    copyBtn.classList.add('bg-green-500');

                    setTimeout(() => {
                        icon.className = 'fas fa-copy';
                        copyBtn.classList.remove('bg-green-500');
                        copyBtn.classList.add('bg-brand-500', 'hover:bg-brand-600');
                    }, 2000);
                } catch (err) {
                    console.error('Copy failed:', err);
                }
            }
        }

        // Make functions available globally
        window.copyApplyUrl = copyApplyUrl;
        window.copyToClipboard = copyToClipboard;
        window.viewApplyUrl = viewApplyUrl;
    </script>
@endpush
