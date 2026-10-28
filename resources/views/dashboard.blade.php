@extends('layouts.main-layout')

@section('title', 'Dashboard')

@section('content')
    @php
        // Same gates as the sidebar (CompanyMenu): plan feature + permission.
        $user = auth()->user();
        $showDrivers = \Illuminate\Support\Facades\Blade::check('feature', 'drivers') && $user->can('drivers.view');
        $showFleet = \Illuminate\Support\Facades\Blade::check('feature', 'fleet') && $user->can('fleets.dashboard');
        $showTrips = $tripStats !== null;
        $showMaintenance = \Illuminate\Support\Facades\Blade::check('feature', 'maintenance') && $user->can('maintenance.view');
        $percent = fn (int $part, int $whole): int => $whole > 0 ? (int) round($part / $whole * 100) : 0;
        $applicationUrl = $company?->slug ? route('application.form', $company->slug) : null;
        $driverAlerts = $driverComplianceStats['overdue'] + $driverComplianceStats['due_soon'];
        $fleetAlerts = $fleetComplianceStats['overdue'] + $fleetComplianceStats['due_soon'];
    @endphp

    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="'Welcome back, '.\Illuminate\Support\Str::before($user->name, ' ')" description="Here's your compliance overview.">
            @if ($company)
                <x-slot:actions>
                    <div class="flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2">
                        @if ($company->logo_path)
                            <img src="{{ asset('storage/'.$company->logo_path) }}" alt="" width="32" height="32" class="size-8 rounded-md object-cover">
                        @else
                            <span class="flex size-8 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground" aria-hidden="true">{{ mb_strtoupper(mb_substr($company->company_name, 0, 1)) }}</span>
                        @endif
                        <span class="min-w-0">
                            <span class="block text-xs text-muted-foreground">Company</span>
                            <span class="block truncate text-sm font-medium">{{ $company->company_name }}</span>
                        </span>
                    </div>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($showDrivers)
            <h2 class="sr-only">Drivers</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Active drivers" :value="$driverStats['active']" icon="user-round-check" tone="success"
                    :hint="'of '.$driverStats['total'].' total'" :href="route('admin.driver.index')" />
                <x-ui.stat label="Pending review" :value="$driverStats['pending']" icon="clock" tone="warning" :href="route('admin.driver.index')" />
                <x-ui.stat label="New applicants" :value="$driverStats['new_applicants']" icon="user-round-plus" tone="info" hint="Last 7 days" />
                <x-ui.stat label="Inactive" :value="$driverStats['inactive']" icon="user-round-x" tone="neutral" hint="Inactive, terminated or rejected" />
            </div>
        @endif

        @if ($showTrips)
            <h2 class="sr-only">Trips</h2>
            <div @class(['grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4', 'mt-4' => $showDrivers])>
                <x-ui.stat label="On the road" :value="$tripStats['on_road']" icon="truck" tone="info" hint="Trips in progress"
                    :href="route('trips.index', ['status' => 'in_progress'])" />
                <x-ui.stat label="Pending pickup" :value="$tripStats['pending_pickup']" icon="package" tone="warning" hint="Load not picked up yet"
                    :href="route('trips.index', ['pickup' => 'pending'])" />
            </div>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Public application link --}}
            <x-ui.card title="Driver application" description="Share this link with applicants." class="lg:col-span-1">
                <x-slot:action>
                    <x-ui.badge tone="brand">{{ svg('lucide-megaphone', '', ['aria-hidden' => 'true']) }} Hiring</x-ui.badge>
                </x-slot:action>

                @if ($applicationUrl)
                    <div x-data="{ copied: false }" class="space-y-3">
                        <label for="application-link" class="label">Public application link</label>
                        <div class="flex gap-2">
                            <input id="application-link" type="text" readonly value="{{ $applicationUrl }}" class="input w-full min-w-0 font-mono text-xs" x-on:focus="$el.select()">
                            <x-ui.button variant="outline" size="icon" aria-label="Copy link"
                                x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($applicationUrl) }}).then(() => { copied = true; window.notify('success', 'Link copied'); setTimeout(() => copied = false, 2000) })">
                                <span x-show="! copied">{{ svg('lucide-copy', '', ['aria-hidden' => 'true']) }}</span>
                                <span x-show="copied" x-cloak>{{ svg('lucide-check', '', ['aria-hidden' => 'true']) }}</span>
                            </x-ui.button>
                        </div>
                        <x-ui.button :href="$applicationUrl" icon="external-link" class="w-full" target="_blank" rel="noopener">Open application form</x-ui.button>
                    </div>
                @else
                    <p class="text-sm text-muted-foreground">The link is available once your account belongs to a company.</p>
                @endif
            </x-ui.card>

            {{-- Driver compliance --}}
            @if ($showDrivers)
                <x-ui.card title="Driver compliance" description="Documents expired or expiring in 30 days.">
                    @can('drivers.dashboard')
                        <x-slot:action>
                            <x-ui.button :href="route('admin.compliance.drivers')" variant="ghost" size="sm" icon-end="arrow-right">View</x-ui.button>
                        </x-slot:action>
                    @endcan
                    @include('partials.dashboard.compliance-bars', ['stats' => $driverComplianceStats, 'total' => $driverAlerts, 'percent' => $percent])
                </x-ui.card>
            @endif

            {{-- Fleet compliance --}}
            @if ($showFleet)
                <x-ui.card title="Fleet compliance" description="Vehicle and trailer documents expired or expiring in 30 days.">
                    <x-slot:action>
                        <x-ui.button :href="route('admin.compliance.fleet')" variant="ghost" size="sm" icon-end="arrow-right">View</x-ui.button>
                    </x-slot:action>
                    @include('partials.dashboard.compliance-bars', ['stats' => $fleetComplianceStats, 'total' => $fleetAlerts, 'percent' => $percent])
                </x-ui.card>
            @endif
        </div>

        @if ($showFleet || $showMaintenance)
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                @if ($showFleet)
                    <x-ui.card title="Fleet" description="Units in your fleet.">
                        @if ($fleetStats['units'] === 0)
                            <x-ui.empty-state icon="truck" title="No vehicles yet" description="Add vehicles and trailers to track their documents and maintenance." class="border-0 p-6!">
                                @can('vehicles.view')
                                    <x-ui.button :href="route('admin.vehicle.index')" size="sm" icon="plus">Add vehicle</x-ui.button>
                                @endcan
                            </x-ui.empty-state>
                        @else
                            <dl class="grid grid-cols-3 gap-4">
                                @foreach (['Total units' => $fleetStats['units'], 'Vehicles' => $fleetStats['vehicles'], 'Trailers' => $fleetStats['trailers']] as $label => $value)
                                    <div class="rounded-xl bg-primary/5 p-4">
                                        <dt class="text-xs text-muted-foreground">{{ $label }}</dt>
                                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </x-ui.card>
                @endif

                @if ($showMaintenance)
                    <x-ui.card title="Maintenance" description="Service history across your fleet.">
                        <x-slot:action>
                            <x-ui.button :href="route('admin.service-log.index')" variant="ghost" size="sm" icon-end="arrow-right">Service log</x-ui.button>
                        </x-slot:action>
                        @if ($maintenanceStats['total_services'] === 0)
                            <x-ui.empty-state icon="wrench" title="No services logged" description="Log services to keep a maintenance history for each unit." class="border-0 p-6!" />
                        @else
                            <dl class="grid grid-cols-2 gap-4">
                                <div class="rounded-xl bg-primary/5 p-4">
                                    <dt class="text-xs text-muted-foreground">All services</dt>
                                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $maintenanceStats['total_services'] }}</dd>
                                </div>
                                <div class="rounded-xl bg-primary/5 p-4">
                                    <dt class="text-xs text-muted-foreground">Last 30 days</dt>
                                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $maintenanceStats['recent_30_days'] }}</dd>
                                </div>
                            </dl>
                        @endif
                    </x-ui.card>
                @endif
            </div>
        @endif

        @if ($showDrivers && $driverStats['total'] === 0)
            <x-ui.empty-state icon="id-card" title="No drivers yet" description="Add your first driver, or share the application link above so applicants can apply." class="mt-6 bg-card">
                @can('drivers.create')
                    <x-ui.button :href="route('admin.driver.create')" icon="plus" size="sm">Add driver</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @endif
    </div>
@endsection
