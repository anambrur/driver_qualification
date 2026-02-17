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
