@extends('layouts.main-layout')

@section('title', 'Driver Details')

@section('content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Driver Details</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">View and manage driver information</p>
                </div>

                <div class="flex items-center space-x-3 mt-4 sm:mt-0">
                    <a href="{{ route('admin.driver.index') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Drivers
                    </a>

                    @if ($driver->status !== 'draft')
                        <a href="{{ route('admin.driver.edit', $driver->id) }}"
                            class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                            <i class="fas fa-edit mr-2"></i>Edit Driver
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Driver Header Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03] mb-6">
            <div class="p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center space-x-4 mb-4 md:mb-0">
                        <div class="flex-shrink-0">

                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">
                                    * {{ $driver->first_name }} {{ $driver->last_name }}
                                    @php
                                        $statusColors = [
                                            'draft' =>
                                                'text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 py-1 px-2 rounded-lg',
                                            'pending' =>
                                                'text-orange-600 dark:text-orange-400 bg-orange-100 dark:bg-orange-900 py-1 px-2 rounded-lg',
                                            'active' =>
                                                'text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900 py-1 px-2 rounded-lg',
                                            'inactive' =>
                                                'text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 py-1 px-2 rounded-lg',
                                            'submitted' =>
                                                'text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900 py-1 px-2 rounded-lg',
                                            'under_review' =>
                                                'text-yellow-600 dark:text-yellow-400 bg-yellow-100 dark:bg-yellow-900 py-1 px-2 rounded-lg',
                                            'approved' =>
                                                'text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900 py-1 px-2 rounded-lg',
                                            'rejected' =>
                                                'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900 py-1 px-2 rounded-lg',
                                        ];

                                        $statusLabels = [
                                            'draft' => 'Draft',
                                            'pending' => 'Pending Review',
                                            'active' => 'Active',
                                            'inactive' => 'Inactive',
                                            'submitted' => 'Submitted',
                                            'under_review' => 'Under Review',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                        ];

                                        // Make sure to include all classes in the fallback
                                        $color =
                                            $statusColors[$driver->status] ??
                                            'text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 py-1 px-2 rounded-lg';
                                        $label = $statusLabels[$driver->status] ?? ucfirst($driver->status);
                                    @endphp
                                    <span class="text-sm ml-2 {{ $color }}">{{ $label }}</span>
                                </h3>
                            </div>
                            <div class="flex items-center mt-2 space-x-2">
                                <div class="flex items-center text-gray-600 dark:text-gray-400">
                                    <i class="fas fa-phone text-sm mr-2"></i>
                                    <span>{{ $driver->main_phone ?: '(515) 317-4198' }}</span>
                                </div>
                                <div class="flex items-center text-gray-600 dark:text-gray-400">
                                    <i class="fas fa-envelope text-sm mr-2"></i>
                                    <span>{{ $driver->email ?: 'SALES@SKYROSLOGISTICS.COM' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Business Name</p>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                            {{ $driver->company->name ?? 'Wunsch-Smitham' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03] mb-6">
            <nav class="flex space-x-8 px-6 py-4 overflow-x-auto" aria-label="Tabs">
                <button
                    class="tab-btn whitespace-nowrap border-b-2 border-brand-500 text-brand-600 py-2 text-sm font-medium"
                    data-tab="overview">
                    Overview
                </button>

                <button
                    class="tab-btn whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-2 text-sm font-medium"
                    data-tab="driver-info">
                    Driver Information
                </button>

                <button
                    class="tab-btn whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-2 text-sm font-medium"
                    data-tab="qualifications">
                    Qualifications Checklist
                </button>

                <button
                    class="tab-btn whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-2 text-sm font-medium"
                    data-tab="files">
                    File Explorer
                </button>

                <button
                    class="tab-btn whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-2 text-sm font-medium"
                    data-tab="logs">
                    Logs
                </button>
            </nav>
        </div>

        <!-- Tab Contents -->
        <div class="mt-6">
            <!-- Overview Tab -->
            <div id="overview" class="tab-content">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-stretch">
                    <!-- Left Column - Profile Information (3 columns wide) -->
                    <div class="md:col-span-3">
                        <!-- Profile Information Card -->
                        <div
                            class="h-full rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex justify-center mt-3">
                                <img src="{{ asset('/storage/' . $driver->photo) ?? asset('images/logo.png') }}"
                                    alt="Driver Profile Image"
                                    class="w-40 h-40 rounded-full object-cover border border-gray-200">
                            </div>

                            <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                    Profile Information
                                </h3>
                            </div>

                            <div class="p-6 space-y-5">
                                <div>
                                    <p
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                        Full Name
                                    </p>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $driver->first_name }} {{ $driver->last_name }}
                                    </p>
                                </div>

                                {{-- <div>
                                    <p
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                        Driver Type
                                    </p>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $driver->driver_type ? ucfirst($driver->driver_type) : 'Driver type is not selected 😊' }}
                                    </p>
                                </div> --}}

                                <div>
                                    <p
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                        Phone Number
                                    </p>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $driver->main_phone ?: '' }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                        Email
                                    </p>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $driver->email ?: '' }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                        Business Name
                                    </p>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $driver->company->company_name ?? 'Wunsch-Smitham' }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                        Identification number
                                    </p>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $driver->employer_identification_number ?: 'Not provided' }}
                                    </p>
                                </div>
                            </div>
                        </div>


                    </div>

                    <!-- Right Column - License Summary (9 columns wide) -->
                    <div class="md:col-span-9 space-y-3">
                        <!-- License Summary Card -->
                        @if ($driver->status === 'pending')
                            <div
                                class="h-full rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        What’s the next step?
                                    </h3>
                                </div>

                                <div class="p-6">
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90 mb-4">
                                        The driver status is currently <span class="font-bold">Pending</span>, which means
                                        they are being considered for employment but have not yet been employed or qualified
                                        to drive.
                                    </p>

                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90 mb-4">
                                        You must now decide whether or not to recruit this driver after evaluating their
                                        complete driver file, running a <span class="font-bold">DOT Pre-Employment Screening
                                            Program (PSP) Report, and running a state Motor Vehicle Report (MVR)</span>.
                                    </p>

                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90 mb-4">
                                        The Qualification Checklist must also be completed to check that the driver has been
                                        appropriately qualified in accordance with the DOT requirements. A failing audit
                                        will happen if the qualification is not completed.
                                    </p>
                                </div>

                                @can('drivers.hire')
                                    <div class="flex items-center justify-items-center space-x-3">
                                        <div class="w-full">
                                            <button type="button" onclick="showHireModal({{ $driver->id }})"
                                                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 w-full text-center">
                                                Hire Applicant
                                            </button>
                                        </div>

                                        <div class="w-full">
                                            <button type="button" onclick="showNotHireModal({{ $driver->id }})"
                                                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-red-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-red-600 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 focus:ring-offset-2 w-full text-center">
                                                Do not Hire
                                            </button>
                                        </div>
                                    </div>
                                @endcan

                                {{-- Hire Modal HTML --}}
                                <div id="hireModal"
                                    class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50 hidden">
                                    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-md">
                                        <div class="p-6">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Hire
                                                    Applicant For Employment</h3>
                                                <button type="button" onclick="closeHireModal()"
                                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>

                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                                You have chosen to hire <span id="hireDriverName"
                                                    class="font-semibold"></span> for employment.
                                                This action cannot be undone. Please set the Date Hired and confirm below to
                                                move the applicant into active status.
                                            </p>

                                            <form id="hireForm">
                                                @csrf
                                                <input type="hidden" name="driver_id" id="hireDriverId">

                                                <div class="space-y-4">
                                                    <div>
                                                        <label for="hire_date"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Date Hired *
                                                        </label>
                                                        <input type="date" name="hire_date" id="hire_date"
                                                            value="{{ date('Y-m-d') }}"
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                                            required>
                                                    </div>

                                                    <div>
                                                        <label for="hazmat"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Will driver be hauling hazmat? *
                                                        </label>
                                                        <select name="hazmat" id="hazmat"
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                                            required>
                                                            <option value="">Please Select</option>
                                                            <option value="yes">Yes</option>
                                                            <option value="no">No</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label for="lcv_certificate"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Does driver require Longer Combination Vehicle (LCV)
                                                            certificate? *
                                                        </label>
                                                        <select name="lcv_certificate" id="lcv_certificate"
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                                            required>
                                                            <option value="">Please Select</option>
                                                            <option value="yes">Yes</option>
                                                            <option value="no">No</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="flex justify-end space-x-3 mt-6">
                                                    <button type="button" onclick="closeHireModal()"
                                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 rounded-lg">
                                                        Cancel
                                                    </button>
                                                    <button type="submit"
                                                        class="px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg">
                                                        Confirm Hire
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                {{-- Not Hire Modal HTML --}}
                                <div id="notHireModal"
                                    class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50 hidden">
                                    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-md">
                                        <div class="p-6">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Not
                                                    Hiring</h3>
                                                <button type="button" onclick="closeNotHireModal()"
                                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>

                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                                You have decided against employing this applicant. Kindly select the reason
                                                from the list below and provide any further information.
                                                Kindly confirm that the applicant will not be hired once you've completed.
                                            </p>

                                            <form id="notHireForm">
                                                @csrf
                                                <input type="hidden" name="driver_id" id="notHireDriverId">

                                                <div class="space-y-4">
                                                    <div>
                                                        <label for="rejection_reason"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Reason *
                                                        </label>
                                                        <select name="rejection_reason" id="rejection_reason"
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                                            required>
                                                            <option value="">Please Select Reason</option>
                                                            <option value="not_good_fit">Applicant is not a good fit for
                                                                our company</option>
                                                            <option value="failed_drug_test">Applicant failed a
                                                                pre-employment drug test</option>
                                                            <option value="background_check_issues">Items found on the
                                                                background check</option>
                                                            <option value="cdl_issues">Items found on the Commercial
                                                                Driver's License</option>
                                                            <option value="mvr_issues">Items found on the Motor Vehicle
                                                                Report (MVR)</option>
                                                            <option value="psp_issues">Items found on the Pre-Employment
                                                                Screening Program (PSP) report</option>
                                                            <option value="other">Other reason not listed above</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label for="additional_info"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Additional Information
                                                        </label>
                                                        <textarea name="additional_info" id="additional_info" rows="3"
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                                            placeholder="Provide additional details..."></textarea>
                                                    </div>

                                                    <div>
                                                        <label for="record_date"
                                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Record Date *
                                                        </label>
                                                        <input type="date" name="record_date" id="record_date"
                                                            value="{{ date('Y-m-d') }}"
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                                            required>
                                                    </div>
                                                </div>

                                                <div class="flex justify-end space-x-3 mt-6">
                                                    <button type="button" onclick="closeNotHireModal()"
                                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 rounded-lg">
                                                        Cancel
                                                    </button>
                                                    <button type="submit"
                                                        class="px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg">
                                                        Confirm Not Hiring
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        @else
                            <div
                                class="h-full rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        License Summary
                                    </h3>
                                </div>

                                <div class="p-6">
                                    @if ($driver->licenses && $driver->licenses->isNotEmpty())
                                        @php
                                            $latestLicense = $driver->licenses->sortByDesc('expires')->first();
                                        @endphp

                                        <!-- License Header -->
                                        <div class="mb-6">
                                            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 mb-4">
                                                <h4
                                                    class="flex items-center text-3xl font-bold text-gray-800 dark:text-white/90 mb-2">
                                                    <span class="mr-2">
                                                        <svg class="h-6 w-6 dark:text-white"
                                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            width="48" height="48" color="#6b7280"
                                                            fill="none">
                                                            <path
                                                                d="M19.4999 10C19.4999 6.22876 19.4999 4.34315 18.3284 3.17157C17.1568 2 15.2712 2 11.4999 2H10.5C6.72883 2 4.84323 2 3.67166 3.17156C2.50008 4.34312 2.50007 6.22872 2.50004 9.99993L2.5 13.9999C2.49997 17.7712 2.49995 19.6568 3.67153 20.8284C4.8431 22 6.72873 22 10.5 22H12"
                                                                stroke="currentColor" stroke-width="1.5"
                                                                stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M7 7H15M7 12H13.5" stroke="currentColor"
                                                                stroke-width="1.5" stroke-linecap="round"></path>
                                                            <path
                                                                d="M15.8613 22H20.1387C21.0238 22 21.7723 21.3987 21.4039 20.753C20.8135 19.7186 19.5114 19 18 19C16.4886 19 15.1865 19.7186 14.5961 20.753C14.2277 21.3987 14.9762 22 15.8613 22Z"
                                                                stroke="currentColor" stroke-width="1.5"
                                                                stroke-linejoin="round"></path>
                                                            <path
                                                                d="M17.9969 16.5C18.9639 16.5 19.7477 15.7165 19.7477 14.75C19.7477 13.7835 18.9639 13 17.9969 13C17.03 13 16.2461 13.7835 16.2461 14.75C16.2461 15.7165 17.03 16.5 17.9969 16.5Z"
                                                                stroke="currentColor" stroke-width="1.5"></path>
                                                            <path opacity="0.4"
                                                                d="M19.4999 13.852V10C19.4999 6.22876 19.4999 4.34315 18.3284 3.17157C17.1568 2 15.2712 2 11.4999 2H10.5C6.72883 2 4.84323 2 3.67166 3.17156C2.50008 4.34312 2.50007 6.22872 2.50004 9.99993L2.5 13.9999C2.49997 17.7712 2.49995 19.6568 3.67153 20.8284C4.8431 22 6.72873 22 10.5 22H11.4999C13.063 22 14.3021 22 15.3008 21.9166C14.698 21.7298 14.3098 21.2548 14.5961 20.753C15.1865 19.7186 16.4886 19 18 19C18.4237 19 18.8309 19.0565 19.2107 19.1605C19.4268 18.2855 19.4815 17.1574 19.4953 15.6558C19.1884 16.1618 18.6322 16.5 17.9969 16.5C17.03 16.5 16.2461 15.7165 16.2461 14.75C16.2461 13.7835 17.03 13 17.9969 13C18.6354 13 19.1941 13.3416 19.4999 13.852Z"
                                                                fill="currentColor"></path>
                                                        </svg>
                                                    </span>
                                                    {{ $latestLicense->state ?? 'Florida' }}
                                                    {{ $latestLicense->license_number ?? 'XYZ-1234' }}
                                                </h4>
                                            </div>

                                            <!-- License Details Table -->
                                            <div class="">
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                                                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 text-center">
                                                        <p
                                                            class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                                            License Status
                                                        </p>
                                                        <p class="text-sm font-bold text-gray-800 dark:text-white/90">
                                                            {{ $latestLicense->status ?? 'COMMERCIAL VALID' }}
                                                        </p>
                                                        <p class="my-2 sm:mb-0 text-xs text-gray-500 dark:text-gray-400">
                                                            As of:
                                                            {{ $latestLicense->updated_at ? $latestLicense->updated_at->format('m/d/Y') : '07/23/2023' }}
                                                        </p>
                                                    </div>
                                                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 text-center">
                                                        <p
                                                            class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                                            License Expires
                                                        </p>
                                                        <p class="text-sm font-bold text-gray-800 dark:text-white/90">
                                                            {{ $latestLicense->expires ? $latestLicense->expires : '' }}
                                                        </p>
                                                    </div>
                                                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 text-center">
                                                        <p
                                                            class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                                            License Monitoring
                                                        </p>
                                                        <p class="text-sm font-bold text-gray-800 dark:text-white/90">
                                                            {{ $latestLicense->monitoring_status ?? 'OFF' }}
                                                        </p>
                                                    </div>
                                                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 text-center">
                                                        <p
                                                            class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                                            MVR Score
                                                        </p>
                                                        <p class="text-sm font-bold text-gray-800 dark:text-white/90">
                                                            {{ $latestLicense->mvr_score ?? 'n/a' }}
                                                        </p>

                                                        <p class="my-2 sm:mb-0 text-xs text-gray-500 dark:text-gray-400">
                                                            Last MVR:
                                                            {{ $latestLicense->last_mvr_check ? $latestLicense->last_mvr_check->format('m/d/Y') : 'n/a' }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Footer Information -->

                                            </div>
                                        </div>

                                        <!-- Additional Licenses -->
                                        @if ($driver->licenses->count() > 1)
                                            <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">
                                                    Additional
                                                    Licenses</h4>
                                                <div class="space-y-3">
                                                    @foreach ($driver->licenses->sortByDesc('expires')->skip(1) as $license)
                                                        <div
                                                            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/30 rounded-lg">
                                                            <div>
                                                                <p
                                                                    class="text-sm font-medium text-gray-800 dark:text-white/90">
                                                                    {{ $license->state }}
                                                                    {{ $license->license_number }}
                                                                </p>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                    Expires:
                                                                    {{ $license->expires ? $license->expires->format('m/d/Y') : 'N/A' }}
                                                                </p>
                                                            </div>
                                                            <span
                                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $license->status === 'valid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' }}">
                                                                {{ ucfirst($license->status) }}
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-center py-8">
                                            <div
                                                class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                                <i class="fas fa-id-card text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">No license information
                                                available</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Driver Information Tab -->
            {{-- <div id="driver-info" class="tab-content hidden">
                <div
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03] p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Driver Information</h3>
                    <p class="text-gray-600 dark:text-gray-400">This is Driver Information content.</p>
                    <!-- Add driver information form or details here -->
                </div>
            </div> --}}

            <!-- Driver Information Tab -->
            <div id="driver-info" class="tab-content hidden">
                <div
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03] p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-6">Driver Information</h3>

                    <!-- Applicant Information Table -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-4">Applicant Information</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            First Name</th>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Middle Name</th>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Last Name</th>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Suffix</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800/30 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td
                                            class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">
                                            {{ $driver->first_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                            {{ $driver->middle_name ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                            {{ $driver->last_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                            {{ $driver->suffix ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Business Information -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-4">Business Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Business Name</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ $driver->business_name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Employer Identification Number</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ $driver->employer_identification_number ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Federal Tax Classification -->
                        <div class="mt-4">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                Federal Tax Classification</p>
                            <div class="flex items-center space-x-2">
                                <div
                                    class="w-4 h-4 border border-gray-300 dark:border-gray-600 rounded flex items-center justify-center">
                                    @if ($driver->federal_tax_classification)
                                        <div class="w-2 h-2 bg-brand-500 rounded-full"></div>
                                    @endif
                                </div>
                                <span class="text-sm font-medium text-gray-800 dark:text-white/90">
                                    @if ($driver->federal_tax_classification)
                                        {{ ucfirst(str_replace('_', ' ', $driver->federal_tax_classification)) }}
                                    @else
                                        Not specified
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-4">Personal Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Date of Birth</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ $driver->date_of_birth ? \Carbon\Carbon::parse($driver->date_of_birth)->format('m/d/Y') : 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    SSN</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                    ***-**-{{ substr($driver->ssn, -4) }}</p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Main Phone Number</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $driver->main_phone }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Emergency Phone Number</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ $driver->alt_phone ?? 'N/A' }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Email Address</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $driver->email }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-4">Address Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Address</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $driver->address }}</p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    City</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $driver->city }}</p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    State</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $driver->state }}</p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Country</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $driver->country }}</p>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Postal Code</p>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $driver->postal_code }}
                                </p>
                            </div>
                        </div>

                        <!-- Document Status -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    TWIC Card</p>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $driver->twic_card ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                    {{ $driver->twic_card ? 'Yes' : 'No' }}
                                </span>
                            </div>
                            <div>
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    Passport</p>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $driver->passport ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                    {{ $driver->passport ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Residence History -->
                    @if ($driver->residence_addresses && $driver->residence_addresses->isNotEmpty())
                        <div class="mb-8">
                            <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-4">Residences (Previous 3
                                Years)</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                                        <tr>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                Address</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                City</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                State</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                Country</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                Zip Code</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                Status</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="bg-white dark:bg-gray-800/30 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($driver->residence_addresses as $residence)
                                            <tr>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">
                                                    {{ $residence->address }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $residence->city }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $residence->state }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $residence->country }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $residence->zip }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    @if ($residence->is_current)
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                            Current
                                                        </span>
                                                    @else
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                            Previous
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Driver License Information -->
                    @if ($driver->licenses && $driver->licenses->isNotEmpty())
                        <div>
                            <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-4">Driver License
                                Information (Last 3 Years)</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                                        <tr>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                First Name</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                Last Name</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                License Number</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                State</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                Class</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                Issued</th>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="bg-white dark:bg-gray-800/30 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($driver->licenses as $license)
                                            <tr>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">
                                                    {{ $license->first_name }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $license->last_name }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $license->license_number }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $license->state }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $license->class }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ \Carbon\Carbon::parse($license->issued)->format('m/d/Y') }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                                    {{ \Carbon\Carbon::parse($license->expires)->format('m/d/Y') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>






            <!-- Qualifications Checklist Tab -->
            <div id="qualifications" class="tab-content hidden">
                <div
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03] p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Qualifications Checklist</h3>
                    <p class="text-gray-600 dark:text-gray-400">This is Qualifications Checklist content.</p>
                    <!-- Add qualifications checklist here -->
                </div>
            </div>

            <!-- File Explorer Tab -->
            <div id="files" class="tab-content hidden">
                <div
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03] p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">File Explorer</h3>
                    <p class="text-gray-600 dark:text-gray-400">This is File Explorer content.</p>
                    <!-- Add file explorer here -->
                </div>
            </div>

            <!-- Logs Tab -->
            <div id="logs" class="tab-content hidden">
                <div
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03] p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Logs</h3>
                    <p class="text-gray-600 dark:text-gray-400">This is Logs content.</p>
                    <!-- Add logs here -->
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Reset all tab buttons
                    tabButtons.forEach(btn => {
                        btn.classList.remove('border-brand-500', 'text-brand-600');
                        btn.classList.add('border-transparent', 'text-gray-500');
                    });

                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });

                    // Activate current tab
                    button.classList.remove('border-transparent', 'text-gray-500');
                    button.classList.add('border-brand-500', 'text-brand-600');

                    // Show corresponding tab content
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(tabId).classList.remove('hidden');
                });
            });

            // Initialize first tab as active
            if (tabButtons.length > 0) {
                tabButtons[0].click();
            }
        });

        function deleteDriver(id) {
            Swal.fire({
                title: 'Delete Driver?',
                html: 'Are you sure you want to delete this driver?<br>This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-lg',
                    cancelButton: 'rounded-lg'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteDriverForm');
                    form.action = `/admin/driver/${id}`;
                    form.submit();
                }
            });
        }

        function showHireModal(driverId) {
            // Get driver name (assuming you have it in a data attribute or can fetch)
            const driverName = "{{ $driver->first_name }} {{ $driver->last_name }}";
            document.getElementById('hireDriverName').textContent = driverName;
            document.getElementById('hireDriverId').value = driverId;
            document.getElementById('hire_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('hireModal').classList.remove('hidden');
        }

        function closeHireModal() {
            document.getElementById('hireModal').classList.add('hidden');
            document.getElementById('hireForm').reset();
        }

        function showNotHireModal(driverId) {
            document.getElementById('notHireDriverId').value = driverId;
            document.getElementById('record_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('notHireModal').classList.remove('hidden');
        }

        function closeNotHireModal() {
            document.getElementById('notHireModal').classList.add('hidden');
            document.getElementById('notHireForm').reset();
        }

        // Handle Hire Form Submission
        document.getElementById('hireForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const driverId = formData.get('driver_id');

            Swal.fire({
                title: 'Confirm Hire?',
                html: `Are you sure you want to hire this driver?<br>
                   <strong>Date Hired:</strong> ${formData.get('hire_date')}<br>
                   <strong>Hazmat:</strong> ${formData.get('hazmat')}<br>
                   <strong>LCV Certificate:</strong> ${formData.get('lcv_certificate')}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Hire Driver',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    updateDriverStatus(driverId, 'hire', Object.fromEntries(formData));
                }
            });
        });

        // Handle Not Hire Form Submission
        document.getElementById('notHireForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const driverId = formData.get('driver_id');
            const reason = formData.get('rejection_reason');

            Swal.fire({
                title: 'Confirm Not Hiring?',
                html: `Are you sure you do not want to hire this driver?<br>
                   <strong>Reason:</strong> ${document.querySelector('#rejection_reason option:checked').textContent}<br>
                   <strong>Additional Info:</strong> ${formData.get('additional_info') || 'None'}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Do Not Hire',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    updateDriverStatus(driverId, 'reject', Object.fromEntries(formData));
                }
            });
        });

        function updateDriverStatus(driverId, action, formData = {}) {
            // Show loading
            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Prepare data
            const data = {
                ...formData,
                action: action
            };

            // Make AJAX request
            fetch(`/admin/driver/${driverId}/hire-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw err;
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    Swal.close();

                    if (data.success) {
                        // Close modals
                        closeHireModal();
                        closeNotHireModal();

                        Swal.fire({
                            title: 'Success!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#10b981',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: data.message,
                            icon: 'error',
                            confirmButtonColor: '#ef4444',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    Swal.fire({
                        title: 'Error!',
                        text: error.message || 'Something went wrong. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: 'OK'
                    });
                });
        }
    </script>
@endpush

<!-- Delete Form (Hidden) -->
<form id="deleteDriverForm" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
