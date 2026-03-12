@extends('layouts.main-layout')

@section('title', 'Dashboard')

@section('content')
    <div class="p-4 md:p-6 lg:p-8 mx-auto">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                    {{ $isSuperAdmin ? 'Admin Dashboard' : 'Company Dashboard' }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Monitor your fleet operations and key compliance metrics.
                </p>
            </div>

            @if(!$isSuperAdmin && $company)
                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex flex-col items-end">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Company</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $company->company_name }}
                        </span>
                    </div>
                    @if($company->logo ?? false)
                        <img src="{{ asset('storage/' . $company->logo) }}"
                             alt="{{ $company->company_name }}"
                             class="w-10 h-10 rounded-lg object-cover border border-gray-200 dark:border-gray-700">
                    @else
                        <div
                            class="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-semibold text-lg">
                            {{ strtoupper(substr($company->company_name, 0, 1)) }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
            {{-- Driver Application for Employment --}}
            <section class="col-span-1 xl:col-span-1">
                <div class="h-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                                Driver Application for Employment
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Share this link with applicants.
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/40 px-2.5 py-1 text-[11px] font-medium text-indigo-700 dark:text-indigo-300">
                            <i class="fas fa-users text-[10px]"></i>
                            Hiring
                        </span>
                    </div>

                    @if(!$isSuperAdmin && $company && $company->slug)
                        @php
                            $applicationUrl = route('application.form', $company->slug);
                        @endphp
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                Public application link
                            </label>
                            <div
                                class="flex items-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2">
                                <input type="text"
                                       readonly
                                       value="{{ $applicationUrl }}"
                                       class="flex-1 bg-transparent border-0 text-xs text-gray-700 dark:text-gray-200 focus:ring-0 truncate">
                                <button
                                    type="button"
                                    x-data
                                    @click="navigator.clipboard.writeText('{{ $applicationUrl }}')"
                                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-2.5 py-1.5 transition-colors">
                                    <i class="fas fa-copy mr-1 text-[10px]"></i>
                                    Copy
                                </button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="{{ $applicationUrl }}"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2.5 transition-colors">
                                <i class="fas fa-paper-plane"></i>
                                Send Application Form
                            </a>
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Application link will be available when a company is associated with this account.
                        </p>
                    @endif
                </div>
            </section>

            {{-- Drivers Status --}}
            <section class="col-span-1">
                <div class="h-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                            Drivers Status
                        </h2>
                        <a href="{{ route('admin.driver.index') }}"
                           class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            View more →
                        </a>
                    </div>

                    <div class="space-y-2 mt-1">
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50 dark:bg-emerald-900/20 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-white text-xs">
                                    <i class="fas fa-user-check"></i>
                                </span>
                                <div>
                                    <p class="text-xs font-medium text-gray-800 dark:text-gray-100">Total Active Drivers</p>
                                </div>
                            </div>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $driverStats['active'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-amber-50 dark:bg-amber-900/20 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-400 text-white text-xs">
                                    <i class="fas fa-hourglass-half"></i>
                                </span>
                                <div>
                                    <p class="text-xs font-medium text-gray-800 dark:text-gray-100">Total Pending Drivers</p>
                                </div>
                            </div>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $driverStats['pending'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-sky-50 dark:bg-sky-900/20 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-500 text-white text-xs">
                                    <i class="fas fa-user-plus"></i>
                                </span>
                                <div>
                                    <p class="text-xs font-medium text-gray-800 dark:text-gray-100">New Applicants (7 days)</p>
                                </div>
                            </div>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $driverStats['new_applicants'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-rose-50 dark:bg-rose-900/20 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-500 text-white text-xs">
                                    <i class="fas fa-user-slash"></i>
                                </span>
                                <div>
                                    <p class="text-xs font-medium text-gray-800 dark:text-gray-100">Total Inactive Drivers</p>
                                </div>
                            </div>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $driverStats['inactive'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Driver Compliance Overview --}}
            <section class="col-span-1">
                <div class="h-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                            Driver Compliance Overview
                        </h2>
                        <a href="{{ route('admin.compliance.drivers') }}"
                           class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            View more →
                        </a>
                    </div>

                    @php
                        $driverTotalAlerts = max(1, $driverComplianceStats['overdue'] + $driverComplianceStats['due_soon']);
                        $driverOverduePct = round(($driverComplianceStats['overdue'] / $driverTotalAlerts) * 100);
                    @endphp

                    <div class="flex items-center gap-4 mt-2">
                        {{-- Simple radial meter --}}
                        <div class="relative flex items-center justify-center">
                            <div class="h-20 w-20 rounded-full border-8 border-gray-100 dark:border-gray-800"></div>
                            <div
                                class="absolute h-20 w-20 rounded-full border-8 border-transparent border-t-emerald-500 border-r-emerald-500 rotate-[{{ max(5, min(315, 315 - $driverOverduePct * 3.15)) }}deg]">
                            </div>
                            <div
                                class="absolute flex flex-col items-center justify-center h-12 w-12 rounded-full bg-white dark:bg-gray-900 shadow-sm">
                                <span class="text-xs font-semibold text-gray-900 dark:text-white">
                                    {{ 100 - $driverOverduePct }}%
                                </span>
                                <span class="text-[10px] text-gray-500 dark:text-gray-400">Compliant</span>
                            </div>
                        </div>

                        <div class="flex-1 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                    Overdue documents
                                </span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ $driverComplianceStats['overdue'] }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                    Due soon (30 days)
                                </span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ $driverComplianceStats['due_soon'] }}
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 pt-1">
                                Keep driver documents up to date to avoid compliance gaps.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Fleet Status --}}
            <section class="col-span-1 md:col-span-1">
                <div class="h-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                            Fleet Status
                        </h2>
                        <a href="{{ route('admin.vehicle.index') }}"
                           class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            View more →
                        </a>
                    </div>

                    <div class="space-y-2 mt-1">
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50 dark:bg-emerald-900/20 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-white text-xs">
                                    <i class="fas fa-truck-moving"></i>
                                </span>
                                <p class="text-xs font-medium text-gray-800 dark:text-gray-100">Active Units</p>
                            </div>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $fleetStats['units'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-sky-50 dark:bg-sky-900/20 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-500 text-white text-xs">
                                    <i class="fas fa-truck"></i>
                                </span>
                                <p class="text-xs font-medium text-gray-800 dark:text-gray-100">Total Trucks</p>
                            </div>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $fleetStats['vehicles'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-purple-50 dark:bg-purple-900/20 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-500 text-white text-xs">
                                    <i class="fas fa-truck-loading"></i>
                                </span>
                                <p class="text-xs font-medium text-gray-800 dark:text-gray-100">Total Trailers</p>
                            </div>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $fleetStats['trailers'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Fleet Compliance Dashboard --}}
            <section class="col-span-1 md:col-span-1">
                <div class="h-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text:white">
                            Fleet Compliance Dashboard
                        </h2>
                        <a href="{{ route('admin.compliance.fleet') }}"
                           class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            View more →
                        </a>
                    </div>

                    <div class="mt-2 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                                <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                Overdue asset documents
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $fleetComplianceStats['overdue'] }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                Due soon (30 days)
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $fleetComplianceStats['due_soon'] }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 pt-1">
                            Resolve overdue and upcoming expirations to keep your fleet fully compliant.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Maintenance Overview --}}
            <section class="col-span-1 md:col-span-2 xl:col-span-1">
                <div class="h-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                            Maintenance Overview
                        </h2>
                        <a href="{{ route('admin.service-log.index') }}"
                           class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                            View more →
                        </a>
                    </div>

                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 px-3 py-3">
                            <p class="text-[11px] text-emerald-700 dark:text-emerald-300 font-medium mb-1">
                                Service records
                            </p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ $maintenanceStats['total_services'] }}
                            </p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                All-time recorded services
                            </p>
                        </div>
                        <div class="rounded-xl bg-sky-50 dark:bg-sky-900/20 px-3 py-3">
                            <p class="text-[11px] text-sky-700 dark:text-sky-300 font-medium mb-1">
                                Last 30 days
                            </p>
                            <p class="text-xl font-bold text-gray-900 dark:text:white">
                                {{ $maintenanceStats['recent_30_days'] }}
                            </p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                Recent maintenance events
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Companies Overview (Admin only) --}}
            @if($isSuperAdmin && $companyStats)
                <section class="col-span-1 md:col-span-2 xl:col-span-1">
                    <div class="h-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                                Companies Overview
                            </h2>
                            <a href="{{ route('admin.settings.company') }}"
                               class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                Manage companies →
                            </a>
                        </div>

                        <dl class="mt-2 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-xl bg-gray-50 dark:bg-gray-800 px-2 py-3">
                                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Total</dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                                    {{ $companyStats['total_companies'] }}
                                </dd>
                            </div>
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 px-2 py-3">
                                <dt class="text-[11px] text-emerald-700 dark:text-emerald-300">Active</dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                                    {{ $companyStats['active_companies'] }}
                                </dd>
                            </div>
                            <div class="rounded-xl bg-rose-50 dark:bg-rose-900/20 px-2 py-3">
                                <dt class="text-[11px] text-rose-700 dark:text-rose-300">Inactive</dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                                    {{ $companyStats['inactive_companies'] }}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                            You are viewing an admin-wide summary across all companies.
                        </p>
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
