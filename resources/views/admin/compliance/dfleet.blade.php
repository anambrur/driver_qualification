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
                    <!-- Search Input -->
                    <div class="relative">
                        <input type="text" 
                               placeholder="Search vehicle or driver..." 
                               class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                        <i class="absolute left-3 top-3 text-gray-400 fas fa-search"></i>
                    </div>

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
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Grouped by vehicle - all document issues</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($warningVehicles > 0)
                            <span
                                class="px-2.5 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                {{ $warningVehicles }} Vehicle{{ $warningVehicles > 1 ? 's' : '' }} Need Attention
                            </span>
                            @endif
                        </div>
                    </div>

                    <!-- Vehicle Compliance List -->
                    <div class="space-y-4">
                        @forelse($vehicles as $vehicle)
                            <div
                                class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 @if ($vehicle->compliance_status === 'warning') bg-amber-50/50 dark:bg-amber-900/5 @elseif($vehicle->compliance_status === 'danger') bg-red-50/50 dark:bg-red-900/5 @else bg-gray-50/50 dark:bg-gray-900/5 @endif">
                                <!-- Vehicle Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg @if ($vehicle->compliance_status === 'warning') bg-amber-100 dark:bg-amber-900/30 @elseif($vehicle->compliance_status === 'danger') bg-red-100 dark:bg-red-900/30 @else bg-gray-100 dark:bg-gray-700 @endif">
                                            <i
                                                class="text-sm @if ($vehicle->compliance_status === 'warning') text-amber-600 dark:text-amber-400 @elseif($vehicle->compliance_status === 'danger') text-red-600 dark:text-red-400 @else text-gray-600 dark:text-gray-400 @endif fas fa-truck"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center">
                                                <h3 class="font-bold text-gray-900 dark:text-white">
                                                    #{{ $vehicle->unit_no }}
                                                    @if ($vehicle->compliance_status === 'warning')
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Warning</span>
                                                    @elseif($vehicle->compliance_status === 'danger')
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">0% ({{ $vehicle->compliant_docs }}/{{ $vehicle->total_docs }})</span>
                                                    @else
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Compliant</span>
                                                    @endif
                                                </h3>
                                            </div>
                                            <div class="flex items-center mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                <span class="flex items-center">
                                                    <i class="mr-1 text-xs fas fa-user"></i>
                                                    @if($vehicle->assetGroups && $vehicle->assetGroups->driver)
                                                        {{ $vehicle->assetGroups->driver->first_name ?? 'Demo Driver' }} {{ $vehicle->assetGroups->driver->last_name ?? '' }}
                                                    @else
                                                        No assigned driver
                                                    @endif
                                                </span>
                                                @if ($vehicle->odometer > 0)
                                                    <span class="mx-2">•</span>
                                                    <span>{{ number_format($vehicle->odometer) }} miles</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="showVehicleDetails({{ $vehicle->id }})"
                                        class="px-3 py-1.5 text-sm font-medium text-brand-600 transition-colors duration-200 bg-white border border-brand-300 rounded-lg hover:bg-brand-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:bg-gray-800 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                </div>

                                <!-- Missing Documents List -->
                                @if($vehicle->compliance_status !== 'compliant')
                                <div class="space-y-2">
                                    @foreach($vehicleDocumentTypes as $docType)
                                        @php
                                            $docStatus = 'missing';
                                            if($vehicle->documents) {
                                                foreach($vehicle->documents as $doc) {
                                                    if($doc->document_type_id == $docType->id) {
                                                        if(is_null($doc->expiry_date) || $doc->expiry_date >= now()->toDateString()) {
                                                            $docStatus = 'valid';
                                                        } else {
                                                            $docStatus = 'expired';
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                        @endphp
                                        
                                        @if($docStatus !== 'valid')
                                        <div class="flex items-center justify-between py-2 px-3 rounded-lg border dark:bg-zinc-900 border-zinc-100 hover:border-zinc-200 transition-colors border-l-4 border-l-gray-400">
                                            <div class="flex items-center gap-3 flex-1">
                                                <svg class="w-4 h-4 text-gray-500 flex-shrink-0"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24"
                                                    height="24" color="#000000" fill="none">
                                                    <path
                                                        d="M14.4998 19H12.4998C9.67139 19 8.25718 19 7.3785 18.1213C6.49982 17.2426 6.49982 15.8284 6.49982 13V8C6.49982 5.17157 6.49982 3.75736 7.3785 2.87868C8.25718 2 9.67139 2 12.4998 2H13.843C14.6605 2 15.0692 2 15.4368 2.15224C15.8043 2.30448 16.0933 2.59351 16.6714 3.17157L19.3282 5.82843C19.9063 6.40648 20.1953 6.69552 20.3476 7.06306C20.4998 7.4306 20.4998 7.83935 20.4998 8.65685V13C20.4998 15.8284 20.4998 17.2426 19.6211 18.1213C18.7425 19 17.3282 19 14.4998 19Z"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                    </path>
                                                    <path
                                                        d="M14.9998 2.5V3.5C14.9998 5.38562 14.9998 6.32843 15.5856 6.91421C16.1714 7.5 17.1142 7.5 18.9998 7.5H19.9998"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                    </path>
                                                    <path
                                                        d="M6.49942 5C4.84257 5 3.49942 6.34315 3.49942 8V16C3.49942 18.8285 3.49942 20.2427 4.3781 21.1213C5.25678 22 6.67099 22 9.49942 22H14.4998C16.1566 22 17.4998 20.6568 17.4998 19"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                    </path>
                                                    <path d="M10 11H14M10 15H17" stroke="currentColor" stroke-width="1.5"
                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <div class="font-medium text-zinc-800 dark:text-white text-sm">
                                                            {{ $docType->name }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="inline-flex items-center font-medium whitespace-nowrap text-xs py-1 rounded-md px-2 text-zinc-700 dark:text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40">
                                                        @if($docStatus === 'expired')
                                                            Expired
                                                        @else
                                                            Missing
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                                                <button type="button"
                                                    class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-6 text-xs rounded-md px-2 inline-flex bg-brand-600 hover:bg-brand-700 text-white border border-transparent"
                                                    onclick="uploadDocument({{ $vehicle->id }}, {{ $docType->id }}, 'vehicle')">
                                                    Complete
                                                </button>
                                                <div class="relative">
                                                    <button type="button"
                                                        class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-6 text-xs rounded-md w-6 inline-flex bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800 dark:text-white"
                                                        onclick="toggleDropdown('vehicle-{{ $vehicle->id }}-{{ $docType->id }}')">
                                                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"
                                                            fill="currentColor" aria-hidden="true">
                                                            <path
                                                                d="M8 2a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM8 6.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM9.5 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                    <div id="vehicle-{{ $vehicle->id }}-{{ $docType->id }}-dropdown"
                                                        class="absolute right-0 z-10 hidden w-48 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                                                        <div class="py-1">
                                                            <button type="button"
                                                                class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                                                onclick="sendReminder({{ $vehicle->id }}, '{{ $docType->name }}', 'vehicle')">
                                                                <svg class="w-4 h-4 mr-2" width="24" height="24"
                                                                    viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path
                                                                        d="M22 12.5001C22 12.0087 21.9947 11.0172 21.9842 10.5244C21.9189 7.45886 21.8862 5.92609 20.7551 4.79066C19.6239 3.65523 18.0497 3.61568 14.9012 3.53657C12.9607 3.48781 11.0393 3.48781 9.09882 3.53656C5.95033 3.61566 4.37608 3.65521 3.24495 4.79065C2.11382 5.92608 2.08114 7.45885 2.01576 10.5244C1.99474 11.5101 1.99475 12.4899 2.01577 13.4756C2.08114 16.5412 2.11383 18.0739 3.24496 19.2094C4.37608 20.3448 5.95033 20.3843 9.09883 20.4634C9.90159 20.4836 10.7011 20.4954 11.5 20.4989"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path
                                                                        d="M2 6L8.91302 9.92462C11.4387 11.3585 12.5613 11.3585 15.087 9.92462L22 6"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linejoin="round"></path>
                                                                    <path
                                                                        d="M22 17.5L14 17.5M22 17.5C22 16.7998 20.0057 15.4915 19.5 15M22 17.5C22 18.2002 20.0057 19.5085 19.5 20"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                </svg> Send Reminder Email
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                                @else
                                <div class="py-3 text-center text-gray-600 dark:text-gray-400">
                                    <i class="fas fa-check-circle text-emerald-500 mr-2"></i>
                                    All documents are compliant
                                </div>
                                @endif
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

                <!-- Trailers Compliance -->
                <div class="mt-6 p-5 bg-white border border-gray-200 rounded-2xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Trailer Compliance</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Trailer document status</p>
                        </div>
                        @if($warningTrailers > 0)
                        <span
                            class="px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            {{ $warningTrailers }} Trailer{{ $warningTrailers > 1 ? 's' : '' }}
                        </span>
                        @endif
                    </div>

                    <!-- Trailers List -->
                    <div class="space-y-4">
                        @forelse($trailers as $trailer)
                            <div
                                class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 @if ($trailer->compliance_status === 'warning') bg-amber-50/50 dark:bg-amber-900/5 @elseif($trailer->compliance_status === 'danger') bg-red-50/50 dark:bg-red-900/5 @else bg-gray-50/50 dark:bg-gray-900/5 @endif">
                                <!-- Trailer Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg @if ($trailer->compliance_status === 'warning') bg-amber-100 dark:bg-amber-900/30 @elseif($trailer->compliance_status === 'danger') bg-red-100 dark:bg-red-900/30 @else bg-gray-100 dark:bg-gray-700 @endif">
                                            <i
                                                class="text-sm @if ($trailer->compliance_status === 'warning') text-amber-600 dark:text-amber-400 @elseif($trailer->compliance_status === 'danger') text-red-600 dark:text-red-400 @else text-gray-600 dark:text-gray-400 @endif fas fa-trailer"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center">
                                                <h3 class="font-bold text-gray-900 dark:text-white">
                                                    #{{ $trailer->unit_no }}
                                                    @if ($trailer->compliance_status === 'warning')
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Warning</span>
                                                    @elseif($trailer->compliance_status === 'danger')
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">0% ({{ $trailer->compliant_docs }}/{{ $trailer->total_docs }})</span>
                                                    @else
                                                        <span
                                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Compliant</span>
                                                    @endif
                                                </h3>
                                            </div>
                                            <div class="flex items-center mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                <span class="flex items-center">
                                                    <i class="mr-1 text-xs fas fa-user"></i>
                                                    @if($trailer->assetGroups && $trailer->assetGroups->driver)
                                                        {{ $trailer->assetGroups->driver->first_name ?? 'Demo Driver' }} {{ $trailer->assetGroups->driver->last_name ?? '' }}
                                                    @else
                                                        No assigned driver
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="showTrailerDetails({{ $trailer->id }})"
                                        class="px-3 py-1.5 text-sm font-medium text-brand-600 transition-colors duration-200 bg-white border border-brand-300 rounded-lg hover:bg-brand-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:bg-gray-800 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                </div>

                                <!-- Missing Documents List -->
                                @if($trailer->compliance_status !== 'compliant')
                                <div class="space-y-2">
                                    @foreach($trailerDocumentTypes as $docType)
                                        @php
                                            $docStatus = 'missing';
                                            if($trailer->documents) {
                                                foreach($trailer->documents as $doc) {
                                                    if($doc->document_type_id == $docType->id) {
                                                        if(is_null($doc->expiry_date) || $doc->expiry_date >= now()->toDateString()) {
                                                            $docStatus = 'valid';
                                                        } else {
                                                            $docStatus = 'expired';
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                        @endphp
                                        
                                        @if($docStatus !== 'valid')
                                        <div class="flex items-center justify-between py-2 px-3 rounded-lg border dark:bg-zinc-900 border-zinc-100 hover:border-zinc-200 transition-colors border-l-4 border-l-gray-400">
                                            <div class="flex items-center gap-3 flex-1">
                                                <svg class="w-4 h-4 text-gray-500 flex-shrink-0"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24"
                                                    height="24" color="#000000" fill="none">
                                                    <path
                                                        d="M14.4998 19H12.4998C9.67139 19 8.25718 19 7.3785 18.1213C6.49982 17.2426 6.49982 15.8284 6.49982 13V8C6.49982 5.17157 6.49982 3.75736 7.3785 2.87868C8.25718 2 9.67139 2 12.4998 2H13.843C14.6605 2 15.0692 2 15.4368 2.15224C15.8043 2.30448 16.0933 2.59351 16.6714 3.17157L19.3282 5.82843C19.9063 6.40648 20.1953 6.69552 20.3476 7.06306C20.4998 7.4306 20.4998 7.83935 20.4998 8.65685V13C20.4998 15.8284 20.4998 17.2426 19.6211 18.1213C18.7425 19 17.3282 19 14.4998 19Z"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                    </path>
                                                    <path
                                                        d="M14.9998 2.5V3.5C14.9998 5.38562 14.9998 6.32843 15.5856 6.91421C16.1714 7.5 17.1142 7.5 18.9998 7.5H19.9998"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                    </path>
                                                    <path
                                                        d="M6.49942 5C4.84257 5 3.49942 6.34315 3.49942 8V16C3.49942 18.8285 3.49942 20.2427 4.3781 21.1213C5.25678 22 6.67099 22 9.49942 22H14.4998C16.1566 22 17.4998 20.6568 17.4998 19"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                    </path>
                                                    <path d="M10 11H14M10 15H17" stroke="currentColor" stroke-width="1.5"
                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <div class="font-medium text-zinc-800 dark:text-white text-sm">
                                                            {{ $docType->name }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="inline-flex items-center font-medium whitespace-nowrap text-xs py-1 rounded-md px-2 text-zinc-700 dark:text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40">
                                                        @if($docStatus === 'expired')
                                                            Expired
                                                        @else
                                                            Missing
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                                                <button type="button"
                                                    class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-6 text-xs rounded-md px-2 inline-flex bg-brand-600 hover:bg-brand-700 text-white border border-transparent"
                                                    onclick="uploadDocument({{ $trailer->id }}, {{ $docType->id }}, 'trailer')">
                                                    Complete
                                                </button>
                                                <div class="relative">
                                                    <button type="button"
                                                        class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-6 text-xs rounded-md w-6 inline-flex bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800 dark:text-white"
                                                        onclick="toggleDropdown('trailer-{{ $trailer->id }}-{{ $docType->id }}')">
                                                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"
                                                            fill="currentColor" aria-hidden="true">
                                                            <path
                                                                d="M8 2a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM8 6.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM9.5 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                    <div id="trailer-{{ $trailer->id }}-{{ $docType->id }}-dropdown"
                                                        class="absolute right-0 z-10 hidden w-48 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
                                                        <div class="py-1">
                                                            <button type="button"
                                                                class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                                                onclick="sendReminder({{ $trailer->id }}, '{{ $docType->name }}', 'trailer')">
                                                                <svg class="w-4 h-4 mr-2" width="24" height="24"
                                                                    viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path
                                                                        d="M22 12.5001C22 12.0087 21.9947 11.0172 21.9842 10.5244C21.9189 7.45886 21.8862 5.92609 20.7551 4.79066C19.6239 3.65523 18.0497 3.61568 14.9012 3.53657C12.9607 3.48781 11.0393 3.48781 9.09882 3.53656C5.95033 3.61566 4.37608 3.65521 3.24495 4.79065C2.11382 5.92608 2.08114 7.45885 2.01576 10.5244C1.99474 11.5101 1.99475 12.4899 2.01577 13.4756C2.08114 16.5412 2.11383 18.0739 3.24496 19.2094C4.37608 20.3448 5.95033 20.3843 9.09883 20.4634C9.90159 20.4836 10.7011 20.4954 11.5 20.4989"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path
                                                                        d="M2 6L8.91302 9.92462C11.4387 11.3585 12.5613 11.3585 15.087 9.92462L22 6"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linejoin="round"></path>
                                                                    <path
                                                                        d="M22 17.5L14 17.5M22 17.5C22 16.7998 20.0057 15.4915 19.5 15M22 17.5C22 18.2002 20.0057 19.5085 19.5 20"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                </svg> Send Reminder Email
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                                @else
                                <div class="py-3 text-center text-gray-600 dark:text-gray-400">
                                    <i class="fas fa-check-circle text-emerald-500 mr-2"></i>
                                    All documents are compliant
                                </div>
                                @endif
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

            <!-- Right Column - Quick Actions & Compliance Summary -->
            <div class="lg:col-span-1">
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
                        {{-- {{ route('admin.documents.upload') ?? '#' }} --}}
                        <a href=""
                            class="flex items-center justify-between p-3 transition-colors duration-200 border border-gray-200 rounded-lg hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                            <div class="flex items-center">
                                <div
                                    class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                                    <i class="text-emerald-600 fas fa-file-upload dark:text-emerald-400"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white">Upload Documents</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Upload compliance documents</p>
                                </div>
                            </div>
                            <i class="text-gray-400 fas fa-chevron-right"></i>
                        </a>
                        {{-- {{ route('admin.reports.compliance') ?? '#' }} --}}
                        <a href=""
                            class="flex items-center justify-between p-3 transition-colors duration-200 border border-gray-200 rounded-lg hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                            <div class="flex items-center">
                                <div
                                    class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-purple-50 dark:bg-purple-900/20">
                                    <i class="text-purple-600 fas fa-chart-bar dark:text-purple-400"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white">Compliance Report</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">View detailed reports</p>
                                </div>
                            </div>
                            <i class="text-gray-400 fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Compliance Summary -->
                <div class="mt-6 p-5 bg-white border border-gray-200 rounded-2xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-white/90">Compliance Summary</h2>
                    <div class="space-y-4">
                        <!-- Overall Progress -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Overall Compliance</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $overallCompliance }}%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                <div class="h-full rounded-full bg-brand-600" style="width: {{ $overallCompliance }}%"></div>
                            </div>
                        </div>

                        <!-- By Category -->
                        <div class="space-y-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Vehicles</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $vehicleCompliance }}%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $vehicleCompliance }}%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Trailers</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $trailerCompliance }}%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-blue-500" style="width: {{ $trailerCompliance }}%"></div>
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
        <!-- Modal content remains the same -->
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
@endsection

@push('scripts')
    <script>
        // Toggle dropdown function
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id + '-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.relative')) {
                document.querySelectorAll('[id$="-dropdown"]').forEach(dropdown => {
                    dropdown.classList.add('hidden');
                });
            }
        });

        // Upload document function
        function uploadDocument(assetId, docTypeId, type) {
            // You can implement your upload logic here
            // For example, redirect to upload page or show a modal
            alert(`Upload ${type} document for asset ${assetId}, document type ${docTypeId}`);
            // Example: window.location.href = `/admin/${type}s/${assetId}/documents/upload?type=${docTypeId}`;
        }

        // Send reminder function
        function sendReminder(assetId, docName, type) {
            // Implement your reminder logic here
            alert(`Sending reminder for ${docName} of ${type} ${assetId}`);
            // Example: fetch(`/admin/${type}s/${assetId}/send-reminder`, { method: 'POST', ... });
        }

        // Show vehicle details
        function showVehicleDetails(vehicleId) {
            // Implement AJAX call to get vehicle details
            fetch(`/admin/vehicles/${vehicleId}/details`)
                .then(response => response.json())
                .then(data => {
                    // Populate and show modal
                    console.log('Vehicle details:', data);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Show trailer details
        function showTrailerDetails(trailerId) {
            // Implement AJAX call to get trailer details
            fetch(`/admin/trailers/${trailerId}/details`)
                .then(response => response.json())
                .then(data => {
                    // Populate and show modal
                    console.log('Trailer details:', data);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `p-4 rounded-lg shadow-lg ${type === 'success' ? 'bg-emerald-100 text-emerald-800' : type === 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'}`;
            toast.textContent = message;
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
@endpush