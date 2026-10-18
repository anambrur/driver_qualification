<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <x-ui.card title="Legal name" description="As on the driver’s license.">
        @if ($driver->status !== 'draft')
            <x-slot:action>
                <x-ui.button :href="route('admin.driver.edit', $driver->id)" variant="ghost" size="sm" icon="pencil">Edit</x-ui.button>
            </x-slot:action>
        @endif
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-ui.detail label="First" :value="$driver->first_name" />
            <x-ui.detail label="Middle" :value="$driver->middle_name" />
            <x-ui.detail label="Last" :value="$driver->last_name" />
            <x-ui.detail label="Suffix" :value="$driver->suffix" />
        </dl>
    </x-ui.card>

    <x-ui.card title="Personal information" description="Contact and identity details.">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.detail label="Date of birth" :value="$driver->date_of_birth ? \Carbon\Carbon::parse($driver->date_of_birth)->format('m/d/Y') : null" />
            <x-ui.detail label="SSN" :value="$driver->ssn ? '***-**-'.substr($driver->ssn, -4) : null" mono />
            <x-ui.detail label="Main phone" :value="$driver->main_phone" />
            <x-ui.detail label="Emergency phone" :value="$driver->alt_phone" />
            <x-ui.detail label="Email" :value="$driver->email" class="sm:col-span-2" />
        </dl>
    </x-ui.card>

    <x-ui.card title="Business information" description="Tax and employer details.">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.detail label="Business name" :value="$driver->business_name" />
            <x-ui.detail label="EIN" :value="$driver->employer_identification_number" mono />
            <x-ui.detail label="Federal tax classification" :value="$driver->federal_tax_classification ? \Illuminate\Support\Str::headline($driver->federal_tax_classification) : 'Not specified'" class="sm:col-span-2" />
        </dl>
    </x-ui.card>

    <x-ui.card title="Current address" description="Primary residence on file.">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-ui.detail label="Street" :value="$driver->address" class="col-span-2 sm:col-span-4" />
            <x-ui.detail label="City" :value="$driver->city" />
            <x-ui.detail label="State" :value="$driver->state_code" />
            <x-ui.detail label="Country" :value="$driver->country_code" />
            <x-ui.detail label="Postal code" :value="$driver->postal_code" />
            <x-ui.detail label="TWIC card"><x-ui.badge :tone="$driver->twic_card ? 'success' : 'neutral'">{{ $driver->twic_card ? 'Yes' : 'No' }}</x-ui.badge></x-ui.detail>
            <x-ui.detail label="Passport"><x-ui.badge :tone="$driver->passport ? 'success' : 'neutral'">{{ $driver->passport ? 'Yes' : 'No' }}</x-ui.badge></x-ui.detail>
        </dl>
    </x-ui.card>
</div>

@if ($driver->residence_addresses && $driver->residence_addresses->isNotEmpty())
    <x-ui.card title="Residence history" description="Previous 3 years." class="mt-6">
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2" role="list">
            @foreach ($driver->residence_addresses as $residence)
                <li class="rounded-lg border border-border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-medium">{{ $residence->address }}</p>
                        <x-ui.badge :tone="$residence->is_current ? 'info' : 'neutral'">{{ $residence->is_current ? 'Current' : 'Previous' }}</x-ui.badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">{{ collect([$residence->city, $residence->state_code, $residence->zip])->filter()->implode(', ') }}</p>
                    <p class="text-xs text-muted-foreground">{{ $residence->country_code }}</p>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
@endif

@if ($driver->licenses && $driver->licenses->isNotEmpty())
    <x-ui.card title="Driver licenses" description="Last 3 years on record." class="mt-6">
        <ul class="space-y-3" role="list">
            @foreach ($driver->licenses as $license)
                <li class="rounded-lg border border-border p-4">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold">{{ collect([$license->state_code, $license->license_number])->filter()->implode(' · ') }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ trim(($license->first_name ?? '').' '.($license->last_name ?? '')) ?: $driverFullName }}
                            </p>
                        </div>
                        @include('admin.driver.show.expiry-badge', ['date' => $license->expires])
                    </div>
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <x-ui.detail label="Issued" :value="$license->issued ? \Carbon\Carbon::parse($license->issued)->format('m/d/Y') : null" />
                        <x-ui.detail label="Expires" :value="$license->expires ? \Carbon\Carbon::parse($license->expires)->format('m/d/Y') : null" />
                        <x-ui.detail label="Class" :value="$license->class" />
                        <x-ui.detail label="State" :value="$license->state_code" />
                    </dl>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
@endif
