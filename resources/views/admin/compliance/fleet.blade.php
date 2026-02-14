@extends('layouts.main-layout')

@section('title', 'Fleet Compliance')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Fleet Compliance Dashboard</h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Monitor document compliance across <span
                            class="font-semibold text-brand-600">{{ $totalVehicles + $totalTrailers }}</span> vehicles &
                        trailers</p>
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Reports & Records Button -->
                    {{-- {{ route('admin.reports.index') ?? '#' }} --}}
                    <a href=""
                        class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors duration-200 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700">
                        <i class="fas fa-file-alt mr-2"></i>Reports & Records
                    </a>

                    <!-- Driver Management Button -->
                    <a href="{{ route('admin.driver.index') }}"
                        class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white transition-colors duration-200 bg-brand-600 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-700 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                        <i class="fas fa-users mr-2"></i>Driver Management
                    </a>

                    <!-- Refresh Button -->
                    <button id="refresh-dashboard"
                        class="inline-flex items-center justify-center p-2.5 text-gray-700 transition-colors duration-200 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700">
                        <i class="fas fa-sync-alt"></i>
                        <span class="sr-only">Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 mt-6 md:grid-cols-4">
                <!-- Total Vehicles -->
                <div
                    class="p-4 bg-white border border-gray-200 rounded-xl shadow-theme-xs dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-brand-50 dark:bg-brand-900/20">
                            <i class="text-lg text-brand-600 fas fa-truck dark:text-brand-400"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Vehicles</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $totalVehicles }}</p>
                        </div>
                    </div>
                </div>

                <!-- Total Trailers -->
                <div
                    class="p-4 bg-white border border-gray-200 rounded-xl shadow-theme-xs dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                            <i class="text-lg text-blue-600 fas fa-trailer dark:text-blue-400"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Trailers</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $totalTrailers }}</p>
                        </div>
                    </div>
                </div>

                <!-- Compliant -->
                <div
                    class="p-4 bg-white border border-gray-200 rounded-xl shadow-theme-xs dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                            <i class="text-lg text-emerald-600 fas fa-check-circle dark:text-emerald-400"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Compliant</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $totalCompliant }}</p>
                        </div>
                    </div>
                </div>

                <!-- Warning/Expiring -->
                <div
                    class="p-4 bg-white border border-gray-200 rounded-xl shadow-theme-xs dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/20">
                            <i class="text-lg text-amber-600 fas fa-exclamation-triangle dark:text-amber-400"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Warning</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $totalWarning }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column - Vehicle Compliance -->
            <div class="lg:col-span-2">
                <div
                    class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Vehicle Compliance Status</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Grouped by vehicle - all document
                                issues</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span
                                class="px-2.5 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                {{ $totalWarning }} Vehicles Need Attention
                            </span>
                        </div>
                    </div>

                    <!-- Vehicle Compliance List -->
                    <div class="space-y-4">
                        @forelse($vehicles as $vehicle)
                            <div
                                class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 
                                @if ($vehicle['compliance_status'] === 'warning') bg-amber-50/50 dark:bg-amber-900/5 
                                @elseif($vehicle['compliance_status'] === 'danger') bg-red-50/50 dark:bg-red-900/5 
                                @else bg-gray-50/50 dark:bg-gray-900/5 @endif">

                                <!-- Vehicle Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg 
                                            @if ($vehicle['compliance_status'] === 'warning') bg-amber-100 dark:bg-amber-900/30 
                                            @elseif($vehicle['compliance_status'] === 'danger') bg-red-100 dark:bg-red-900/30 
                                            @else bg-emerald-100 dark:bg-emerald-900/30 @endif">
                                            <i
                                                class="text-sm 
                                                @if ($vehicle['compliance_status'] === 'warning') text-amber-600 dark:text-amber-400 
                                                @elseif($vehicle['compliance_status'] === 'danger') text-red-600 dark:text-red-400 
                                                @else text-emerald-600 dark:text-emerald-400 @endif fas fa-truck"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center">
                                                <h3 class="font-bold text-gray-900 dark:text-white">
                                                    #{{ $vehicle['unit_no'] }}
                                                    @if ($vehicle['compliance_status'] === 'warning')
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Warning</span>
                                                    @elseif($vehicle['compliance_status'] === 'danger')
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Critical</span>
                                                    @else
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Compliant</span>
                                                    @endif
                                                </h3>
                                            </div>
                                            <div class="flex items-center mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                <span>{{ $vehicle['compliance_percentage'] }}
                                                    ({{ $vehicle['compliant_docs'] }}/{{ $vehicle['total_docs'] }})
                                                </span>
                                                <span class="mx-2">•</span>
                                                <span class="flex items-center">
                                                    <i class="mr-1 text-xs fas fa-user"></i>
                                                    @if ($vehicle['driver'])
                                                        {{ $vehicle['driver']->first_name }}
                                                        {{ $vehicle['driver']->last_name }}
                                                    @else
                                                        No assigned driver
                                                    @endif
                                                </span>
                                                @if ($vehicle['odometer'] > 0)
                                                    <span class="mx-2">•</span>
                                                    <span>{{ number_format($vehicle['odometer']) }} miles</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="showVehicleDetails({{ $vehicle['id'] }})"
                                        class="px-3 py-1.5 text-sm font-medium text-brand-600 transition-colors duration-200 bg-white border border-brand-300 rounded-lg hover:bg-brand-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:bg-gray-800 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                </div>

                                <!-- Document Details -->
                                <div class="space-y-2">
                                    @foreach ($vehicle['document_details'] as $docDetail)
                                        <div
                                            class="flex items-center justify-between py-2 px-3 rounded-lg border dark:bg-zinc-900 border-zinc-100 hover:border-zinc-200 transition-colors 
                                            @if ($docDetail['status'] === 'missing' || $docDetail['status'] === 'expired') border-l-4 border-l-red-400
                                            @elseif($docDetail['status'] === 'expiring') border-l-4 border-l-amber-400
                                            @else border-l-4 border-l-emerald-400 @endif">

                                            <div class="flex items-center gap-3 flex-1">
                                                <svg class="w-4 h-4 text-gray-500 flex-shrink-0"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                                                    <path
                                                        d="M14.4998 19H12.4998C9.67139 19 8.25718 19 7.3785 18.1213C6.49982 17.2426 6.49982 15.8284 6.49982 13V8C6.49982 5.17157 6.49982 3.75736 7.3785 2.87868C8.25718 2 9.67139 2 12.4998 2H13.843C14.6605 2 15.0692 2 15.4368 2.15224C15.8043 2.30448 16.0933 2.59351 16.6714 3.17157L19.3282 5.82843C19.9063 6.40648 20.1953 6.69552 20.3476 7.06306C20.4998 7.4306 20.4998 7.83935 20.4998 8.65685V13C20.4998 15.8284 20.4998 17.2426 19.6211 18.1213C18.7425 19 17.3282 19 14.4998 19Z"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                                    </path>
                                                    <path d="M10 11H14M10 15H17" stroke="currentColor" stroke-width="1.5"
                                                        stroke-linecap="round"></path>
                                                </svg>
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-medium text-sm text-zinc-800 dark:text-white">
                                                        {{ $docDetail['type_name'] }}
                                                    </div>
                                                    @if ($docDetail['expiry_date'])
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                                            Expires:
                                                            {{ \Carbon\Carbon::parse($docDetail['expiry_date'])->format('M d, Y') }}
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    @if ($docDetail['status'] === 'missing')
                                                        <span
                                                            class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                                            Missing
                                                        </span>
                                                    @elseif($docDetail['status'] === 'expired')
                                                        <span
                                                            class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                                            Expired
                                                        </span>
                                                    @elseif($docDetail['status'] === 'expiring')
                                                        <span
                                                            class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                                            Expiring Soon
                                                        </span>
                                                    @else
                                                        <span
                                                            class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                            Valid
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                                                @if ($docDetail['status'] !== 'valid')
                                                    <button type="button"
                                                        onclick="openUploadModal({{ $vehicle['id'] }}, {{ $docDetail['type_id'] }}, 'vehicle')"
                                                        class="h-6 text-xs rounded-md px-2 inline-flex items-center font-medium bg-brand-600 hover:bg-brand-700 text-white border border-black/10 dark:border-0">
                                                        Complete
                                                    </button>
                                                @endif
                                                <button type="button"
                                                    onclick="sendReminderEmail({{ $vehicle['id'] }}, {{ $docDetail['type_id'] }}, 'vehicle')"
                                                    class="h-6 w-6 rounded-md inline-flex items-center justify-center bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800 dark:text-white">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16">
                                                        <path
                                                            d="M8 2a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM8 6.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM9.5 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center border border-gray-200 rounded-lg dark:border-gray-700">
                                <i class="mb-3 text-3xl text-gray-400 fas fa-truck"></i>
                                <h3 class="mb-2 text-lg font-medium text-gray-700 dark:text-gray-300">No Vehicles Found
                                </h3>
                                <p class="text-gray-500 dark:text-gray-400">Add vehicles to start tracking compliance</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Trailers Section -->
                <div
                    class="mt-6 p-5 bg-white border border-gray-200 rounded-2xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Trailer Compliance</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Trailer document status</p>
                        </div>
                        <span
                            class="px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            {{ $totalTrailers }} Trailers
                        </span>
                    </div>

                    <!-- Trailers List -->
                    <div class="space-y-3">
                        @forelse($trailers as $trailer)
                            <div
                                class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 
                                @if ($trailer['compliance_status'] === 'warning') bg-amber-50/50 dark:bg-amber-900/5 
                                @elseif($trailer['compliance_status'] === 'danger') bg-red-50/50 dark:bg-red-900/5 
                                @else bg-gray-50/50 dark:bg-gray-900/5 @endif">

                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg 
                                            @if ($trailer['compliance_status'] === 'warning') bg-amber-100 dark:bg-amber-900/30 
                                            @elseif($trailer['compliance_status'] === 'danger') bg-red-100 dark:bg-red-900/30 
                                            @else bg-emerald-100 dark:bg-emerald-900/30 @endif">
                                            <i
                                                class="text-sm 
                                                @if ($trailer['compliance_status'] === 'warning') text-amber-600 dark:text-amber-400 
                                                @elseif($trailer['compliance_status'] === 'danger') text-red-600 dark:text-red-400 
                                                @else text-emerald-600 dark:text-emerald-400 @endif fas fa-trailer"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center">
                                                <h3 class="font-bold text-gray-900 dark:text-white">
                                                    #{{ $trailer['unit_no'] }}
                                                    @if ($trailer['compliance_status'] === 'warning')
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Warning</span>
                                                    @elseif($trailer['compliance_status'] === 'danger')
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Critical</span>
                                                    @else
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Compliant</span>
                                                    @endif
                                                </h3>
                                            </div>
                                            <div class="flex items-center mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                <span>{{ $trailer['compliance_percentage'] }}
                                                    ({{ $trailer['compliant_docs'] }}/{{ $trailer['total_docs'] }})
                                                </span>
                                                <span class="mx-2">•</span>
                                                <span class="flex items-center">
                                                    <i class="mr-1 text-xs fas fa-user"></i>
                                                    @if ($trailer['driver'])
                                                        {{ $trailer['driver']->first_name }}
                                                        {{ $trailer['driver']->last_name }}
                                                    @else
                                                        No assigned driver
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="showTrailerDetails({{ $trailer['id'] }})"
                                        class="px-3 py-1.5 text-sm font-medium text-brand-600 transition-colors duration-200 bg-white border border-brand-300 rounded-lg hover:bg-brand-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:bg-gray-800 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                </div>

                                <!-- Document Details for Trailer -->
                                <div class="space-y-2">
                                    @foreach ($trailer['document_details'] as $docDetail)
                                        <div
                                            class="flex items-center justify-between py-2 px-3 rounded-lg border dark:bg-zinc-900 border-zinc-100 hover:border-zinc-200 transition-colors 
                                            @if ($docDetail['status'] === 'missing' || $docDetail['status'] === 'expired') border-l-4 border-l-red-400
                                            @elseif($docDetail['status'] === 'expiring') border-l-4 border-l-amber-400
                                            @else border-l-4 border-l-emerald-400 @endif">

                                            <div class="flex items-center gap-3 flex-1">
                                                <svg class="w-4 h-4 text-gray-500 flex-shrink-0"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    fill="none">
                                                    <path
                                                        d="M14.4998 19H12.4998C9.67139 19 8.25718 19 7.3785 18.1213C6.49982 17.2426 6.49982 15.8284 6.49982 13V8C6.49982 5.17157 6.49982 3.75736 7.3785 2.87868C8.25718 2 9.67139 2 12.4998 2H13.843C14.6605 2 15.0692 2 15.4368 2.15224C15.8043 2.30448 16.0933 2.59351 16.6714 3.17157L19.3282 5.82843C19.9063 6.40648 20.1953 6.69552 20.3476 7.06306C20.4998 7.4306 20.4998 7.83935 20.4998 8.65685V13C20.4998 15.8284 20.4998 17.2426 19.6211 18.1213C18.7425 19 17.3282 19 14.4998 19Z"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                                    </path>
                                                    <path d="M10 11H14M10 15H17" stroke="currentColor" stroke-width="1.5"
                                                        stroke-linecap="round"></path>
                                                </svg>
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-medium text-sm text-zinc-800 dark:text-white">
                                                        {{ $docDetail['type_name'] }}
                                                    </div>
                                                    @if ($docDetail['expiry_date'])
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                                            Expires:
                                                            {{ \Carbon\Carbon::parse($docDetail['expiry_date'])->format('M d, Y') }}
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    @if ($docDetail['status'] === 'missing')
                                                        <span
                                                            class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                                            Missing
                                                        </span>
                                                    @elseif($docDetail['status'] === 'expired')
                                                        <span
                                                            class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                                            Expired
                                                        </span>
                                                    @elseif($docDetail['status'] === 'expiring')
                                                        <span
                                                            class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                                            Expiring Soon
                                                        </span>
                                                    @else
                                                        <span
                                                            class="inline-flex items-center text-xs py-1 px-2 rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                            Valid
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                                                @if ($docDetail['status'] !== 'valid')
                                                    <button type="button"
                                                        onclick="openUploadModal({{ $trailer['id'] }}, {{ $docDetail['type_id'] }}, 'trailer')"
                                                        class="h-6 text-xs rounded-md px-2 inline-flex items-center font-medium bg-brand-600 hover:bg-brand-700 text-white border border-black/10 dark:border-0">
                                                        Complete
                                                    </button>
                                                @endif
                                                <button type="button"
                                                    onclick="sendReminderEmail({{ $trailer['id'] }}, {{ $docDetail['type_id'] }}, 'trailer')"
                                                    class="h-6 w-6 rounded-md inline-flex items-center justify-center bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800 dark:text-white">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16">
                                                        <path
                                                            d="M8 2a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM8 6.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM9.5 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center border border-gray-200 rounded-lg dark:border-gray-700">
                                <i class="mb-3 text-3xl text-gray-400 fas fa-trailer"></i>
                                <h3 class="mb-2 text-lg font-medium text-gray-700 dark:text-gray-300">No Trailers Found
                                </h3>
                                <p class="text-gray-500 dark:text-gray-400">Add trailers to start tracking compliance</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column - Quick Actions & Summary -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div
                    class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-white/90">Quick Actions</h2>
                    <div class="space-y-3">
                        <a href="{{ route('admin.vehicle.index') }}"
                            class="flex items-center justify-between p-3 transition-colors duration-200 border border-gray-200 rounded-lg hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                            <div class="flex items-center">
                                <div
                                    class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-brand-50 dark:bg-brand-900/20">
                                    <i class="text-brand-600 fas fa-plus dark:text-brand-400"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white">Add New Vehicle</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Register a new vehicle</p>
                                </div>
                            </div>
                            <i class="text-gray-400 fas fa-chevron-right"></i>
                        </a>

                        <a href="{{ route('admin.trailer.index') }}"
                            class="flex items-center justify-between p-3 transition-colors duration-200 border border-gray-200 rounded-lg hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                            <div class="flex items-center">
                                <div
                                    class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                                    <i class="text-blue-600 fas fa-trailer dark:text-blue-400"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white">Add New Trailer</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Register a new trailer</p>
                                </div>
                            </div>
                            <i class="text-gray-400 fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Compliance Summary -->
                <div
                    class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-white/90">Compliance Summary</h2>
                    <div class="space-y-4">
                        <!-- Overall Progress -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Overall
                                    Compliance</span>
                                <span
                                    class="text-sm font-bold text-gray-900 dark:text-white">{{ $overallCompliance }}%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                <div class="h-full rounded-full bg-brand-600" style="width: {{ $overallCompliance }}%">
                                </div>
                            </div>
                        </div>

                        <!-- By Category -->
                        <div class="space-y-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Vehicles</span>
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white">{{ $vehicleCompliance }}%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-emerald-500"
                                        style="width:{{ $vehicleCompliance }}%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Trailers</span>
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white">{{ $trailerCompliance }}%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-blue-500"
                                        style="width: {{ $trailerCompliance }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vehicle Details Modal -->
    <div id="vehicleDetailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div
                class="inline-block w-full overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl dark:bg-gray-800">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full mt-3 sm:mt-0 sm:ml-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold leading-6 text-gray-900 dark:text-white"
                                    id="vehicleModalTitle"></h3>
                                <button type="button" onclick="closeVehicleModal()"
                                    class="text-gray-400 hover:text-gray-500">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="vehicleDetailsContent">
                                <!-- Content will be loaded via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Upload Modal -->
    @include('admin.compliance.partials.upload-document-modal')

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
@endsection

@push('scripts')
    <script>
        // Refresh Dashboard
        document.getElementById('refresh-dashboard')?.addEventListener('click', function() {
            showToast('Refreshing dashboard...', 'info');
            setTimeout(() => {
                location.reload();
            }, 500);
        });

        // Vehicle Details Modal Functions
        function showVehicleDetails(vehicleId) {
            document.getElementById('vehicleDetailsContent').innerHTML = `
                <div class="flex items-center justify-center py-12">
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-brand-600"></div>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Loading vehicle details...</p>
                    </div>
                </div>
            `;

            fetch(`/admin/compliance/vehicles/${vehicleId}/details`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const vehicle = data.vehicle;
                        document.getElementById('vehicleModalTitle').textContent =
                            `Vehicle #${vehicle.unit_no} Details`;

                        const missingDocsHtml = vehicle.missing_documents && vehicle.missing_documents.length > 0 ? `
                            <div>
                                <h4 class="mb-2 font-medium text-gray-900 dark:text-white">Missing/Expiring Documents:</h4>
                                <div class="space-y-2">
                                    ${vehicle.missing_documents.map(doc => `
                                                <div class="flex items-center p-2 border border-gray-200 rounded-lg dark:border-gray-700">
                                                    <i class="mr-3 text-amber-500 fas fa-exclamation-circle"></i>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">${doc}</span>
                                                </div>
                                            `).join('')}
                                </div>
                            </div>
                        ` : '';

                        document.getElementById('vehicleDetailsContent').innerHTML = `
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg dark:bg-gray-700">
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Make/Model</p>
                                        <p class="font-medium text-gray-900 dark:text-white">${vehicle.make} ${vehicle.model}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Year</p>
                                        <p class="font-medium text-gray-900 dark:text-white">${vehicle.year}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">VIN</p>
                                        <p class="font-medium text-gray-900 dark:text-white">${vehicle.vin}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Odometer</p>
                                        <p class="font-medium text-gray-900 dark:text-white">${vehicle.odometer.toLocaleString()} miles</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="mb-2 font-medium text-gray-900 dark:text-white">Compliance Status</h4>
                                    <div class="flex items-center justify-between p-3 rounded-lg ${vehicle.compliance_status === 'warning' ? 'bg-amber-50 dark:bg-amber-900/10' : vehicle.compliance_status === 'danger' ? 'bg-red-50 dark:bg-red-900/10' : 'bg-emerald-50 dark:bg-emerald-900/10'}">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">${vehicle.compliance_percentage} Compliance</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">${vehicle.compliant_docs} of ${vehicle.total_docs} documents</p>
                                        </div>
                                        <span class="px-3 py-1 text-sm font-medium rounded-full ${vehicle.compliance_status === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : vehicle.compliance_status === 'danger' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'}">
                                            ${vehicle.compliance_status === 'warning' ? 'Warning' : vehicle.compliance_status === 'danger' ? 'Critical' : 'Compliant'}
                                        </span>
                                    </div>
                                </div>
                                
                                ${missingDocsHtml}
                                
                                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex justify-end space-x-3">
                                        <button type="button" onclick="closeVehicleModal()" class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors duration-200 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        document.getElementById('vehicleDetailsContent').innerHTML = `
                            <div class="py-12 text-center">
                                <i class="mb-3 text-3xl text-red-500 fas fa-exclamation-circle"></i>
                                <p class="text-gray-700 dark:text-gray-300">Failed to load vehicle details</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('vehicleDetailsContent').innerHTML = `
                        <div class="py-12 text-center">
                            <i class="mb-3 text-3xl text-red-500 fas fa-exclamation-circle"></i>
                            <p class="text-gray-700 dark:text-gray-300">Error loading vehicle details</p>
                        </div>
                    `;
                });

            document.getElementById('vehicleDetailsModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function showTrailerDetails(trailerId) {
            // Similar implementation for trailers
            showToast('Trailer details coming soon', 'info');
        }

        function closeVehicleModal() {
            document.getElementById('vehicleDetailsModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Function to get CSRF token from multiple sources
        function getCsrfToken() {
            // Try meta tag first
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag && metaTag.content) {
                return metaTag.content;
            }

            // Try to find CSRF token in form
            const csrfInput = document.querySelector('input[name="_token"]');
            if (csrfInput && csrfInput.value) {
                return csrfInput.value;
            }

            // Fallback: try to get from window.Laravel if available
            if (window.Laravel && window.Laravel.csrfToken) {
                return window.Laravel.csrfToken;
            }

            console.error('CSRF token not found');
            return '';
        }

        // Update sendReminderEmail function
        function sendReminderEmail(assetId, docTypeId, assetType) {
            if (!confirm('Send reminder email to the assigned driver?')) {
                return;
            }

            const csrfToken = getCsrfToken();
            if (!csrfToken) {
                showToast('Security token not found. Please refresh the page.', 'error');
                return;
            }

            fetch('/admin/compliance/documents/send-reminder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        asset_id: assetId,
                        document_type_id: docTypeId,
                        asset_type: assetType
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Reminder sent successfully', 'success');
                    } else {
                        showToast(data.message || 'Failed to send reminder', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error sending reminder: ' + error.message, 'error');
                });
        }

        // Toast Notification Function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
            const toastId = 'toast-' + Date.now();
            const toast = document.createElement('div');
            toast.id = toastId;

            const bgColor = type === 'success' ?
                'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' :
                type === 'error' ? 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800' :
                'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800';

            const icon = type === 'success' ? 'fas fa-check-circle text-emerald-500' :
                type === 'error' ? 'fas fa-exclamation-circle text-red-500' :
                'fas fa-info-circle text-blue-500';

            toast.className =
                `flex items-center p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full ${bgColor} border`;
            toast.innerHTML = `
                <i class="${icon} mr-3"></i>
                <span class="text-sm font-medium text-gray-900 dark:text-white">${message}</span>
                <button onclick="document.getElementById('${toastId}').remove()" class="ml-4 text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            `;

            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
                toast.classList.add('translate-x-0');
            }, 10);

            setTimeout(() => {
                if (document.getElementById(toastId)) {
                    toast.classList.remove('translate-x-0');
                    toast.classList.add('translate-x-full');
                    setTimeout(() => {
                        if (document.getElementById(toastId)) {
                            document.getElementById(toastId).remove();
                        }
                    }, 300);
                }
            }, 5000);
        }

        // Close modal when clicking outside
        document.getElementById('vehicleDetailsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeVehicleModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeVehicleModal();
                closeUploadModal();
            }
        });
    </script>
@endpush
