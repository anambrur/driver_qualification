<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <x-ui.card title="Profile">
        <dl class="grid gap-4">
            <x-ui.detail label="Full name" :value="$driverFullName" />
            <x-ui.detail label="Phone" :value="$driver->main_phone" />
            <x-ui.detail label="Email" :value="$driver->email" />
            <x-ui.detail label="Business name" :value="$driver->company->company_name ?? $driver->business_name" />
            <x-ui.detail label="Identification number" :value="$driver->employer_identification_number" mono />
        </dl>
    </x-ui.card>

    <div class="lg:col-span-2">
        @if ($driver->status === 'pending')
            <x-ui.card title="What’s the next step?" description="This applicant is pending: considered for employment but not yet hired or qualified to drive.">
                <div class="space-y-3 text-sm text-muted-foreground">
                    <p>Decide whether to hire after reviewing the complete driver file and running a <strong class="text-foreground">DOT Pre-Employment Screening Program (PSP) report</strong> and a <strong class="text-foreground">state Motor Vehicle Report (MVR)</strong>.</p>
                    <p>Complete the Qualifications checklist so the driver is qualified under DOT requirements. An incomplete qualification fails an audit.</p>
                </div>
                @can('drivers.hire')
                    <x-slot:footer class="flex-col sm:flex-row">
                        <x-ui.button icon="user-round-check" class="w-full sm:w-auto" data-dialog-open="hire-dialog">Hire applicant</x-ui.button>
                        <x-ui.button variant="destructive" icon="user-round-x" class="w-full sm:w-auto" data-dialog-open="not-hire-dialog">Do not hire</x-ui.button>
                    </x-slot:footer>
                @endcan
            </x-ui.card>
        @else
            <x-ui.card title="License summary">
                @php($latestLicense = $driver->licenses?->sortByDesc('expires')->first())
                @if ($latestLicense)
                    <div class="flex flex-wrap items-center gap-3 rounded-lg bg-muted/50 p-4">
                        {{ svg('lucide-id-card', 'size-6 text-muted-foreground', ['aria-hidden' => 'true']) }}
                        <p class="text-xl font-semibold tracking-tight">{{ collect([$latestLicense->state_code, $latestLicense->license_number])->filter()->implode(' · ') ?: 'License on file' }}</p>
                        @include('admin.driver.show.expiry-badge', ['date' => $latestLicense->expires])
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                        <x-ui.detail label="Class" :value="$latestLicense->class" />
                        <x-ui.detail label="State" :value="$latestLicense->state_code" />
                        <x-ui.detail label="Issued" :value="$latestLicense->issued ? \Carbon\Carbon::parse($latestLicense->issued)->format('m/d/Y') : null" />
                        <x-ui.detail label="Expires" :value="$latestLicense->expires ? \Carbon\Carbon::parse($latestLicense->expires)->format('m/d/Y') : null" />
                    </dl>

                    @if ($driver->licenses->count() > 1)
                        <h3 class="mb-3 mt-6 text-sm font-medium">Other licenses</h3>
                        <ul class="divide-y divide-border rounded-lg border border-border" role="list">
                            @foreach ($driver->licenses->sortByDesc('expires')->skip(1) as $license)
                                <li class="flex items-center justify-between gap-3 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ collect([$license->state_code, $license->license_number])->filter()->implode(' · ') }}</p>
                                        <p class="text-xs text-muted-foreground">Expires {{ $license->expires ? \Carbon\Carbon::parse($license->expires)->format('m/d/Y') : '—' }}</p>
                                    </div>
                                    @include('admin.driver.show.expiry-badge', ['date' => $license->expires])
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <x-ui.empty-state icon="id-card" title="No license on file" description="Add the driver’s license from Edit driver." class="border-0 p-6!" />
                @endif
            </x-ui.card>
        @endif
    </div>
</div>
