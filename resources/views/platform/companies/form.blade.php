@extends('layouts.platform')

@section('title', $company ? 'Edit company' : 'New company')

@php
    $breadcrumbs = $company
        ? [['label' => 'Companies', 'href' => route('platform.companies.index')], ['label' => $company->company_name, 'href' => route('platform.companies.show', $company)], ['label' => 'Edit']]
        : [['label' => 'Companies', 'href' => route('platform.companies.index')], ['label' => 'New company']];
@endphp

@section('content')
    <x-ui.page-header :title="$company ? 'Edit '.$company->company_name : 'New company'"
        :description="$company ? 'Profile and contact details. Status and ownership are changed from the company page.' : 'Creates the company with its roles and settings, and invites the owner.'">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="$breadcrumbs" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <form method="POST" action="{{ $company ? route('platform.companies.update', $company) : route('platform.companies.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($company)
            @method('PUT')
        @endif

        <x-ui.card title="Company">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Company name" name="company_name" required class="sm:col-span-2">
                    <x-ui.input name="company_name" :value="$company?->company_name" required maxlength="255" />
                </x-ui.field>
                <x-ui.field label="DOT number" name="dot_number">
                    <x-ui.input name="dot_number" :value="$company?->dot_number" maxlength="20" inputmode="numeric" />
                </x-ui.field>
                <x-ui.field label="MC number" name="mc_number">
                    <x-ui.input name="mc_number" :value="$company?->mc_number" maxlength="20" />
                </x-ui.field>
                <x-ui.field label="Time zone" name="timezone" class="sm:col-span-2">
                    <x-ui.select name="timezone" required :options="\App\Support\TimeZones::OPTIONS" :value="$company?->timezone ?? \App\Support\TimeZones::preselected()" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Contact">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Contact email" name="email">
                    <x-ui.input type="email" name="email" :value="$company?->email" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Phone" name="phone">
                    <x-ui.input type="tel" name="phone" :value="$company?->phone" maxlength="20" />
                </x-ui.field>
                <x-ui.field label="Billing email" name="billing_email" hint="Invoices and payment notices go here." class="sm:col-span-2">
                    <x-ui.input type="email" name="billing_email" :value="$company?->billing_email" maxlength="255" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Address">
            <div class="grid gap-4 sm:grid-cols-6">
                <x-ui.field label="Street address" name="address" class="sm:col-span-6">
                    <x-ui.input name="address" :value="$company?->address" maxlength="255" autocomplete="street-address" />
                </x-ui.field>
                <x-ui.field label="City" name="city" class="sm:col-span-3">
                    <x-ui.input name="city" :value="$company?->city" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="State" name="state_code" hint="2 letters" class="sm:col-span-1">
                    <x-ui.input name="state_code" :value="$company?->state_code" maxlength="2" class="uppercase" />
                </x-ui.field>
                <x-ui.field label="ZIP" name="zip" class="sm:col-span-2">
                    <x-ui.input name="zip" :value="$company?->zip" maxlength="20" inputmode="numeric" />
                </x-ui.field>
            </div>
        </x-ui.card>

        @unless ($company)
            <x-ui.card title="Owner" description="The owner is emailed a link to set their password. The email can't already be a tenant or platform login.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Owner name" name="owner_name" required>
                        <x-ui.input name="owner_name" required maxlength="255" />
                    </x-ui.field>
                    <x-ui.field label="Owner email" name="owner_email" required>
                        <x-ui.input type="email" name="owner_email" required maxlength="255" />
                    </x-ui.field>
                </div>
            </x-ui.card>
        @endunless

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="outline" :href="$company ? route('platform.companies.show', $company) : route('platform.companies.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">{{ $company ? 'Save changes' : 'Create company' }}</x-ui.button>
        </div>
    </form>
@endsection
