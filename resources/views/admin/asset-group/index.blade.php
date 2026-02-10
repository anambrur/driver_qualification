@extends('layouts.main-layout')

@section('title', 'Asset Groups')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Asset Groups</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage asset groups with vehicle and trailer assignments
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="refresh-table"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <button onclick="showCreateModal()"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                        <i class="fas fa-plus mr-2"></i>Add New Asset Group
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="p-4 mb-6 bg-white rounded-lg shadow-sm dark:bg-gray-800">
            <div class="flex flex-col space-y-4 md:flex-row md:space-y-0 md:space-x-4 md:items-end">
                <!-- Quick Search -->
                <div class="flex-1">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Quick Search
                    </label>
                    <div class="relative">
                        <input type="text" id="quick-search"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            placeholder="Search by group name, driver name, email, phone...">
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="w-full md:w-48">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Status
                    </label>
                    <select id="filter-status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="deleted">Deleted</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-2">
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
        </div>

        <!-- Asset Groups Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col justify-between sm:flex-row sm:items-center">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        <i class="fas fa-users mr-2"></i>Asset Groups
                    </h3>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 sm:mt-0">
                        Total: <span id="total-records" class="font-medium">Loading...</span> groups
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">
                <div class="p-5 sm:p-6">
                    <div class="overflow-x-auto">
                        <table id="asset-groups-table"
                            class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        #
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Group Information
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Drivers Information
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Assigned Assets
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
    <div id="assetGroupModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div
                class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full dark:bg-gray-800">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 id="modalTitle" class="text-lg font-medium leading-6 text-gray-900 dark:text-white"></h3>
                            <div class="mt-4">
                                <form id="assetGroupForm">
                                    @csrf
                                    <input type="hidden" id="asset_group_id" name="id">

                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <!-- Column 1: Basic Information & Drivers -->
                                        <div class="space-y-6">
                                            <!-- Basic Information -->
                                            <div>
                                                <h4
                                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                                    <i class="fas fa-info-circle mr-2"></i>Basic Information
                                                </h4>

                                                <!-- Group Name -->
                                                <div class="mb-4">
                                                    <label for="group_name"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Group Name <span class="text-red-500">*</span>
                                                        <span class="text-xs text-gray-500 ml-2">(Auto-generated from
                                                            vehicle unit number)</span>
                                                    </label>
                                                    <input type="text" name="group_name" id="group_name" required
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        placeholder="Will auto-generate as GR#[vehicle unit no] when vehicle is selected">
                                                    <div id="group_name_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>

                                                <!-- Status -->
                                                <div class="mb-4">
                                                    <label for="status"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Status <span class="text-red-500">*</span>
                                                    </label>
                                                    <select name="status" id="status" required
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">

                                                        <option value="active">Active</option>
                                                        <option value="inactive">Inactive</option>
                                                    </select>
                                                    <div id="status_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                            </div>

                                            <!-- Primary Driver Information -->
                                            <div>
                                                <h4
                                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                                    <i class="fas fa-user mr-2"></i>Primary Driver Information
                                                </h4>

                                                <div class="space-y-4">
                                                    <!-- Primary Driver -->

                                                    <input type="hidden" name="driver_id" id="driver_id">
                                                    <div>
                                                        <label for="primary_driver_name"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Primary Driver <span class="text-red-500">*</span>
                                                        </label>
                                                        <select name="primary_driver_name" id="primary_driver_name"
                                                            required
                                                            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                            <option value="">Select Driver</option>
                                                            @foreach ($drivers as $driver)
                                                                <option value="{{ $driver->id }}">
                                                                    {{ $driver->first_name }} {{ $driver->last_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <div id="primary_driver_name_error"
                                                            class="mt-1 text-sm text-red-600">
                                                        </div>
                                                    </div>

                                                    <!-- Primary Driver Phone & Email -->
                                                    <!-- Primary Driver Phone & Email -->
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        <div>
                                                            <label for="primary_driver_phone"
                                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                Phone Number
                                                            </label>
                                                            <input type="text" name="primary_driver_phone"
                                                                id="primary_driver_phone" readonly
                                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm bg-gray-50 cursor-not-allowed focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-brand-400"
                                                                placeholder="Phone will auto-fill when driver is selected">
                                                            <div id="primary_driver_phone_error"
                                                                class="mt-1 text-sm text-red-600"></div>
                                                        </div>
                                                        <div>
                                                            <label for="primary_driver_email"
                                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                Email Address
                                                            </label>
                                                            <input type="email" name="primary_driver_email"
                                                                id="primary_driver_email" readonly
                                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm bg-gray-50 cursor-not-allowed focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-brand-400"
                                                                placeholder="Email will auto-fill when driver is selected">
                                                            <div id="primary_driver_email_error"
                                                                class="mt-1 text-sm text-red-600"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Column 2: Second Driver & Asset Assignment -->
                                        <div class="space-y-6">
                                            <!-- Second Driver Information -->
                                            <div>
                                                <h4
                                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                                    <i class="fas fa-user-friends mr-2"></i>Second Driver Information
                                                    (Optional)
                                                </h4>

                                                <div class="space-y-4">
                                                    <!-- Second Driver Name -->
                                                    <div>
                                                        <label for="second_driver_name"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Second Driver Name
                                                        </label>
                                                        <input type="text" name="second_driver_name"
                                                            id="second_driver_name"
                                                            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            placeholder="Enter second driver name">
                                                        <div id="second_driver_name_error"
                                                            class="mt-1 text-sm text-red-600"></div>
                                                    </div>

                                                    <!-- Second Driver Phone & Email -->
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        <div>
                                                            <label for="second_driver_phone"
                                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                Phone Number
                                                            </label>
                                                            <input type="text" name="second_driver_phone"
                                                                id="second_driver_phone"
                                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                                placeholder="(123) 456-7890">
                                                            <div id="second_driver_phone_error"
                                                                class="mt-1 text-sm text-red-600"></div>
                                                        </div>
                                                        <div>
                                                            <label for="second_driver_email"
                                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                Email Address
                                                            </label>
                                                            <input type="email" name="second_driver_email"
                                                                id="second_driver_email"
                                                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                                placeholder="driver2@example.com">
                                                            <div id="second_driver_email_error"
                                                                class="mt-1 text-sm text-red-600"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Asset Assignment -->
                                            <div>
                                                <h4
                                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2 mb-4">
                                                    <i class="fas fa-truck mr-2"></i>Asset Assignment
                                                </h4>

                                                <div class="space-y-4">
                                                    <!-- Vehicle Assignment -->
                                                    <div>
                                                        <label for="vehicle_id"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Vehicle <span class="text-red-500">*</span>
                                                        </label>
                                                        <select name="vehicle_id" id="vehicle_id" required
                                                            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                            <option value="">Select Vehicle</option>
                                                            @foreach ($vehicles as $vehicle)
                                                                <option value="{{ $vehicle->id }}">
                                                                    {{ $vehicle->unit_no }} - {{ $vehicle->year }}
                                                                    {{ $vehicle->make }} {{ $vehicle->model }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <div id="vehicle_id_error" class="mt-1 text-sm text-red-600">
                                                        </div>
                                                    </div>

                                                    <!-- Trailer Assignment -->
                                                    <div>
                                                        <label for="trailer_id"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Trailer (Optional)
                                                        </label>
                                                        <select name="trailer_id" id="trailer_id"
                                                            class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                            <option value="">Select Trailer (Optional)</option>
                                                            @foreach ($trailers as $trailer)
                                                                <option value="{{ $trailer->id }}">
                                                                    {{ $trailer->unit_no }} - {{ $trailer->year }}
                                                                    {{ $trailer->make }} {{ $trailer->model }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <div id="trailer_id_error" class="mt-1 text-sm text-red-600">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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

        /* Form styles */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Scrollbar styling */
        .overflow-x-auto {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }

        .overflow-x-auto::-webkit-scrollbar {
            height: 8px;
        }

        .overflow-x-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .dark .overflow-x-auto {
            scrollbar-color: #4b5563 #1f2937;
        }

        .dark .overflow-x-auto::-webkit-scrollbar-track {
            background: #374151;
        }

        .dark .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #6b7280;
        }

        .dark .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Global variables for asset data
        let vehicleData = {};
        let trailerData = {};

        // Global helper functions
        function clearVehicleDetails() {
            $('#vehicle-details-container').remove();
        }

        function clearTrailerDetails() {
            $('#trailer-details-container').remove();
        }

        function resetFormErrors() {
            $('.text-red-600').html('');
        }

        function resetForm() {
            $('#assetGroupForm')[0].reset();
            $('#asset_group_id').val('');
            resetFormErrors();
            clearVehicleDetails();
            clearTrailerDetails();
            // Also clear the readonly fields
            $('#primary_driver_phone').val('');
            $('#primary_driver_email').val('');
            // Clear the group name
            $('#group_name').val('');
            // Clear driver_id
            $('#driver_id').val('');
        }

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

        // Functions that need to be accessible from HTML onclick attributes
        window.showCreateModal = function() {
            $('#modalTitle').text('Add New Asset Group');
            $('#submitText').text('Save');
            $('#asset_group_id').val('');
            resetForm();
            $('#assetGroupModal').removeClass('hidden');
            $('#group_name').focus();
        }

        window.clearVehicleSelection = function() {
            $('#vehicle_id').val('');
            clearVehicleDetails();
            // Also clear the auto-generated group name
            $('#group_name').val('');
        }

        window.clearTrailerSelection = function() {
            $('#trailer_id').val('');
            clearTrailerDetails();
        }

        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#asset-groups-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.asset-group.index') }}',
                    type: 'GET',
                    data: function(d) {
                        // Add filter parameters
                        d.search_text = $('#quick-search').val();
                        d.status = $('#filter-status').val();
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX Error:', error);
                        showToast('Failed to load asset groups', 'error');
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
                        data: 'group_info',
                        name: 'group_name',
                        orderable: true,
                        searchable: true,
                        width: '20%'
                    },
                    {
                        data: 'drivers_info',
                        name: 'primary_driver_name',
                        orderable: false,
                        searchable: false,
                        width: '25%'
                    },
                    {
                        data: 'assets_info',
                        name: 'vehicle_id',
                        orderable: false,
                        searchable: false,
                        width: '25%'
                    },
                    {
                        data: 'status',
                        name: 'deleted_at',
                        orderable: true,
                        searchable: false,
                        width: '10%',
                        className: 'text-center'
                    },
                    {
                        data: 'created_at_formatted',
                        name: 'created_at',
                        orderable: true,
                        searchable: false,
                        width: '12%',
                        className: 'text-center'
                    },
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
                    searchPlaceholder: "Search asset groups...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No asset groups found",
                    zeroRecords: "No matching records found",
                    processing: '<div class="spinner-border text-brand-500" role="status"></div> Loading...',
                    paginate: {
                        first: '<i class="fas fa-angle-double-left"></i>',
                        last: '<i class="fas fa-angle-double-right"></i>',
                        next: '<i class="fas fa-angle-right"></i>',
                        previous: '<i class="fas fa-angle-left"></i>'
                    }
                },
                responsive: false,
                scrollX: true,
                autoWidth: true,
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
                $('#assetGroupModal').addClass('hidden');
                resetForm();
            });

            // Close modal when clicking outside
            $(document).on('click', function(event) {
                if ($(event.target).hasClass('fixed')) {
                    $('#assetGroupModal').addClass('hidden');
                    resetForm();
                }
            });

            // Submit form
            $('#submitForm').on('click', function() {
                submitAssetGroupForm();
            });

            // Apply filters
            window.applyFilters = function() {
                table.ajax.reload();
            }

            // Clear filters
            window.clearFilters = function() {
                $('#quick-search').val('');
                $('#filter-status').val('');
                table.ajax.reload();
            }

            // Quick search on Enter key
            $('#quick-search').on('keyup', function(e) {
                if (e.key === 'Enter') {
                    applyFilters();
                }
            });

            // --- ASSET GROUP FORM SPECIFIC LOGIC ---

            // Load vehicle and trailer data
            function loadAssetData() {
                $.ajax({
                    url: '{{ route('admin.asset-group.get-dropdown-data') }}',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            // Store vehicle data with ID as key for quick lookup
                            response.vehicles.forEach(function(vehicle) {
                                vehicleData[vehicle.id] = vehicle;
                            });

                            // Store trailer data with ID as key for quick lookup
                            response.trailers.forEach(function(trailer) {
                                trailerData[trailer.id] = trailer;
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to load asset data:', xhr);
                    }
                });
            }

            // Call this when page loads
            loadAssetData();

            // Driver selection change handler
            $('#primary_driver_name').on('change', function() {
                const driverId = $(this).val();

                if (!driverId) {
                    // Clear fields if no driver selected
                    $('#primary_driver_phone').val('');
                    $('#primary_driver_email').val('');
                    return;
                }

                // Get driver data via AJAX
                $.ajax({
                    url: '/admin/driver/' + driverId + '/details',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            // Fill in driver details
                            $('#primary_driver_phone').val(response.data.main_phone || response
                                .data.alt_phone || '');
                            $('#primary_driver_email').val(response.data.email || '');
                            $('#driver_id').val(driverId);
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to load driver details:', xhr);
                        showToast('Failed to load driver details', 'error');
                    }
                });
            });

            // Vehicle selection change handler
            $('#vehicle_id').on('change', function() {
                const vehicleId = $(this).val();

                if (!vehicleId) {
                    // Clear vehicle details if no vehicle selected
                    clearVehicleDetails();
                    // Also clear the auto-generated group name
                    $('#group_name').val('');
                    return;
                }

                if (vehicleData[vehicleId]) {
                    displayVehicleDetails(vehicleData[vehicleId]);
                    autoGenerateGroupName(vehicleData[vehicleId]);
                } else {
                    // If not in cache, fetch from server
                    fetchVehicleDetails(vehicleId);
                }
            });

            // Function to auto-generate group name from vehicle unit number
            function autoGenerateGroupName(vehicle) {
                if (vehicle && vehicle.unit_no) {
                    // Format: GR#[vehicle unit_no] - exactly like in the screenshot
                    const groupName = `GR#${vehicle.unit_no}`;
                    $('#group_name').val(groupName);
                } else {
                    $('#group_name').val('');
                }
            }

            // Trailer selection change handler
            $('#trailer_id').on('change', function() {
                const trailerId = $(this).val();

                if (!trailerId) {
                    // Clear trailer details if no trailer selected
                    clearTrailerDetails();
                    return;
                }

                if (trailerData[trailerId]) {
                    displayTrailerDetails(trailerData[trailerId]);
                } else {
                    // If not in cache, fetch from server
                    fetchTrailerDetails(trailerId);
                }
            });

            // Function to display vehicle details like screenshot
            function displayVehicleDetails(vehicle) {
                // Create or update the vehicle details container
                let $container = $('#vehicle-details-container');

                if ($container.length === 0) {
                    // Create container if it doesn't exist
                    $container = $('<div>').attr('id', 'vehicle-details-container')
                        .addClass(
                            'mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700'
                        );
                    $('#vehicle_id').after($container);
                }

                // Build the details display similar to screenshot
                const detailsHtml = `
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-lg font-semibold text-gray-800 dark:text-white">${vehicle.unit_no || vehicle.id}</span>
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full dark:bg-blue-900 dark:text-blue-300">Power Unit</span>
                        </div>
                        <div class="mt-1 space-y-1">
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-car mr-2"></i>
                                <span>${vehicle.year || ''} ${vehicle.make || ''} ${vehicle.model || ''}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-tag mr-2"></i>
                                <span>License Plate: ${vehicle.license_plate || 'License plate number'}</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="clearVehicleSelection()" 
                        class="px-3 py-1 text-sm text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30">
                        Remove
                    </button>
                </div>
            `;

                $container.html(detailsHtml);
            }

            // Function to display trailer details like screenshot
            function displayTrailerDetails(trailer) {
                // Create or update the trailer details container
                let $container = $('#trailer-details-container');

                if ($container.length === 0) {
                    // Create container if it doesn't exist
                    $container = $('<div>').attr('id', 'trailer-details-container')
                        .addClass(
                            'mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700'
                        );
                    $('#trailer_id').after($container);
                }

                // Get trailer type from your data or use default
                const trailerType = trailer.trailer_type || trailer.trailer_type || 'Tanker';

                // Build the details display similar to screenshot
                const detailsHtml = `
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-lg font-semibold text-gray-800 dark:text-white">${trailer.unit_no || trailer.id}</span>
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full dark:bg-green-900 dark:text-green-300">Trailer</span>
                        </div>
                        <div class="mt-1 space-y-1">
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-trailer mr-2"></i>
                                <span>Trailer Type: ${trailerType}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-tag mr-2"></i>
                                <span>License Plate: ${trailer.license_plate || 'License plate number'}</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="clearTrailerSelection()" 
                        class="px-3 py-1 text-sm text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30">
                        Remove
                    </button>
                </div>
            `;

                $container.html(detailsHtml);
            }

            // Function to fetch vehicle details from server
            function fetchVehicleDetails(vehicleId) {
                $.ajax({
                    url: '/admin/vehicle/' + vehicleId + '/details',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            vehicleData[vehicleId] = response.data;
                            displayVehicleDetails(response.data);
                            autoGenerateGroupName(response.data);
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to load vehicle details:', xhr);
                        showToast('Failed to load vehicle details', 'error');

                        // Create a basic display even if fetch fails
                        const basicVehicle = {
                            unit_no: vehicleId,
                            year: '',
                            make: '',
                            model: '',
                            license_plate: 'License plate number'
                        };
                        displayVehicleDetails(basicVehicle);
                        autoGenerateGroupName(basicVehicle);
                    }
                });
            }

            // Function to fetch trailer details from server
            function fetchTrailerDetails(trailerId) {
                $.ajax({
                    url: '/admin/trailer/' + trailerId + '/details',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            trailerData[trailerId] = response.data;
                            displayTrailerDetails(response.data);
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to load trailer details:', xhr);
                        showToast('Failed to load trailer details', 'error');

                        // Create a basic display even if fetch fails
                        const basicTrailer = {
                            unit_no: trailerId,
                            trailer_type: 'Tanker',
                            license_plate: 'License plate number'
                        };
                        displayTrailerDetails(basicTrailer);
                    }
                });
            }
        });

        // Functions that need to be accessible globally but defined outside document.ready
        window.editAssetGroup = function(id) {
            resetFormErrors();

            // Show loading
            $('#modalTitle').html('Loading...');
            $('#submitText').html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading');
            $('#assetGroupModal').removeClass('hidden');

            $.ajax({
                url: '{{ route('admin.asset-group.edit', ':id') }}'.replace(':id', id),
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#modalTitle').text('Edit Asset Group');
                        $('#submitText').text('Update');
                        $('#asset_group_id').val(id);

                        // Fill form fields
                        $.each(response.data, function(key, value) {
                            if ($('#' + key).length) {
                                if ($('#' + key).is('select')) {
                                    $('#' + key).val(value);

                                    // Trigger change events for select fields to load details
                                    if (key === 'vehicle_id' && value) {
                                        setTimeout(() => {
                                            $('#vehicle_id').trigger('change');
                                        }, 100);
                                    }
                                    if (key === 'trailer_id' && value) {
                                        setTimeout(() => {
                                            $('#trailer_id').trigger('change');
                                        }, 100);
                                    }
                                    if (key === 'primary_driver_name' && value) {
                                        setTimeout(() => {
                                            $('#primary_driver_name').trigger('change');
                                        }, 100);
                                    }
                                } else {
                                    $('#' + key).val(value || '');
                                }
                            }
                        });

                        // If group name already exists, don't auto-generate it
                        // This preserves the existing group name during edit
                        if (!response.data.group_name && response.data.vehicle_id) {
                            // Auto-generate group name if not already set
                            setTimeout(() => {
                                if (typeof vehicleData !== 'undefined' && vehicleData[response.data
                                        .vehicle_id]) {
                                    autoGenerateGroupName(vehicleData[response.data.vehicle_id]);
                                } else {
                                    fetchVehicleDetails(response.data.vehicle_id);
                                }
                            }, 200);
                        }
                    } else {
                        showToast('Failed to load asset group data', 'error');
                        $('#assetGroupModal').addClass('hidden');
                    }
                },
                error: function(xhr) {
                    console.error('Edit error:', xhr);
                    showToast('Failed to load asset group data', 'error');
                    $('#assetGroupModal').addClass('hidden');
                }
            });
        }

        window.deleteAssetGroup = function(id, groupName) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this! Asset Group: " + groupName,
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
                        url: '{{ route('admin.asset-group.destroy', ':id') }}'.replace(':id', id),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                $('#asset-groups-table').DataTable().ajax.reload();
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
                            let errorMessage = 'Failed to delete asset group.';
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

        window.restoreAssetGroup = function(id, groupName) {
            Swal.fire({
                title: 'Restore Asset Group',
                text: "Are you sure you want to restore asset group: " + groupName + "?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, restore it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Restoring...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: '{{ route('admin.asset-group.restore', ':id') }}'.replace(':id', id),
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'POST'
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                $('#asset-groups-table').DataTable().ajax.reload();
                                Swal.fire({
                                    title: 'Restored!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonColor: '#10b981',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.close();
                            let errorMessage = 'Failed to restore asset group.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                title: 'Error!',
                                text: errorMessage,
                                icon: 'error',
                                confirmButtonColor: '#10b981'
                            });
                        }
                    });
                }
            });
        }

        function submitAssetGroupForm() {
            var formData = $('#assetGroupForm').serialize();
            var url = $('#asset_group_id').val() ?
                '{{ route('admin.asset-group.update', ':id') }}'.replace(':id', $('#asset_group_id').val()) :
                '{{ route('admin.asset-group.store') }}';
            var method = $('#asset_group_id').val() ? 'PUT' : 'POST';

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
                        $('#assetGroupModal').addClass('hidden');
                        $('#asset-groups-table').DataTable().ajax.reload();
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

        // Add these functions to window object for editAssetGroup to use
        window.autoGenerateGroupName = function(vehicle) {
            if (vehicle && vehicle.unit_no) {
                // Format: GR#[vehicle unit_no] - exactly like in the screenshot
                const groupName = `GR#${vehicle.unit_no}`;
                $('#group_name').val(groupName);
            } else {
                $('#group_name').val('');
            }
        };

        window.fetchVehicleDetails = function(vehicleId) {
            $.ajax({
                url: '/admin/vehicle/' + vehicleId + '/details',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        vehicleData[vehicleId] = response.data;
                        // Note: displayVehicleDetails is inside document.ready, so we need to check if it exists
                        if (typeof displayVehicleDetails !== 'undefined') {
                            displayVehicleDetails(response.data);
                        }
                        autoGenerateGroupName(response.data);
                    }
                },
                error: function(xhr) {
                    console.error('Failed to load vehicle details:', xhr);
                    showToast('Failed to load vehicle details', 'error');

                    // Create a basic display even if fetch fails
                    const basicVehicle = {
                        unit_no: vehicleId,
                        year: '',
                        make: '',
                        model: '',
                        license_plate: 'License plate number'
                    };
                    if (typeof displayVehicleDetails !== 'undefined') {
                        displayVehicleDetails(basicVehicle);
                    }
                    autoGenerateGroupName(basicVehicle);
                }
            });
        };
    </script>
@endpush
