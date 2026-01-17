@extends('layouts.main-layout')

@section('title', 'Vehicles')

@section('content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Vehicles</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage all vehicles in your fleet</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="refresh-table"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <button onclick="showCreateModal()"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                        <i class="fas fa-plus mr-2"></i>Add New Vehicle
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
                            placeholder="Search by Unit No, VIN, Make, Model...">
                    </div>
                </div>

                <!-- Vehicle Type Filter -->
                <div class="w-full md:w-48">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Vehicle Type
                    </label>
                    <select id="filter-type"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">All Types</option>
                        @foreach ($vehicleTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
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

        <!-- Vehicles Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col justify-between sm:flex-row sm:items-center">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        <i class="fas fa-truck mr-2"></i>Vehicle Fleet
                    </h3>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 sm:mt-0">
                        Total: <span id="total-records" class="font-medium">Loading...</span> vehicles
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">
                <div class="p-5 sm:p-6">
                    <div class="overflow-hidden">
                        <table id="vehicles-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        #
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Vehicle Information
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Type & Group
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Fuel & Odometer
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
    <div id="vehicleModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
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
                                <form id="vehicleForm">
                                    @csrf
                                    <input type="hidden" id="vehicle_id" name="id">

                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <!-- Column 1: Basic Information -->
                                        <div class="space-y-4">
                                            <h4
                                                class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2">
                                                <i class="fas fa-info-circle mr-2"></i>Basic Information
                                            </h4>

                                            <!-- Unit No -->
                                            <div>
                                                <label for="unit_no"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Unit Number <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" name="unit_no" id="unit_no" required
                                                    class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                <div id="unit_no_error" class="mt-1 text-sm text-red-600"></div>
                                            </div>

                                            <!-- VIN -->
                                            <div>
                                                <label for="vin"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    VIN (17 characters) <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" name="vin" id="vin" required maxlength="17"
                                                    class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                <div id="vin_error" class="mt-1 text-sm text-red-600"></div>
                                            </div>

                                            <!-- Year, Make, Model -->
                                            <div class="grid grid-cols-3 gap-3">
                                                <div>
                                                    <label for="year"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Year <span class="text-red-500">*</span>
                                                    </label>
                                                    <input type="number" name="year" id="year" required
                                                        min="1900" max="{{ date('Y') + 1 }}"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="year_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="make"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Make <span class="text-red-500">*</span>
                                                    </label>
                                                    <input type="text" name="make" id="make" required
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="make_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="model"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Model <span class="text-red-500">*</span>
                                                    </label>
                                                    <input type="text" name="model" id="model" required
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="model_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                            </div>

                                            <!-- Vehicle Type & Group -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="vehicle_type_id"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Vehicle Type
                                                    </label>
                                                    <select name="vehicle_type_id" id="vehicle_type_id"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                        <option value="">Select Type</option>
                                                        @foreach ($vehicleTypes as $type)
                                                            <option value="{{ $type->id }}">{{ $type->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div id="vehicle_type_id_error" class="mt-1 text-sm text-red-600">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label for="vehicle_group_id"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Vehicle Group
                                                    </label>
                                                    <select name="vehicle_group_id" id="vehicle_group_id"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                        <option value="">Select Group</option>
                                                        @foreach ($vehicleGroups as $group)
                                                            <option value="{{ $group->id }}">{{ $group->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div id="vehicle_group_id_error" class="mt-1 text-sm text-red-600">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Owned By & Color -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="owned_by"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Owned By <span class="text-red-500">*</span>
                                                    </label>
                                                    <select name="owned_by" id="owned_by" required
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                        <option value="">Select Ownership</option>
                                                        <option value="company">Company Owned</option>
                                                        <option value="lease">Leased</option>
                                                        <option value="rental">Rental</option>
                                                    </select>
                                                    <div id="owned_by_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="color"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Color
                                                    </label>
                                                    <input type="text" name="color" id="color"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="color_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Column 2: Details & Specifications -->
                                        <div class="space-y-4">
                                            <h4
                                                class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b pb-2">
                                                <i class="fas fa-cogs mr-2"></i>Details & Specifications
                                            </h4>

                                            <!-- Title No & Tire Size -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="title_no"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Title Number
                                                    </label>
                                                    <input type="text" name="title_no" id="title_no"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="title_no_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="tire_size"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Tire Size
                                                    </label>
                                                    <input type="text" name="tire_size" id="tire_size"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="tire_size_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                            </div>

                                            <!-- Odometer & GVW -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="odometer"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Odometer (miles) <span class="text-red-500">*</span>
                                                    </label>
                                                    <input type="number" name="odometer" id="odometer" required
                                                        min="0"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="odometer_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="gvw"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        GVW (lbs)
                                                    </label>
                                                    <input type="number" name="gvw" id="gvw" min="0"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="gvw_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                            </div>

                                            <!-- Fuel Type & Engine Type -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="fuel_type_id"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Fuel Type
                                                    </label>
                                                    <select name="fuel_type_id" id="fuel_type_id"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                        <option value="">Select Fuel Type</option>
                                                        @foreach ($fuelTypes as $fuel)
                                                            <option value="{{ $fuel->id }}">{{ $fuel->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div id="fuel_type_id_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="engine_type"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Engine Type
                                                    </label>
                                                    <input type="text" name="engine_type" id="engine_type"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="engine_type_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                            </div>

                                            <!-- Transmission & Suspension -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="transmission"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Transmission
                                                    </label>
                                                    <input type="text" name="transmission" id="transmission"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="transmission_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="suspension"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Suspension
                                                    </label>
                                                    <input type="text" name="suspension" id="suspension"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="suspension_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                            </div>

                                            <!-- No. of Axles & Configuration -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="no_axles"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Number of Axles
                                                    </label>
                                                    <input type="number" name="no_axles" id="no_axles" min="1"
                                                        max="10"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="no_axles_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="configuration"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Configuration
                                                    </label>
                                                    <select name="configuration" id="configuration"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                        <option value="">Select Configuration</option>
                                                        <option value="conventional">Conventional</option>
                                                        <option value="cabover">Cabover</option>
                                                    </select>
                                                    <div id="configuration_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                            </div>

                                            <!-- Wheel Base & Size Dimension -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="wheel_base"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Wheel Base (inches)
                                                    </label>
                                                    <input type="number" name="wheel_base" id="wheel_base"
                                                        min="0"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="wheel_base_error" class="mt-1 text-sm text-red-600"></div>
                                                </div>
                                                <div>
                                                    <label for="size_dimension"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Size/Dimension
                                                    </label>
                                                    <input type="text" name="size_dimension" id="size_dimension"
                                                        class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-hidden focus:ring-brand-500 focus:border-brand-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                                    <div id="size_dimension_error" class="mt-1 text-sm text-red-600">
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
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#vehicles-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.vehicle.index') }}',
                    type: 'GET',
                    data: function(d) {
                        // Add filter parameters
                        d.search_text = $('#quick-search').val();
                        d.vehicle_type_id = $('#filter-type').val();
                        d.status = $('#filter-status').val();
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX Error:', error);
                        showToast('Failed to load vehicles', 'error');
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
                        name: 'unit_no',
                        orderable: true,
                        searchable: true,
                        width: '25%'
                    },
                    {
                        data: 'type_group',
                        name: 'vehicle_type_id',
                        orderable: false,
                        searchable: false,
                        width: '20%'
                    },
                    {
                        data: 'fuel_odometer',
                        name: 'fuel_type_id',
                        orderable: false,
                        searchable: false,
                        width: '20%'
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
                    searchPlaceholder: "Search vehicles...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    emptyTable: "No vehicles found",
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
                $('#vehicleModal').addClass('hidden');
                resetForm();
            });

            // Close modal when clicking outside
            $(document).on('click', function(event) {
                if ($(event.target).hasClass('fixed')) {
                    $('#vehicleModal').addClass('hidden');
                    resetForm();
                }
            });

            // Submit form
            $('#submitForm').on('click', function() {
                submitVehicleForm();
            });

            // Apply filters
            window.applyFilters = function() {
                table.ajax.reload();
            }

            // Clear filters
            window.clearFilters = function() {
                $('#quick-search').val('');
                $('#filter-type').val('');
                $('#filter-status').val('');
                table.ajax.reload();
            }

            // Quick search on Enter key
            $('#quick-search').on('keyup', function(e) {
                if (e.key === 'Enter') {
                    applyFilters();
                }
            });
        });

        // Show create modal
        function showCreateModal() {
            $('#modalTitle').text('Add New Vehicle');
            $('#submitText').text('Save');
            $('#vehicle_id').val('');
            resetFormErrors();
            $('#vehicleModal').removeClass('hidden');
            $('#unit_no').focus();
        }

        // Show edit modal
        function editVehicle(id) {
            resetFormErrors();

            // Show loading
            $('#modalTitle').html('Loading...');
            $('#submitText').html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading');
            $('#vehicleModal').removeClass('hidden');

            $.ajax({
                url: '{{ route('admin.vehicle.edit', ':id') }}'.replace(':id', id),
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#modalTitle').text('Edit Vehicle');
                        $('#submitText').text('Update');
                        $('#vehicle_id').val(id);

                        // Fill form fields
                        $.each(response.data, function(key, value) {
                            if ($('#' + key).length) {
                                if ($('#' + key).is('select')) {
                                    $('#' + key).val(value);
                                } else {
                                    $('#' + key).val(value);
                                }
                            }
                        });
                    } else {
                        showToast('Failed to load vehicle data', 'error');
                        $('#vehicleModal').addClass('hidden');
                    }
                },
                error: function(xhr) {
                    console.error('Edit error:', xhr);
                    showToast('Failed to load vehicle data', 'error');
                    $('#vehicleModal').addClass('hidden');
                }
            });
        }

        // Submit form
        function submitVehicleForm() {
            var formData = $('#vehicleForm').serialize();
            var url = $('#vehicle_id').val() ?
                '{{ route('admin.vehicle.update', ':id') }}'.replace(':id', $('#vehicle_id').val()) :
                '{{ route('admin.vehicle.store') }}';
            var method = $('#vehicle_id').val() ? 'PUT' : 'POST';

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
                        $('#vehicleModal').addClass('hidden');
                        $('#vehicles-table').DataTable().ajax.reload();
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

        // Delete vehicle
        function deleteVehicle(id, unitNo) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this! Vehicle: " + unitNo,
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
                        url: '{{ route('admin.vehicle.destroy', ':id') }}'.replace(':id', id),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                $('#vehicles-table').DataTable().ajax.reload();
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
                            let errorMessage = 'Failed to delete vehicle.';
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

        // Restore vehicle
        function restoreVehicle(id, unitNo) {
            Swal.fire({
                title: 'Restore Vehicle',
                text: "Are you sure you want to restore vehicle: " + unitNo + "?",
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
                        url: '{{ route('admin.vehicle.restore', ':id') }}'.replace(':id', id),
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'POST'
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                $('#vehicles-table').DataTable().ajax.reload();
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
                            let errorMessage = 'Failed to restore vehicle.';
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

        // Reset form
        function resetForm() {
            $('#vehicleForm')[0].reset();
            $('#vehicle_id').val('');
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
        window.editVehicle = editVehicle;
        window.deleteVehicle = deleteVehicle;
        window.restoreVehicle = restoreVehicle;
        window.showCreateModal = showCreateModal;
    </script>
@endpush
