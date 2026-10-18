@extends('layouts.main-layout')

@section('title', 'Fleet Compliance')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-foreground dark:text-white/90">Fleet Compliance Dashboard</h1>
                    <p class="mt-2 text-muted-foreground">Monitor document compliance across <span
                            class="font-semibold text-primary">{{ $totalVehicles + $totalTrailers }}</span> vehicles &
                        trailers</p>
                </div>
                <div class="flex items-center space-x-3">
                    <!-- Driver Management Button -->
                    <a href="{{ route('admin.driver.index') }}"
                        class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white transition-colors duration-200 bg-primary border border-transparent rounded-lg shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2">
                        <x-ui.icon name="users" class="inline-block size-4 align-[-0.125em] mr-2" />Driver Management
                    </a>

                    <!-- Refresh Button -->
                    <button id="refresh-dashboard"
                        class="inline-flex items-center justify-center p-2.5 text-foreground transition-colors duration-200 bg-card border border-input rounded-lg shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2">
                        <x-ui.icon name="refresh-cw" class="inline-block size-4 align-[-0.125em]" />
                        <span class="sr-only">Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 mt-6 md:grid-cols-3 xl:grid-cols-5">
                <!-- Total Vehicles -->
                <div
                    class="p-4 bg-card border border-border rounded-xl shadow-theme-xs">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
                            <x-ui.icon name="truck" class="inline-block size-5 align-[-0.125em] text-primary" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-muted-foreground">Total Vehicles</p>
                            <p class="mt-1 text-2xl font-bold text-foreground">{{ $totalVehicles }}</p>
                        </div>
                    </div>
                </div>

                <!-- Total Trailers -->
                <div
                    class="p-4 bg-card border border-border rounded-xl shadow-theme-xs">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                            <x-ui.icon name="container" class="inline-block size-5 align-[-0.125em] text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-muted-foreground">Total Trailers</p>
                            <p class="mt-1 text-2xl font-bold text-foreground">{{ $totalTrailers }}</p>
                        </div>
                    </div>
                </div>

                <!-- Compliant -->
                <div
                    class="p-4 bg-card border border-border rounded-xl shadow-theme-xs">
                    <div class="flex items-center">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                            <x-ui.icon name="circle-check" class="inline-block size-5 align-[-0.125em] text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-muted-foreground">Compliant</p>
                            <p class="mt-1 text-2xl font-bold text-foreground">{{ $totalCompliant }}</p>
                        </div>
                    </div>
                </div>

                <!-- Warning/Expiring -->
                <div
                    class="p-4 bg-card border border-border rounded-xl shadow-theme-xs">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/20">
                            <x-ui.icon name="triangle-alert" class="inline-block size-5 align-[-0.125em] text-amber-600 dark:text-amber-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-muted-foreground">Warning</p>
                            <p class="mt-1 text-2xl font-bold text-foreground">{{ $totalWarning }}</p>
                        </div>
                    </div>
                </div>

                <!-- Critical (missing / expired) -->
                <div
                    class="p-4 bg-card border border-border rounded-xl shadow-theme-xs">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-red-50 dark:bg-red-900/20">
                            <x-ui.icon name="circle-x" class="inline-block size-5 align-[-0.125em] text-red-600 dark:text-red-400" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-muted-foreground">Critical</p>
                            <p class="mt-1 text-2xl font-bold text-foreground">{{ $totalCritical }}</p>
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
                    class="p-5 bg-card border border-border rounded-2xl shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-foreground dark:text-white/90">Vehicle Compliance Status</h2>
                            <p class="mt-1 text-sm text-muted-foreground">Grouped by vehicle - all document
                                issues</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span
                                class="px-2.5 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                {{ $vehiclesNeedingAttention }} Vehicles Need Attention
                            </span>
                        </div>
                    </div>

                    <!-- Vehicle Compliance List -->
                    <div class="space-y-4">
                        @forelse($vehicles as $vehicle)
                            <div
                                class="p-4 border border-border rounded-lg 
                                @if ($vehicle['compliance_status'] === 'warning') bg-amber-50/50 dark:bg-amber-900/5 
                                @elseif($vehicle['compliance_status'] === 'danger') bg-red-50/50 dark:bg-red-900/5 
                                @else bg-muted/25 @endif">

                                <!-- Vehicle Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg 
                                            @if ($vehicle['compliance_status'] === 'warning') bg-amber-100 dark:bg-amber-900/30 
                                            @elseif($vehicle['compliance_status'] === 'danger') bg-red-100 dark:bg-red-900/30 
                                            @else bg-emerald-100 dark:bg-emerald-900/30 @endif">
                                            <span class="inline-flex @if ($vehicle['compliance_status'] === 'warning') text-amber-600 dark:text-amber-400 @elseif($vehicle['compliance_status'] === 'danger') text-red-600 dark:text-red-400 @else text-emerald-600 dark:text-emerald-400 @endif">{{ svg('lucide-truck', 'size-3.5') }}</span>
                                        </div>
                                        <div>
                                            <div class="flex items-center">
                                                <h3 class="font-bold text-foreground">
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
                                            <div class="flex items-center mt-1 text-sm text-muted-foreground">
                                                <span>{{ $vehicle['compliance_percentage'] }}
                                                    ({{ $vehicle['compliant_docs'] }}/{{ $vehicle['total_docs'] }})
                                                </span>
                                                <span class="mx-2">•</span>
                                                <span class="flex items-center">
                                                    <x-ui.icon name="user" class="inline-block size-3 align-[-0.125em] mr-1" />
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
                                        class="px-3 py-1.5 text-sm font-medium text-primary transition-colors duration-200 bg-card border border-primary/50 rounded-lg hover:bg-primary/10 focus:outline-hidden focus:ring-2 focus:ring-primary/20">
                                        <x-ui.icon name="eye" class="inline-block size-4 align-[-0.125em] mr-1" />View Details
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
                                                <svg class="w-4 h-4 text-muted-foreground shrink-0"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                                                    <path
                                                        d="M14.4998 19H12.4998C9.67139 19 8.25718 19 7.3785 18.1213C6.49982 17.2426 6.49982 15.8284 6.49982 13V8C6.49982 5.17157 6.49982 3.75736 7.3785 2.87868C8.25718 2 9.67139 2 12.4998 2H13.843C14.6605 2 15.0692 2 15.4368 2.15224C15.8043 2.30448 16.0933 2.59351 16.6714 3.17157L19.3282 5.82843C19.9063 6.40648 20.1953 6.69552 20.3476 7.06306C20.4998 7.4306 20.4998 7.83935 20.4998 8.65685V13C20.4998 15.8284 20.4998 17.2426 19.6211 18.1213C18.7425 19 17.3282 19 14.4998 19Z"
                                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                                    </path>
                                                    <path d="M10 11H14M10 15H17" stroke="currentColor" stroke-width="1.5"
                                                        stroke-linecap="round"></path>
                                                </svg>
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-medium text-sm text-zinc-800">
                                                        {{ $docDetail['type_name'] }}
                                                    </div>
                                                    @if ($docDetail['expiry_date'])
                                                        <div class="text-xs text-muted-foreground">
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

                                            <div class="flex items-center gap-2 ml-3 shrink-0">
                                                @if ($docDetail['status'] !== 'valid')
                                                    <button type="button"
                                                        onclick="openUploadModal({{ $vehicle['id'] }}, {{ $docDetail['type_id'] }}, 'vehicle')"
                                                        class="h-6 text-xs rounded-md px-2 inline-flex items-center font-medium bg-primary hover:bg-primary/90 text-white border border-black/10 dark:border-0">
                                                        Complete
                                                    </button>
                                                @endif
                                                <button type="button"
                                                    onclick="sendReminderEmail({{ $vehicle['id'] }}, {{ $docDetail['type_id'] }}, 'vehicle')"
                                                    class="h-6 w-6 rounded-md inline-flex items-center justify-center bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800">
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
                            <div class="p-8 text-center border border-border rounded-lg">
                                <x-ui.icon name="truck" class="inline-block size-8 align-[-0.125em] mb-3 text-muted-foreground" />
                                <h3 class="mb-2 text-lg font-medium text-foreground">No Vehicles Found
                                </h3>
                                <p class="text-muted-foreground">Add vehicles to start tracking compliance</p>
                            </div>
                        @endforelse
                    </div>

                    @if ($vehicles->hasPages())
                        <div class="mt-6">
                            {{ $vehicles->links() }}
                        </div>
                    @endif
                </div>

                <!-- Trailers Section -->
                <div
                    class="mt-6 p-5 bg-card border border-border rounded-2xl shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-foreground dark:text-white/90">Trailer Compliance</h2>
                            <p class="mt-1 text-sm text-muted-foreground">Trailer document status</p>
                        </div>
                        <span
                            class="px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            {{ $totalTrailers }} Trailers · {{ $trailersNeedingAttention }} Need Attention
                        </span>
                    </div>

                    <!-- Trailers List -->
                    <div class="space-y-3">
                        @forelse($trailers as $trailer)
                            <div
                                class="p-4 border border-border rounded-lg 
                                @if ($trailer['compliance_status'] === 'warning') bg-amber-50/50 dark:bg-amber-900/5 
                                @elseif($trailer['compliance_status'] === 'danger') bg-red-50/50 dark:bg-red-900/5 
                                @else bg-muted/25 @endif">

                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg 
                                            @if ($trailer['compliance_status'] === 'warning') bg-amber-100 dark:bg-amber-900/30 
                                            @elseif($trailer['compliance_status'] === 'danger') bg-red-100 dark:bg-red-900/30 
                                            @else bg-emerald-100 dark:bg-emerald-900/30 @endif">
                                            <span class="inline-flex @if ($trailer['compliance_status'] === 'warning') text-amber-600 dark:text-amber-400 @elseif($trailer['compliance_status'] === 'danger') text-red-600 dark:text-red-400 @else text-emerald-600 dark:text-emerald-400 @endif">{{ svg('lucide-container', 'size-3.5') }}</span>
                                        </div>
                                        <div>
                                            <div class="flex items-center">
                                                <h3 class="font-bold text-foreground">
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
                                            <div class="flex items-center mt-1 text-sm text-muted-foreground">
                                                <span>{{ $trailer['compliance_percentage'] }}
                                                    ({{ $trailer['compliant_docs'] }}/{{ $trailer['total_docs'] }})
                                                </span>
                                                <span class="mx-2">•</span>
                                                <span class="flex items-center">
                                                    <x-ui.icon name="user" class="inline-block size-3 align-[-0.125em] mr-1" />
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
                                        class="px-3 py-1.5 text-sm font-medium text-primary transition-colors duration-200 bg-card border border-primary/50 rounded-lg hover:bg-primary/10 focus:outline-hidden focus:ring-2 focus:ring-primary/20">
                                        <x-ui.icon name="eye" class="inline-block size-4 align-[-0.125em] mr-1" />View Details
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
                                                <svg class="w-4 h-4 text-muted-foreground shrink-0"
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
                                                    <div class="font-medium text-sm text-zinc-800">
                                                        {{ $docDetail['type_name'] }}
                                                    </div>
                                                    @if ($docDetail['expiry_date'])
                                                        <div class="text-xs text-muted-foreground">
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

                                            <div class="flex items-center gap-2 ml-3 shrink-0">
                                                @if ($docDetail['status'] !== 'valid')
                                                    <button type="button"
                                                        onclick="openUploadModal({{ $trailer['id'] }}, {{ $docDetail['type_id'] }}, 'trailer')"
                                                        class="h-6 text-xs rounded-md px-2 inline-flex items-center font-medium bg-primary hover:bg-primary/90 text-white border border-black/10 dark:border-0">
                                                        Complete
                                                    </button>
                                                @endif
                                                <button type="button"
                                                    onclick="sendReminderEmail({{ $trailer['id'] }}, {{ $docDetail['type_id'] }}, 'trailer')"
                                                    class="h-6 w-6 rounded-md inline-flex items-center justify-center bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800">
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
                            <div class="p-8 text-center border border-border rounded-lg">
                                <x-ui.icon name="container" class="inline-block size-8 align-[-0.125em] mb-3 text-muted-foreground" />
                                <h3 class="mb-2 text-lg font-medium text-foreground">No Trailers Found
                                </h3>
                                <p class="text-muted-foreground">Add trailers to start tracking compliance</p>
                            </div>
                        @endforelse
                    </div>

                    @if ($trailers->hasPages())
                        <div class="mt-6">
                            {{ $trailers->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Column - Quick Actions & Summary -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div
                    class="p-5 bg-card border border-border rounded-2xl shadow-sm">
                    <h2 class="mb-4 text-xl font-bold text-foreground dark:text-white/90">Quick Actions</h2>
                    <div class="space-y-3">
                        <a href="{{ route('admin.vehicle.index') }}"
                            class="flex items-center justify-between p-3 transition-colors duration-200 border border-border rounded-lg hover:bg-muted/50">
                            <div class="flex items-center">
                                <div
                                    class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-primary/10">
                                    <x-ui.icon name="plus" class="inline-block size-4 align-[-0.125em] text-primary" />
                                </div>
                                <div>
                                    <h4 class="font-medium text-foreground">Add New Vehicle</h4>
                                    <p class="text-xs text-muted-foreground">Register a new vehicle</p>
                                </div>
                            </div>
                            <x-ui.icon name="chevron-right" class="inline-block size-4 align-[-0.125em] text-muted-foreground" />
                        </a>

                        <a href="{{ route('admin.trailer.index') }}"
                            class="flex items-center justify-between p-3 transition-colors duration-200 border border-border rounded-lg hover:bg-muted/50">
                            <div class="flex items-center">
                                <div
                                    class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                                    <x-ui.icon name="container" class="inline-block size-4 align-[-0.125em] text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h4 class="font-medium text-foreground">Add New Trailer</h4>
                                    <p class="text-xs text-muted-foreground">Register a new trailer</p>
                                </div>
                            </div>
                            <x-ui.icon name="chevron-right" class="inline-block size-4 align-[-0.125em] text-muted-foreground" />
                        </a>
                    </div>
                </div>

                <!-- Compliance Summary -->
                <div
                    class="p-5 bg-card border border-border rounded-2xl shadow-sm">
                    <h2 class="mb-4 text-xl font-bold text-foreground dark:text-white/90">Compliance Summary</h2>
                    <div class="space-y-4">
                        <!-- Overall Progress -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-foreground">Overall
                                    Compliance</span>
                                <span
                                    class="text-sm font-bold text-foreground">{{ $overallCompliance }}%</span>
                            </div>
                            <div class="w-full h-2 bg-muted rounded-full">
                                <div class="h-full rounded-full bg-primary" style="width: {{ $overallCompliance }}%">
                                </div>
                            </div>
                        </div>

                        <!-- By Category -->
                        <div class="space-y-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-muted-foreground">Vehicles</span>
                                    <span
                                        class="text-sm font-medium text-foreground">{{ $vehicleCompliance }}%</span>
                                </div>
                                <div class="w-full h-1.5 bg-muted rounded-full">
                                    <div class="h-full rounded-full bg-emerald-500"
                                        style="width:{{ $vehicleCompliance }}%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-muted-foreground">Trailers</span>
                                    <span
                                        class="text-sm font-medium text-foreground">{{ $trailerCompliance }}%</span>
                                </div>
                                <div class="w-full h-1.5 bg-muted rounded-full">
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
            <div class="fixed inset-0 transition-opacity bg-black/50"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div
                class="inline-block w-full overflow-hidden text-left align-bottom transition-all transform bg-card rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full mt-3 sm:mt-0 sm:ml-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold leading-6 text-foreground"
                                    id="vehicleModalTitle"></h3>
                                <button type="button" onclick="closeVehicleModal()"
                                    class="text-muted-foreground hover:text-muted-foreground">
                                    <x-ui.icon name="x" class="inline-block size-4 align-[-0.125em]" />
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
@endsection

@push('styles')
    <style>
        /* Add to your existing styles */
        #imagePreviewModal {
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Lucide icons for markup built in JS (the same SVGs the Blade icon component renders).
        const lucide = {{ \Illuminate\Support\Js::from(collect(['circle-alert', 'download', 'eye', 'file-text', 'x'])->mapWithKeys(fn ($n) => [$n => svg('lucide-'.$n, 'lucide-icon')->toHtml()])) }};
        const icon = (name, cls = 'size-4') => lucide[name].replace('class="lucide-icon"', `class="inline-block align-[-0.125em] ${cls}"`);

        // Refresh Dashboard
        document.getElementById('refresh-dashboard')?.addEventListener('click', function() {
            showToast('Refreshing dashboard...', 'info');
            setTimeout(() => {
                location.reload();
            }, 500);
        });

        // Vehicle Details Modal Functions
        // Vehicle Details Modal Functions
        function showVehicleDetails(vehicleId) {
            document.getElementById('vehicleDetailsContent').innerHTML = `
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary"></div>
                <p class="mt-2 text-muted-foreground">Loading vehicle details...</p>
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

                        // Build documents HTML
                        let documentsHtml = '';
                        if (vehicle.documents && vehicle.documents.length > 0) {
                            const assetType = 'vehicle';
                            documentsHtml = vehicle.documents.map(doc => {
                                const statusClass = doc.status === 'expired' ?
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' :
                                    doc.status === 'expiring' ?
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' :
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300';

                                const statusText = doc.status === 'expired' ? 'Expired' :
                                    doc.status === 'expiring' ? `Expires in ${doc.days_until_expiry} days` :
                                    'Valid';

                                const fileUrl = `/admin/compliance/documents/${doc.id}/${assetType}/view`;
                                const fileExtension = doc.file_path ? doc.file_path.split('.').pop()
                                    .toLowerCase() : '';
                                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);

                                return `
                            <div class="p-4 border border-border rounded-lg">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <h4 class="font-medium text-foreground">${escapeHtml(doc.type_name)}</h4>
                                            <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full ${statusClass}">
                                                ${statusText}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            <div>
                                                <p class="text-muted-foreground">Expiry Date:</p>
                                                <p class="font-medium text-foreground">${new Date(doc.expiry_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                                            </div>
                                            ${doc.description ? `
                                                        <div>
                                                            <p class="text-muted-foreground">Description:</p>
                                                            <p class="font-medium text-foreground">${escapeHtml(doc.description)}</p>
                                                        </div>
                                                        ` : ''}
                                        </div>
                                    </div>
                                    ${doc.file_path ? `
                                                <div class="ml-4 flex space-x-2">
                                                    ${isImage ? `
                                        <button type="button" data-action="preview-document" data-url="${escapeHtml(fileUrl)}" data-title="${escapeHtml(doc.type_name)}" 
                                            class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Preview">
                                            ${icon('eye', 'size-4')}
                                        </button>
                                        ` : ''}
                                                    <a href="/admin/compliance/documents/${doc.id}/vehicle/download" 
                                                        class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Download">
                                                        ${icon('download', 'size-4')}
                                                    </a>
                                                    <button type="button" onclick="viewDocument(${Number(doc.id)}, 'vehicle')" 
                                                        class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-colors" title="View">
                                                        ${icon('file-text', 'size-4')}
                                                    </button>
                                                </div>
                                                ` : ''}
                                </div>
                            </div>
                        `;
                            }).join('');
                        } else {
                            documentsHtml =
                                '<p class="text-muted-foreground text-center py-4">No documents uploaded yet</p>';
                        }

                        const missingDocsHtml = vehicle.missing_documents && vehicle.missing_documents.length > 0 ? `
                    <div class="mt-4">
                        <h4 class="mb-2 font-medium text-foreground">Missing/Expired Documents:</h4>
                        <div class="space-y-2">
                            ${vehicle.missing_documents.map(doc => `
                                            <div class="flex items-center p-2 border border-border rounded-lg">
                                                ${icon('circle-alert', 'size-4 mr-3 text-amber-500')}
                                                <span class="text-sm text-foreground">${escapeHtml(doc)}</span>
                                            </div>
                                        `).join('')}
                        </div>
                    </div>
                ` : '';

                        document.getElementById('vehicleDetailsContent').innerHTML = `
                    <div class="space-y-4">
                        <!-- Vehicle Information -->
                        <div class="grid grid-cols-2 gap-4 p-4 bg-muted/50 rounded-lg">
                            <div>
                                <p class="text-sm text-muted-foreground">Make/Model</p>
                                <p class="font-medium text-foreground">${escapeHtml(vehicle.make)} ${escapeHtml(vehicle.model)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Year</p>
                                <p class="font-medium text-foreground">${escapeHtml(vehicle.year)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">VIN</p>
                                <p class="font-medium text-foreground">${escapeHtml(vehicle.vin)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Odometer</p>
                                <p class="font-medium text-foreground">${vehicle.odometer ? vehicle.odometer.toLocaleString() + ' miles' : 'N/A'}</p>
                            </div>
                        </div>
                        
                        <!-- Compliance Status -->
                        <div>
                            <h4 class="mb-2 font-medium text-foreground">Compliance Status</h4>
                            <div class="flex items-center justify-between p-3 rounded-lg ${vehicle.compliance_status === 'warning' ? 'bg-amber-50 dark:bg-amber-900/10' : vehicle.compliance_status === 'danger' ? 'bg-red-50 dark:bg-red-900/10' : 'bg-emerald-50 dark:bg-emerald-900/10'}">
                                <div>
                                    <p class="text-sm font-medium text-foreground">${escapeHtml(vehicle.compliance_percentage)} Compliance</p>
                                    <p class="text-sm text-muted-foreground">${vehicle.compliant_docs} of ${vehicle.total_docs} documents</p>
                                </div>
                                <span class="px-3 py-1 text-sm font-medium rounded-full ${vehicle.compliance_status === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : vehicle.compliance_status === 'danger' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'}">
                                    ${vehicle.compliance_status === 'warning' ? 'Warning' : vehicle.compliance_status === 'danger' ? 'Critical' : 'Compliant'}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Documents Section -->
                        <div>
                            <h4 class="mb-3 font-medium text-foreground">Documents</h4>
                            <div class="space-y-3">
                                ${documentsHtml}
                            </div>
                        </div>
                        
                        ${missingDocsHtml}
                        
                        <div class="pt-4 border-t border-border">
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeVehicleModal()" class="px-4 py-2 text-sm font-medium text-foreground transition-colors duration-200 bg-card border border-input rounded-lg hover:bg-muted/50">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                    } else {
                        document.getElementById('vehicleDetailsContent').innerHTML = `
                    <div class="py-12 text-center">
                        ${icon('circle-alert', 'size-8 mb-3 text-red-500')}
                        <p class="text-foreground">Failed to load vehicle details</p>
                    </div>
                `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('vehicleDetailsContent').innerHTML = `
                <div class="py-12 text-center">
                    ${icon('circle-alert', 'size-8 mb-3 text-red-500')}
                    <p class="text-foreground">Error loading vehicle details</p>
                </div>
            `;
                });

            document.getElementById('vehicleDetailsModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        // Function to show trailer details
        function showTrailerDetails(trailerId) {
            document.getElementById('vehicleDetailsContent').innerHTML = `
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary"></div>
                <p class="mt-2 text-muted-foreground">Loading trailer details...</p>
            </div>
        </div>
    `;

            fetch(`/admin/compliance/trailers/${trailerId}/details`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const trailer = data.trailer;
                        document.getElementById('vehicleModalTitle').textContent =
                            `Trailer #${trailer.unit_no} Details`;

                        // Build documents HTML (similar to vehicle)
                        let documentsHtml = '';
                        if (trailer.documents && trailer.documents.length > 0) {
                            const assetType = 'trailer';
                            documentsHtml = trailer.documents.map(doc => {
                                const statusClass = doc.status === 'expired' ?
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' :
                                    doc.status === 'expiring' ?
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' :
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300';

                                const statusText = doc.status === 'expired' ? 'Expired' :
                                    doc.status === 'expiring' ? `Expires in ${doc.days_until_expiry} days` :
                                    'Valid';

                                const fileUrl = `/admin/compliance/documents/${doc.id}/${assetType}/view`;
                                const fileExtension = doc.file_path ? doc.file_path.split('.').pop()
                                    .toLowerCase() : '';
                                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);

                                return `
                            <div class="p-4 border border-border rounded-lg">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <h4 class="font-medium text-foreground">${escapeHtml(doc.type_name)}</h4>
                                            <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full ${statusClass}">
                                                ${statusText}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            <div>
                                                <p class="text-muted-foreground">Expiry Date:</p>
                                                <p class="font-medium text-foreground">${new Date(doc.expiry_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                                            </div>
                                            ${doc.description ? `
                                                        <div>
                                                            <p class="text-muted-foreground">Description:</p>
                                                            <p class="font-medium text-foreground">${escapeHtml(doc.description)}</p>
                                                        </div>
                                                        ` : ''}
                                        </div>
                                    </div>
                                    ${doc.file_path ? `
                                                <div class="ml-4 flex space-x-2">
                                                    ${isImage ? `
                                        <button type="button" data-action="preview-document" data-url="${escapeHtml(fileUrl)}" data-title="${escapeHtml(doc.type_name)}" 
                                            class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Preview">
                                            ${icon('eye', 'size-4')}
                                        </button>
                                        ` : ''}
                                                    <a href="/admin/compliance/documents/${doc.id}/trailer/download" 
                                                        class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Download">
                                                        ${icon('download', 'size-4')}
                                                    </a>
                                                    <button type="button" onclick="viewDocument(${Number(doc.id)}, 'trailer')" 
                                                        class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-colors" title="View">
                                                        ${icon('file-text', 'size-4')}
                                                    </button>
                                                </div>
                                                ` : ''}
                                </div>
                            </div>
                        `;
                            }).join('');
                        } else {
                            documentsHtml =
                                '<p class="text-muted-foreground text-center py-4">No documents uploaded yet</p>';
                        }

                        const missingDocsHtml = trailer.missing_documents && trailer.missing_documents.length > 0 ? `
                    <div class="mt-4">
                        <h4 class="mb-2 font-medium text-foreground">Missing/Expired Documents:</h4>
                        <div class="space-y-2">
                            ${trailer.missing_documents.map(doc => `
                                            <div class="flex items-center p-2 border border-border rounded-lg">
                                                ${icon('circle-alert', 'size-4 mr-3 text-amber-500')}
                                                <span class="text-sm text-foreground">${escapeHtml(doc)}</span>
                                            </div>
                                        `).join('')}
                        </div>
                    </div>
                ` : '';

                        document.getElementById('vehicleDetailsContent').innerHTML = `
                    <div class="space-y-4">
                        <!-- Trailer Information -->
                        <div class="grid grid-cols-2 gap-4 p-4 bg-muted/50 rounded-lg">
                            <div>
                                <p class="text-sm text-muted-foreground">Make/Model</p>
                                <p class="font-medium text-foreground">${escapeHtml(trailer.make)} ${escapeHtml(trailer.model)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Year</p>
                                <p class="font-medium text-foreground">${escapeHtml(trailer.year)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">VIN</p>
                                <p class="font-medium text-foreground">${escapeHtml(trailer.vin)}</p>
                            </div>
                        </div>
                        
                        <!-- Compliance Status -->
                        <div>
                            <h4 class="mb-2 font-medium text-foreground">Compliance Status</h4>
                            <div class="flex items-center justify-between p-3 rounded-lg ${trailer.compliance_status === 'warning' ? 'bg-amber-50 dark:bg-amber-900/10' : trailer.compliance_status === 'danger' ? 'bg-red-50 dark:bg-red-900/10' : 'bg-emerald-50 dark:bg-emerald-900/10'}">
                                <div>
                                    <p class="text-sm font-medium text-foreground">${escapeHtml(trailer.compliance_percentage)} Compliance</p>
                                    <p class="text-sm text-muted-foreground">${trailer.compliant_docs} of ${trailer.total_docs} documents</p>
                                </div>
                                <span class="px-3 py-1 text-sm font-medium rounded-full ${trailer.compliance_status === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : trailer.compliance_status === 'danger' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'}">
                                    ${trailer.compliance_status === 'warning' ? 'Warning' : trailer.compliance_status === 'danger' ? 'Critical' : 'Compliant'}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Documents Section -->
                        <div>
                            <h4 class="mb-3 font-medium text-foreground">Documents</h4>
                            <div class="space-y-3">
                                ${documentsHtml}
                            </div>
                        </div>
                        
                        ${missingDocsHtml}
                        
                        <div class="pt-4 border-t border-border">
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeVehicleModal()" class="px-4 py-2 text-sm font-medium text-foreground transition-colors duration-200 bg-card border border-input rounded-lg hover:bg-muted/50">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                    } else {
                        document.getElementById('vehicleDetailsContent').innerHTML = `
                    <div class="py-12 text-center">
                        ${icon('circle-alert', 'size-8 mb-3 text-red-500')}
                        <p class="text-foreground">Failed to load trailer details</p>
                    </div>
                `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('vehicleDetailsContent').innerHTML = `
                <div class="py-12 text-center">
                    ${icon('circle-alert', 'size-8 mb-3 text-red-500')}
                    <p class="text-foreground">Error loading trailer details</p>
                </div>
            `;
                });

            document.getElementById('vehicleDetailsModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        // Vehicle/trailer data comes from tenant users: never put it in HTML unescaped.
        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        document.addEventListener('click', function(e) {
            const button = e.target.closest('[data-action="preview-document"]');
            if (button) {
                previewImage(button.dataset.url, button.dataset.title);
            }
        });

        // Add these new functions for document handling
        function previewImage(fileUrl, title) {
            // Create a modal for image preview
            const modalHtml = `
        <div id="imagePreviewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75" onclick="closeImagePreview()"></div>
            <div class="relative z-50 max-w-4xl max-h-full">
                <div class="bg-card rounded-lg shadow-xl">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="text-lg font-bold text-foreground">${escapeHtml(title)}</h3>
                        <button type="button" onclick="closeImagePreview()" class="text-muted-foreground hover:text-muted-foreground">
                            ${icon('x', 'size-4')}
                        </button>
                    </div>
                    <div class="p-4">
                        <img src="${escapeHtml(fileUrl)}" alt="${escapeHtml(title)}" class="max-w-full max-h-[70vh] object-contain">
                    </div>
                    <div class="flex justify-end p-4 border-t">
                        <a href="${escapeHtml(fileUrl)}" download class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90">
                            ${icon('download', 'size-4 mr-2')}Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;

            // Remove existing preview modal if any
            const existingModal = document.getElementById('imagePreviewModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add new modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            document.body.classList.add('overflow-hidden');
        }

        function closeImagePreview() {
            const modal = document.getElementById('imagePreviewModal');
            if (modal) {
                modal.remove();
                document.body.classList.remove('overflow-hidden');
            }
        }

        function viewDocument(documentId, assetType) {
            window.open(`/admin/compliance/documents/${documentId}/${assetType}/view`, '_blank');
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

        // Send reminder email function
        @include('admin.compliance.partials.send-reminder-script')

        function showToast(message, type = 'info') {
            window.notify(type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'), message);
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
