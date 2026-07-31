@extends('layouts.main-layout')

@section('title', 'Driver Details')

@section('content')
    <div class="p-4 mx-auto md:p-6">
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

        @php
            $driverFullName = trim(
                ($driver->first_name ?? '') .
                    ($driver->middle_name ? ' ' . $driver->middle_name : '') .
                    ' ' .
                    ($driver->last_name ?? '') .
                    ($driver->suffix ? ' ' . $driver->suffix : ''),
            );
            $driverInitials = strtoupper(
                substr($driver->first_name ?? 'D', 0, 1) . substr($driver->last_name ?? 'R', 0, 1),
            );
            $driverPhotoUrl = $driver->photo ? asset('storage/' . $driver->photo) : null;
        @endphp

        <!-- Driver Header Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03] mb-6">
            <div class="p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center space-x-4 mb-4 md:mb-0">
                        <div class="flex-shrink-0">
                            @if ($driverPhotoUrl)
                                <img src="{{ $driverPhotoUrl }}" alt="{{ $driverFullName }}"
                                    class="h-16 w-16 rounded-full object-cover ring-2 ring-brand-100 dark:ring-brand-900/40 shadow-sm">
                            @else
                                <span
                                    class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-brand-100 text-lg font-semibold text-brand-700 ring-2 ring-brand-50 dark:bg-brand-900/40 dark:text-brand-300 dark:ring-brand-900/20">
                                    {{ $driverInitials }}
                                </span>
                            @endif
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">
                                    {{ $driverFullName }}
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
                            <div class="flex flex-wrap items-center mt-2 gap-x-4 gap-y-1">
                                <div class="flex items-center text-gray-600 dark:text-gray-400">
                                    <i class="fas fa-phone text-sm mr-2"></i>
                                    <span>{{ $driver->main_phone ?: 'No phone' }}</span>
                                </div>
                                <div class="flex items-center text-gray-600 dark:text-gray-400">
                                    <i class="fas fa-envelope text-sm mr-2"></i>
                                    <span>{{ $driver->email ?: 'No email' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Business Name</p>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                            {{ $driver->company->company_name ?? ($driver->business_name ?? 'N/A') }}
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
                                @if ($driverPhotoUrl)
                                    <img src="{{ $driverPhotoUrl }}" alt="{{ $driverFullName }}"
                                        class="w-40 h-40 rounded-full object-cover border-4 border-white shadow-md dark:border-gray-800">
                                @else
                                    <span
                                        class="inline-flex w-40 h-40 items-center justify-center rounded-full bg-brand-100 text-3xl font-semibold text-brand-700 border-4 border-white shadow-md dark:bg-brand-900/40 dark:text-brand-300 dark:border-gray-800">
                                        {{ $driverInitials }}
                                    </span>
                                @endif
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
                                        {{ $driver->first_name }}{{ $driver->middle_name ? ' ' . $driver->middle_name : '' }}
                                        {{ $driver->last_name }}
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
                                        {{ $driver->company->company_name ?? ($driver->business_name ?? 'N/A') }}
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
            <div id="driver-info" class="tab-content hidden">
                <div class="space-y-5">
                    {{-- Profile summary strip --}}
                    <div
                        class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-r from-brand-500/90 via-brand-600/80 to-slate-700/90"></div>
                        <div class="relative px-5 sm:px-6 pt-10 pb-6">
                            <div class="flex flex-col sm:flex-row sm:items-end gap-5">
                                <div class="flex-shrink-0">
                                    @if ($driverPhotoUrl)
                                        <img src="{{ $driverPhotoUrl }}" alt="{{ $driverFullName }}"
                                            class="h-24 w-24 rounded-2xl object-cover ring-4 ring-white shadow-lg dark:ring-gray-900">
                                    @else
                                        <span
                                            class="inline-flex h-24 w-24 items-center justify-center rounded-2xl bg-white text-2xl font-semibold text-brand-700 ring-4 ring-white shadow-lg dark:bg-gray-800 dark:text-brand-300 dark:ring-gray-900">
                                            {{ $driverInitials }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 pb-1">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $driverFullName }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $driver->email ?: 'No email on file' }}
                                        @if ($driver->main_phone)
                                            <span class="mx-2 text-gray-300 dark:text-gray-600">·</span>
                                            {{ $driver->main_phone }}
                                        @endif
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="fas fa-map-marker-alt text-[10px]"></i>
                                            {{ collect([$driver->city, $driver->state])->filter()->implode(', ') ?: 'Location N/A' }}
                                        </span>
                                        @if ($driver->hired_at)
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                <i class="fas fa-calendar-check text-[10px]"></i>
                                                Hired {{ \Carbon\Carbon::parse($driver->hired_at)->format('M d, Y') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                @if ($driver->status !== 'draft')
                                    <a href="{{ route('admin.driver.edit', $driver->id) }}"
                                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                        <i class="fas fa-pen mr-2 text-xs"></i>
                                        Edit Info
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                        {{-- Applicant / Name --}}
                        <div
                            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-300">
                                    <i class="fas fa-user text-sm"></i>
                                </span>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Applicant Name</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Legal name on file</p>
                                </div>
                            </div>
                            <div class="p-5 grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">First</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->first_name ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Middle</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->middle_name ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Last</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->last_name ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Suffix</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->suffix ?: '—' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Personal Information --}}
                        <div
                            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-300">
                                    <i class="fas fa-id-badge text-sm"></i>
                                </span>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Personal Information</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Contact & identity details</p>
                                </div>
                            </div>
                            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Date of Birth</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $driver->date_of_birth ? \Carbon\Carbon::parse($driver->date_of_birth)->format('m/d/Y') : '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">SSN</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white font-mono tracking-wide">
                                        {{ $driver->ssn ? '***-**-' . substr($driver->ssn, -4) : '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Main Phone</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->main_phone ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Emergency Phone</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->alt_phone ?: '—' }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Email</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white break-all">{{ $driver->email ?: '—' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Business Information --}}
                        <div
                            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300">
                                    <i class="fas fa-briefcase text-sm"></i>
                                </span>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Business Information</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Tax & employer details</p>
                                </div>
                            </div>
                            <div class="p-5 space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Business Name</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->business_name ?: '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">EIN</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white font-mono">{{ $driver->employer_identification_number ?: '—' }}</p>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Federal Tax Classification</p>
                                    @if ($driver->federal_tax_classification)
                                        <span
                                            class="inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            {{ ucfirst(str_replace('_', ' ', $driver->federal_tax_classification)) }}
                                        </span>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Not specified</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Address Information --}}
                        <div
                            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    <i class="fas fa-home text-sm"></i>
                                </span>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Current Address</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Primary residence on file</p>
                                </div>
                            </div>
                            <div class="p-5 space-y-4">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Street</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->address ?: '—' }}</p>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">City</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->city ?: '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">State</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->state ?: '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Country</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->country ?: '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Postal</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $driver->postal_code ?: '—' }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3 pt-1">
                                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">TWIC</span>
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $driver->twic_card ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                            {{ $driver->twic_card ? 'Yes' : 'No' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Passport</span>
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $driver->passport ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                            {{ $driver->passport ? 'Yes' : 'No' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Residence History --}}
                    @if ($driver->residence_addresses && $driver->residence_addresses->isNotEmpty())
                        <div
                            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-300">
                                    <i class="fas fa-history text-sm"></i>
                                </span>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Residence History</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Previous 3 years</p>
                                </div>
                            </div>
                            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach ($driver->residence_addresses as $residence)
                                    <div
                                        class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                                        <div class="flex items-start justify-between gap-3 mb-3">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $residence->address }}
                                            </p>
                                            @if ($residence->is_current)
                                                <span
                                                    class="shrink-0 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                                    Current
                                                </span>
                                            @else
                                                <span
                                                    class="shrink-0 inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                                    Previous
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ collect([$residence->city, $residence->state, $residence->zip])->filter()->implode(', ') }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                            {{ $residence->country }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- License Information --}}
                    @if ($driver->licenses && $driver->licenses->isNotEmpty())
                        <div
                            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300">
                                    <i class="fas fa-id-card text-sm"></i>
                                </span>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Driver Licenses</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Last 3 years on record</p>
                                </div>
                            </div>
                            <div class="p-5 space-y-3">
                                @foreach ($driver->licenses as $license)
                                    <div
                                        class="rounded-xl border border-gray-200 p-4 dark:border-gray-700 hover:border-brand-200 dark:hover:border-brand-800 transition-colors">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                                            <div>
                                                <p class="text-base font-semibold text-gray-900 dark:text-white">
                                                    {{ $license->state }} · {{ $license->license_number }}
                                                </p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {{ trim(($license->first_name ?? '') . ' ' . ($license->last_name ?? '')) ?: $driverFullName }}
                                                    @if ($license->class)
                                                        <span class="mx-1.5">·</span>Class {{ $license->class }}
                                                    @endif
                                                </p>
                                            </div>
                                            <span
                                                class="self-start inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ ($license->status ?? '') === 'valid' ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                                {{ $license->status ? ucfirst($license->status) : 'On file' }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                            <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/50">
                                                <p class="text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400">Issued</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $license->issued ? \Carbon\Carbon::parse($license->issued)->format('m/d/Y') : '—' }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/50">
                                                <p class="text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400">Expires</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $license->expires ? \Carbon\Carbon::parse($license->expires)->format('m/d/Y') : '—' }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/50">
                                                <p class="text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400">Class</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $license->class ?: '—' }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/50">
                                                <p class="text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400">State</p>
                                                <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $license->state ?: '—' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>






            <!-- Qualifications Checklist Tab -->
            <div id="qualifications" class="tab-content hidden">
                <div
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Qualifications Checklist</h3>
                                @if (($compliance['status'] ?? '') === 'danger')
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Critical</span>
                                @elseif (($compliance['status'] ?? '') === 'warning')
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Warning</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Compliant</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ $compliance['percentage'] ?? 0 }}%
                                ({{ $compliance['compliant_docs'] ?? 0 }}/{{ $compliance['total_docs'] ?? 0 }})
                            </p>
                        </div>
                    </div>

                    <div class="p-5 space-y-2">
                        @forelse ($compliance['document_details'] ?? [] as $docDetail)
                            <div
                                class="flex items-center justify-between py-2 px-3 rounded-lg border dark:bg-zinc-900 border-zinc-100 hover:border-zinc-200 transition-colors
                                @if ($docDetail['status'] === 'missing' || $docDetail['status'] === 'expired') border-l-4 border-l-red-400
                                @elseif($docDetail['status'] === 'expiring') border-l-4 border-l-amber-400
                                @else border-l-4 border-l-emerald-400 @endif">

                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <i class="fas fa-file-alt text-gray-400 text-sm shrink-0"></i>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-sm text-zinc-800 dark:text-white truncate">
                                            {{ $docDetail['type_name'] }}
                                        </div>
                                        @if ($docDetail['file_date'])
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                Filed: {{ \Carbon\Carbon::parse($docDetail['file_date'])->format('M d, Y') }}
                                            </div>
                                        @endif
                                        @if ($docDetail['expiry_date'])
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                Expires: {{ \Carbon\Carbon::parse($docDetail['expiry_date'])->format('M d, Y') }}
                                                @if (!is_null($docDetail['days_until_expiry']) && $docDetail['status'] === 'expiring')
                                                    ({{ (int) $docDetail['days_until_expiry'] }} days)
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        @if ($docDetail['status'] === 'missing')
                                            <span class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Missing</span>
                                        @elseif ($docDetail['status'] === 'expired')
                                            <span class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Expired</span>
                                        @elseif ($docDetail['status'] === 'expiring')
                                            <span class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Expiring Soon</span>
                                        @else
                                            <span class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Valid</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                                    @if ($docDetail['document_id'] && $docDetail['file_path'])
                                        <a href="{{ route('admin.compliance.driver.documents.view', $docDetail['document_id']) }}"
                                            target="_blank"
                                            class="h-6 w-6 rounded-md inline-flex items-center justify-center text-gray-600 hover:bg-zinc-800/5 dark:text-gray-300 dark:hover:bg-white/15"
                                            title="View">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <a href="{{ route('admin.compliance.driver.documents.download', $docDetail['document_id']) }}"
                                            class="h-6 w-6 rounded-md inline-flex items-center justify-center text-gray-600 hover:bg-zinc-800/5 dark:text-gray-300 dark:hover:bg-white/15"
                                            title="Download">
                                            <i class="fas fa-download text-xs"></i>
                                        </a>
                                    @endif

                                    @can('drivers.edit')
                                        @if ($docDetail['status'] !== 'valid')
                                            <button type="button"
                                                onclick="openUploadModal({{ $driver->id }}, {{ $docDetail['type_id'] }}, 'driver')"
                                                class="h-6 text-xs rounded-md px-2 inline-flex items-center font-medium bg-brand-600 hover:bg-brand-700 text-white border border-black/10 dark:border-0">
                                                Complete
                                            </button>
                                        @else
                                            <button type="button"
                                                onclick="openUploadModal({{ $driver->id }}, {{ $docDetail['type_id'] }}, 'driver')"
                                                class="h-6 text-xs rounded-md px-2 inline-flex items-center font-medium border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                                title="Replace document">
                                                Replace
                                            </button>
                                        @endif

                                        <button type="button"
                                            onclick="sendReminderEmail({{ $driver->id }}, {{ $docDetail['type_id'] }}, 'driver')"
                                            class="h-6 w-6 rounded-md inline-flex items-center justify-center bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800 dark:text-white"
                                            title="Send reminder">
                                            <i class="fas fa-ellipsis-v text-xs"></i>
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center">
                                <i class="fas fa-clipboard-list text-3xl text-gray-300 dark:text-gray-600 mb-3"></i>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No active driver document types configured.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- File Explorer Tab -->
            <div id="files" class="tab-content hidden">
                <div
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">File Explorer</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Uploaded compliance documents for this driver</p>
                    </div>

                    <div class="p-5">
                        @if ($uploadedDocuments->isEmpty())
                            <div class="py-10 text-center">
                                <i class="fas fa-folder-open text-3xl text-gray-300 dark:text-gray-600 mb-3"></i>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">No documents uploaded yet</p>
                                <p class="text-xs text-gray-500 dark:text-gray-500">Use <span class="font-medium">Complete</span> on the Qualifications Checklist to upload files.</p>
                            </div>
                        @else
                            <div class="space-y-2">
                                @foreach ($uploadedDocuments as $document)
                                    @php
                                        $ext = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));
                                        $icon = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'fa-file-image' : 'fa-file-pdf';
                                    @endphp
                                    <div
                                        class="flex items-center justify-between gap-3 py-3 px-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                                        <div class="flex items-center gap-3 min-w-0 flex-1">
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400 shrink-0">
                                                <i class="fas {{ $icon }}"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    {{ $document->documentType?->name ?? 'Document' }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                    @if ($document->file_date)
                                                        Filed {{ \Carbon\Carbon::parse($document->file_date)->format('M d, Y') }}
                                                    @endif
                                                    @if ($document->expiry_date)
                                                        <span class="mx-1">·</span>
                                                        Expires {{ \Carbon\Carbon::parse($document->expiry_date)->format('M d, Y') }}
                                                    @endif
                                                    @if ($document->updated_at)
                                                        <span class="mx-1">·</span>
                                                        Updated {{ $document->updated_at->format('M d, Y') }}
                                                    @endif
                                                </p>
                                                @if ($document->description)
                                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate">{{ $document->description }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <a href="{{ route('admin.compliance.driver.documents.view', $document->id) }}"
                                                target="_blank"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                                title="View">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <a href="{{ route('admin.compliance.driver.documents.download', $document->id) }}"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                                title="Download">
                                                <i class="fas fa-download text-xs"></i>
                                            </a>
                                            @can('drivers.edit')
                                                <button type="button"
                                                    onclick="deleteComplianceDocument({{ $document->id }}, @js($document->documentType?->name ?? 'Document'))"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-red-50 hover:text-red-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                                    title="Delete">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Logs Tab -->
            <div id="logs" class="tab-content hidden">
                <div
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Logs</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recent document and status activity</p>
                    </div>

                    <div class="p-5">
                        @if ($activityLogs->isEmpty())
                            <div class="py-10 text-center">
                                <i class="fas fa-history text-3xl text-gray-300 dark:text-gray-600 mb-3"></i>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No activity recorded yet.</p>
                            </div>
                        @else
                            <ol class="relative space-y-0 border-l border-gray-200 dark:border-gray-700 ml-3">
                                @foreach ($activityLogs as $log)
                                    @php
                                        $toneClasses = match ($log['tone'] ?? 'brand') {
                                            'green' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                            'red' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                            default => 'bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300',
                                        };
                                    @endphp
                                    <li class="mb-6 ml-6 last:mb-0">
                                        <span class="absolute -left-3.5 flex h-7 w-7 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900 {{ $toneClasses }}">
                                            <i class="fas {{ $log['icon'] }} text-[10px]"></i>
                                        </span>
                                        <div class="rounded-xl border border-gray-200 bg-gray-50/60 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/40">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $log['label'] }}</p>
                                                <time class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ \Carbon\Carbon::parse($log['at'])->format('M d, Y g:i A') }}
                                                </time>
                                            </div>
                                            @if (!empty($log['detail']))
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $log['detail'] }}</p>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.compliance.partials.driver-upload-document-modal')
@endsection

@push('scripts')
    <script>
        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) return meta.getAttribute('content');
            const input = document.querySelector('input[name="_token"]');
            return input ? input.value : null;
        }

        function showToast(message, type = 'info') {
            if (typeof toastr !== 'undefined') {
                toastr[type] ? toastr[type](message) : toastr.info(message);
                return;
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
                    title: message,
                    showConfirmButton: false,
                    timer: 3000
                });
                return;
            }
            alert(message);
        }

        function deleteComplianceDocument(documentId, documentName) {
            const runDelete = () => {
                fetch(@json(route('admin.compliance.driver.documents.delete')), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ document_id: documentId })
                    })
                    .then(async response => {
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Failed to delete document');
                        return data;
                    })
                    .then(data => {
                        showToast(data.message || 'Document deleted', 'success');
                        setTimeout(() => location.reload(), 800);
                    })
                    .catch(error => {
                        showToast(error.message || 'Error deleting document', 'error');
                    });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete document?',
                    html: `Remove <strong>${documentName}</strong> for this driver?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, delete',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) runDelete();
                });
                return;
            }

            if (confirm(`Delete ${documentName}?`)) runDelete();
        }

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
    <script>
        @include('admin.compliance.partials.send-reminder-script')
    </script>
@endpush

<!-- Delete Form (Hidden) -->
<form id="deleteDriverForm" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
