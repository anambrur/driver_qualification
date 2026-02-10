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
                    {{-- {{ route('admin.reports.index') }} --}}
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
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">222222</p>
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
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">3333</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
                                3333 Vehicles Need Attention
                            </span>
                        </div>
                    </div>


                    <!-- Vehicle Compliance List -->
                    <div class="space-y-4">
                        @forelse($vehicles as $vehicle)
                            <div
                                class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 @if ($vehicle['compliance_status'] === 'warning') bg-amber-50/50 dark:bg-amber-900/5 @elseif($vehicle['compliance_status'] === 'danger') bg-red-50/50 dark:bg-red-900/5 @else bg-gray-50/50 dark:bg-gray-900/5 @endif">
                                <!-- Vehicle Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg @if ($vehicle['compliance_status'] === 'warning') bg-amber-100 dark:bg-amber-900/30 @elseif($vehicle['compliance_status'] === 'danger') bg-red-100 dark:bg-red-900/30 @else bg-gray-100 dark:bg-gray-700 @endif">
                                            <i
                                                class="text-sm @if ($vehicle['compliance_status'] === 'warning') text-amber-600 dark:text-amber-400 @elseif($vehicle['compliance_status'] === 'danger') text-red-600 dark:text-red-400 @else text-gray-600 dark:text-gray-400 @endif fas fa-truck"></i>
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
                                                    {{ $vehicle->assetGroups->driver->first_name ?? 'No assigned driver' }}
                                                    {{ $vehicle->assetGroups->driver->last_name ?? '' }}
                                                </span>
                                                @if ($vehicle['odometer'] > 0)
                                                    <span class="mx-2">•</span>
                                                    <span>{{ number_format($vehicle->odometer) }} miles</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="showVehicleDetails({{ $vehicle['id'] }})"
                                        class="px-3 py-1.5 text-sm font-medium text-brand-600 transition-colors duration-200 bg-white border border-brand-300 rounded-lg hover:bg-brand-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:bg-gray-800 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                </div>

                                <!-- Missing Documents List -->
                                @foreach ($vehicleDocumentTypes as $vehicleDocumentType)
                                    <div
                                        class="hidden md:flex items-center justify-between py-2 px-3 mb-2 rounded-lg border dark:bg-zinc-900 border-zinc-100 hover:border-zinc-200 transition-colors border-l-4 border-l-gray-400">
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
                                                    <div class="font-medium [:where(&amp;)]:text-zinc-800 [:where(&amp;)]:dark:text-white text-sm [&amp;:has(+[data-flux-subheading])]:mb-2 [[data-flux-subheading]+&amp;]:mt-2"
                                                        data-flux-heading="">{{ $vehicleDocumentType->name }}</div>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <div data-flux-badge="data-flux-badge"
                                                    class="inline-flex items-center font-medium whitespace-nowrap  [print-color-adjust:exact] text-xs py-1 **:data-flux-badge-icon:size-3 **:data-flux-badge-icon:me-1 rounded-md px-2 text-zinc-700 [&amp;_button]:text-zinc-700! dark:text-zinc-200 dark:[&amp;_button]:text-zinc-200! bg-zinc-400/15 dark:bg-zinc-400/40 [&amp;:is(button)]:hover:bg-zinc-400/25 dark:[button]:hover:bg-zinc-400/50">
                                                    Missing
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                                            <button type="button"
                                                class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-6 text-xs rounded-md px-2 inline-flex  bg-[var(--color-accent)] hover:bg-[color-mix(in_oklab,_var(--color-accent),_transparent_10%)] text-[var(--color-accent-foreground)] border border-black/10 dark:border-0 shadow-[inset_0px_1px_--theme(--color-white/.2)] [[data-flux-button-group]_&amp;]:border-e-0 [:is([data-flux-button-group]&gt;&amp;:last-child,_[data-flux-button-group]_:last-child&gt;&amp;)]:border-e-[1px] dark:[:is([data-flux-button-group]&gt;&amp;:last-child,_[data-flux-button-group]_:last-child&gt;&amp;)]:border-e-0 dark:[:is([data-flux-button-group]&gt;&amp;:last-child,_[data-flux-button-group]_:last-child&gt;&amp;)]:border-s-[1px] [:is([data-flux-button-group]&gt;&amp;:not(:first-child),_[data-flux-button-group]_:not(:first-child)&gt;&amp;)]:border-s-[color-mix(in_srgb,var(--color-accent-foreground),transparent_85%)]"
                                                data-flux-button="data-flux-button"
                                                data-flux-group-target="data-flux-group-target"
                                                onclick="Livewire.dispatch('slide-over.open', {component: 'vehicle::vehicle.checklist.modal.checklist-upload', arguments: {vehicleId: 'a0c5577f-804c-4a7f-bb52-b89e3a2e31c0', category: 'INSURANCE_PROOF_COVERAGE'}})">
                                                Complete
                                            </button>
                                            <ui-dropdown position="bottom start" data-flux-dropdown="">
                                                <button type="button"
                                                    class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-6 text-xs rounded-md w-6 inline-flex  bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800 dark:text-white"
                                                    data-flux-button="data-flux-button" aria-haspopup="true"
                                                    aria-controls="lofi-dropdown-896633dd795ad" aria-expanded="false">
                                                    <svg class="shrink-0 [:where(&amp;)]:size-4" data-flux-icon=""
                                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"
                                                        fill="currentColor" aria-hidden="true" data-slot="icon">
                                                        <path
                                                            d="M8 2a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM8 6.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM9.5 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z">
                                                        </path>
                                                    </svg>
                                                </button>
                                                <ui-menu
                                                    class="[:where(&amp;)]:min-w-48 p-[.3125rem] rounded-lg shadow-xs border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 focus:outline-hidden"
                                                    popover="manual" data-flux-menu="" id="lofi-dropdown-896633dd795ad"
                                                    role="menu" tabindex="-1">
                                                    <button type="button"
                                                        class="flex items-center px-2 py-1.5 w-full focus:outline-hidden rounded-md text-start text-sm font-medium [&amp;[disabled]]:opacity-50 text-zinc-800 data-active:bg-zinc-50 dark:text-white dark:data-active:bg-zinc-600 **:data-flux-menu-item-icon:text-zinc-400 dark:**:data-flux-menu-item-icon:text-white/60 [&amp;[data-active]_[data-flux-menu-item-icon]]:text-current"
                                                        data-flux-menu-item="data-flux-menu-item"
                                                        onclick="Livewire.dispatch('modal.open', { component: 'vehicle::vehicle.inspections.send-email', arguments: { vehicleId: 'a0c5577f-804c-4a7f-bb52-b89e3a2e31c0', documentId: '', field: 'INSURANCE_PROOF_COVERAGE', date: ''} })"
                                                        id="lofi-menu-item-6f2872e56e2608" role="menuitem"
                                                        tabindex="-1">
                                                        <div
                                                            class="w-7 hidden [[data-flux-menu]:has(&gt;[data-flux-menu-item-has-icon])_&amp;]:block">
                                                        </div>

                                                        <svg class="w-4 h-4 mr-2" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none"
                                                            xmlns="http://www.w3.org/2000/svg">
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
                                                </ui-menu>
                                            </ui-dropdown>
                                        </div>
                                    </div>
                                @endforeach


                                @if (!empty($vehicle['missing_documents']))
                                    <div class="pl-11">
                                        <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                            <h4 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Missing/Expiring Documents:</h4>
                                            <ul class="space-y-1">
                                                @foreach ($vehicle['missing_documents'] as $document)
                                                    <li class="flex items-start text-sm text-gray-600 dark:text-gray-400">
                                                        <i
                                                            class="mt-0.5 mr-2 text-amber-500 fas fa-exclamation-circle"></i>
                                                        <span>{{ $document }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
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
            </div>

            <!-- Right Column - Trailers & Quick Actions -->
            <div class="lg:col-span-2">
                <!-- Trailers Compliance -->
                <div
                    class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Trailer Compliance</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Trailer document status</p>
                        </div>
                        <span
                            class="px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            3333 Trailers
                        </span>
                    </div>

                    <!-- Trailers List -->
                    <div class="space-y-3">
                        @forelse($trailers as $trailer)
                            <div
                                class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 @if ($trailer['compliance_status'] === 'warning') bg-amber-50/50 dark:bg-amber-900/5 @elseif($trailer['compliance_status'] === 'danger') bg-red-50/50 dark:bg-red-900/5 @else bg-gray-50/50 dark:bg-gray-900/5 @endif">
                                <!-- Vehicle Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 mr-3 rounded-lg @if ($trailer['compliance_status'] === 'warning') bg-amber-100 dark:bg-amber-900/30 @elseif($trailer['compliance_status'] === 'danger') bg-red-100 dark:bg-red-900/30 @else bg-gray-100 dark:bg-gray-700 @endif">
                                            <i
                                                class="text-sm @if ($trailer['compliance_status'] === 'warning') text-amber-600 dark:text-amber-400 @elseif($trailer['compliance_status'] === 'danger') text-red-600 dark:text-red-400 @else text-gray-600 dark:text-gray-400 @endif fas fa-truck"></i>
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
                                                    {{ $trailer->assetGroups->driver->first_name ?? 'No assigned driver' }}
                                                    {{ $trailer->assetGroups->driver->last_name ?? '' }}
                                                </span>
                                                @if ($trailer['odometer'] > 0)
                                                    <span class="mx-2">•</span>
                                                    <span>{{ number_format($trailer->odometer) }} miles</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="showtrailerDetails({{ $trailer['id'] }})"
                                        class="px-3 py-1.5 text-sm font-medium text-brand-600 transition-colors duration-200 bg-white border border-brand-300 rounded-lg hover:bg-brand-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:bg-gray-800 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-900/20">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                </div>

                                <!-- Missing Documents List -->
                                @foreach ($trailersDocumentTypes as $trailerDocumentType)
                                    <div
                                        class="hidden md:flex items-center justify-between py-2 px-3 mb-2 rounded-lg border dark:bg-zinc-900 border-zinc-100 hover:border-zinc-200 transition-colors border-l-4 border-l-gray-400">
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
                                                    <div class="font-medium [:where(&amp;)]:text-zinc-800 [:where(&amp;)]:dark:text-white text-sm [&amp;:has(+[data-flux-subheading])]:mb-2 [[data-flux-subheading]+&amp;]:mt-2"
                                                        data-flux-heading="">{{ $trailerDocumentType->name }}</div>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <div data-flux-badge="data-flux-badge"
                                                    class="inline-flex items-center font-medium whitespace-nowrap  [print-color-adjust:exact] text-xs py-1 **:data-flux-badge-icon:size-3 **:data-flux-badge-icon:me-1 rounded-md px-2 text-zinc-700 [&amp;_button]:text-zinc-700! dark:text-zinc-200 dark:[&amp;_button]:text-zinc-200! bg-zinc-400/15 dark:bg-zinc-400/40 [&amp;:is(button)]:hover:bg-zinc-400/25 dark:[button]:hover:bg-zinc-400/50">
                                                    Missing
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                                            <button type="button"
                                                class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-6 text-xs rounded-md px-2 inline-flex  bg-[var(--color-accent)] hover:bg-[color-mix(in_oklab,_var(--color-accent),_transparent_10%)] text-[var(--color-accent-foreground)] border border-black/10 dark:border-0 shadow-[inset_0px_1px_--theme(--color-white/.2)] [[data-flux-button-group]_&amp;]:border-e-0 [:is([data-flux-button-group]&gt;&amp;:last-child,_[data-flux-button-group]_:last-child&gt;&amp;)]:border-e-[1px] dark:[:is([data-flux-button-group]&gt;&amp;:last-child,_[data-flux-button-group]_:last-child&gt;&amp;)]:border-e-0 dark:[:is([data-flux-button-group]&gt;&amp;:last-child,_[data-flux-button-group]_:last-child&gt;&amp;)]:border-s-[1px] [:is([data-flux-button-group]&gt;&amp;:not(:first-child),_[data-flux-button-group]_:not(:first-child)&gt;&amp;)]:border-s-[color-mix(in_srgb,var(--color-accent-foreground),transparent_85%)]"
                                                data-flux-button="data-flux-button"
                                                data-flux-group-target="data-flux-group-target"
                                                onclick="Livewire.dispatch('slide-over.open', {component: 'vehicle::vehicle.checklist.modal.checklist-upload', arguments: {vehicleId: 'a0c5577f-804c-4a7f-bb52-b89e3a2e31c0', category: 'INSURANCE_PROOF_COVERAGE'}})">
                                                Complete
                                            </button>
                                            <ui-dropdown position="bottom start" data-flux-dropdown="">
                                                <button type="button"
                                                    class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-6 text-xs rounded-md w-6 inline-flex  bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-800 dark:text-white"
                                                    data-flux-button="data-flux-button" aria-haspopup="true"
                                                    aria-controls="lofi-dropdown-896633dd795ad" aria-expanded="false">
                                                    <svg class="shrink-0 [:where(&amp;)]:size-4" data-flux-icon=""
                                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"
                                                        fill="currentColor" aria-hidden="true" data-slot="icon">
                                                        <path
                                                            d="M8 2a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM8 6.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM9.5 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z">
                                                        </path>
                                                    </svg>
                                                </button>
                                                <ui-menu
                                                    class="[:where(&amp;)]:min-w-48 p-[.3125rem] rounded-lg shadow-xs border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 focus:outline-hidden"
                                                    popover="manual" data-flux-menu="" id="lofi-dropdown-896633dd795ad"
                                                    role="menu" tabindex="-1">
                                                    <button type="button"
                                                        class="flex items-center px-2 py-1.5 w-full focus:outline-hidden rounded-md text-start text-sm font-medium [&amp;[disabled]]:opacity-50 text-zinc-800 data-active:bg-zinc-50 dark:text-white dark:data-active:bg-zinc-600 **:data-flux-menu-item-icon:text-zinc-400 dark:**:data-flux-menu-item-icon:text-white/60 [&amp;[data-active]_[data-flux-menu-item-icon]]:text-current"
                                                        data-flux-menu-item="data-flux-menu-item"
                                                        onclick="Livewire.dispatch('modal.open', { component: 'vehicle::vehicle.inspections.send-email', arguments: { vehicleId: 'a0c5577f-804c-4a7f-bb52-b89e3a2e31c0', documentId: '', field: 'INSURANCE_PROOF_COVERAGE', date: ''} })"
                                                        id="lofi-menu-item-6f2872e56e2608" role="menuitem"
                                                        tabindex="-1">
                                                        <div
                                                            class="w-7 hidden [[data-flux-menu]:has(&gt;[data-flux-menu-item-has-icon])_&amp;]:block">
                                                        </div>

                                                        <svg class="w-4 h-4 mr-2" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none"
                                                            xmlns="http://www.w3.org/2000/svg">
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
                                                </ui-menu>
                                            </ui-dropdown>
                                        </div>
                                    </div>
                                @endforeach


                                @if (!empty($vehicle['missing_documents']))
                                    <div class="pl-11">
                                        <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                            <h4 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Missing/Expiring Documents:</h4>
                                            <ul class="space-y-1">
                                                @foreach ($vehicle['missing_documents'] as $document)
                                                    <li class="flex items-start text-sm text-gray-600 dark:text-gray-400">
                                                        <i
                                                            class="mt-0.5 mr-2 text-amber-500 fas fa-exclamation-circle"></i>
                                                        <span>{{ $document }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-8 text-center border border-gray-200 rounded-lg dark:border-gray-700">
                                <i class="mb-3 text-3xl text-gray-400 fas fa-truck"></i>
                                <h3 class="mb-2 text-lg font-medium text-gray-700 dark:text-gray-300">No Trailers Found
                                </h3>
                                <p class="text-gray-500 dark:text-gray-400">Add trailers to start tracking compliance</p>
                            </div>
                        @endforelse
                    </div>
                </div>

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
                        {{-- {{ route('admin.documents.upload') }} --}}
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
                        {{-- {{ route('admin.reports.compliance') }} --}}
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
                                    class="text-sm font-bold text-gray-900 dark:text-white">4444%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                <div class="h-full rounded-full bg-brand-600" style="width: 44444%">
                                </div>
                            </div>
                        </div>

                        <!-- By Category -->
                        <div class="space-y-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Vehicles</span>
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white">44444%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-emerald-500"
                                        style="width:4444%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Trailers</span>
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white">44444%</span>
                                </div>
                                <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-full rounded-full bg-blue-500"
                                        style="width: 44444%"></div>
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

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
@endsection

@push('styles')
    <style>
        .compliance-status {
            position: relative;
        }

        .compliance-status::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-warning::before {
            background-color: #f59e0b;
        }

        .status-danger::before {
            background-color: #ef4444;
        }

        .status-success::before {
            background-color: #10b981;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Refresh Dashboard
        document.getElementById('refresh-dashboard').addEventListener('click', function() {
            showToast('Refreshing dashboard...', 'info');
            setTimeout(() => {
                location.reload();
            }, 500);
        });

        // Vehicle Details Modal Functions
        function showVehicleDetails(vehicleId) {
            // Show loading state
            document.getElementById('vehicleDetailsContent').innerHTML = `
            <div class="flex items-center justify-center py-12">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-brand-600"></div>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Loading vehicle details...</p>
                </div>
            </div>
        `;

            // Fetch vehicle details via AJAX
            fetch(`/admin/vehicles/${vehicleId}/details`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('vehicleModalTitle').textContent =
                            `Vehicle #${data.vehicle.unit_no} Details`;
                        document.getElementById('vehicleDetailsContent').innerHTML = `
                        <div class="space-y-4">
                            <!-- Basic Info -->
                            <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg dark:bg-gray-700">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Make/Model</p>
                                    <p class="font-medium text-gray-900 dark:text-white">${data.vehicle.make} ${data.vehicle.model}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Year</p>
                                    <p class="font-medium text-gray-900 dark:text-white">${data.vehicle.year}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">VIN</p>
                                    <p class="font-medium text-gray-900 dark:text-white">${data.vehicle.vin}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Odometer</p>
                                    <p class="font-medium text-gray-900 dark:text-white">${data.vehicle.odometer.toLocaleString()} miles</p>
                                </div>
                            </div>
                            
                            <!-- Compliance Status -->
                            <div>
                                <h4 class="mb-2 font-medium text-gray-900 dark:text-white">Compliance Status</h4>
                                <div class="flex items-center justify-between p-3 rounded-lg ${data.vehicle.compliance_status === 'warning' ? 'bg-amber-50 dark:bg-amber-900/10' : 'bg-emerald-50 dark:bg-emerald-900/10'}">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">${data.vehicle.compliance_percentage} Compliance</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">${data.vehicle.compliant_docs} of ${data.vehicle.total_docs} documents</p>
                                    </div>
                                    <span class="px-3 py-1 text-sm font-medium rounded-full ${data.vehicle.compliance_status === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'}">
                                        ${data.vehicle.compliance_status === 'warning' ? 'Warning' : 'Compliant'}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Missing Documents -->
                            ${data.vehicle.missing_documents && data.vehicle.missing_documents.length > 0 ? `
                                                                    <div>
                                                                        <h4 class="mb-2 font-medium text-gray-900 dark:text-white">Missing Documents</h4>
                                                                        <div class="space-y-2">
                                                                            ${data.vehicle.missing_documents.map(doc => `
                                        <div class="flex items-center p-2 border border-gray-200 rounded-lg dark:border-gray-700">
                                            <i class="mr-3 text-amber-500 fas fa-exclamation-circle"></i>
                                            <span class="text-sm text-gray-700 dark:text-gray-300">${doc}</span>
                                        </div>
                                    `).join('')}
                                                                        </div>
                                                                    </div>
                                                                    ` : ''}
                            
                            <!-- Actions -->
                            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex justify-end space-x-3">
                                    <button type="button" onclick="closeVehicleModal()" class="px-4 py-2 text-sm font-medium text-gray-700 transition-colors duration-200 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-gray-500/20 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600">
                                        Close
                                    </button>
                                    <a href="/admin/vehicles/${vehicleId}/documents" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white transition-colors duration-200 bg-brand-600 border border-transparent rounded-lg hover:bg-brand-700 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                        <i class="mr-2 fas fa-file-upload"></i>Upload Documents
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    } else {
                        document.getElementById('vehicleDetailsContent').innerHTML = `
                        <div class="py-12 text-center">
                            <i class="mb-3 text-3xl text-red-500 fas fa-exclamation-circle"></i>
                            <p class="text-gray-700 dark:text-gray-300">Failed to load vehicle details</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">${data.message || 'Please try again'}</p>
                        </div>
                    `;
                    }
                })
                .catch(error => {
                    document.getElementById('vehicleDetailsContent').innerHTML = `
                    <div class="py-12 text-center">
                        <i class="mb-3 text-3xl text-red-500 fas fa-exclamation-circle"></i>
                        <p class="text-gray-700 dark:text-gray-300">Error loading vehicle details</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Network error occurred</p>
                    </div>
                `;
                });

            // Show modal
            document.getElementById('vehicleDetailsModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeVehicleModal() {
            document.getElementById('vehicleDetailsModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Toast Notification Function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
            const toastId = 'toast-' + Date.now();

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className =
                `flex items-center p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full ${type === 'success' ? 'bg-emerald-50 border border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800' : type === 'error' ? 'bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800' : 'bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800'}`;

            const icon = type === 'success' ? 'fas fa-check-circle text-emerald-500' :
                type === 'error' ? 'fas fa-exclamation-circle text-red-500' :
                'fas fa-info-circle text-blue-500';

            toast.innerHTML = `
            <i class="${icon} mr-3"></i>
            <span class="text-sm font-medium text-gray-900 dark:text-white">${message}</span>
            <button onclick="document.getElementById('${toastId}').remove()" class="ml-4 text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        `;

            toastContainer.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
                toast.classList.add('translate-x-0');
            }, 10);

            // Auto remove after 5 seconds
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
        document.getElementById('vehicleDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeVehicleModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeVehicleModal();
            }
            if (e.key === 'r' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                document.getElementById('refresh-dashboard').click();
            }
        });
    </script>
@endpush
