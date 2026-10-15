@extends('layouts.platform')

@section('title', $company ? 'Edit company' : 'New company')

@php($input = 'mt-1 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">{{ $company ? 'Edit '.$company->company_name : 'New company' }}</h1>

    <form method="POST" action="{{ $company ? route('platform.companies.update', $company) : route('platform.companies.store') }}"
        class="max-w-3xl space-y-4 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        @csrf
        @if ($company)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ([
                'company_name' => 'Company name', 'dot_number' => 'DOT number', 'mc_number' => 'MC number',
                'email' => 'Contact email', 'billing_email' => 'Billing email', 'phone' => 'Phone',
                'address' => 'Address', 'city' => 'City', 'state_code' => 'State (2 letters)', 'zip' => 'ZIP',
            ] as $field => $label)
                <label class="block text-sm text-gray-700 dark:text-gray-300">{{ $label }}
                    <input name="{{ $field }}" value="{{ old($field, $company?->{$field}) }}" class="{{ $input }}" @if ($field === 'company_name') required @endif>
                </label>
            @endforeach
        </div>

        @unless ($company)
            <h2 class="pt-2 text-sm font-semibold text-gray-800 dark:text-white/90">Owner</h2>
            <p class="text-xs text-gray-500">The owner is emailed a link to set their password. The email can't already be a tenant or platform login.</p>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block text-sm text-gray-700 dark:text-gray-300">Owner name
                    <input name="owner_name" value="{{ old('owner_name') }}" class="{{ $input }}" required>
                </label>
                <label class="block text-sm text-gray-700 dark:text-gray-300">Owner email
                    <input type="email" name="owner_email" value="{{ old('owner_email') }}" class="{{ $input }}" required>
                </label>
            </div>
        @endunless

        <div class="flex gap-3 pt-2">
            <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Save</button>
            <a href="{{ $company ? route('platform.companies.show', $company) : route('platform.companies.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700 dark:text-gray-300">Cancel</a>
        </div>
    </form>
@endsection
