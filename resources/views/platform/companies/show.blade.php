@extends('layouts.platform')

@section('title', $company->company_name)

@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $input = 'h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $button = 'rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600';
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $company->company_name }}</h1>
            <p class="text-sm capitalize text-gray-500">{{ $company->status }}@if ($company->status_reason) · {{ $company->status_reason }}@endif</p>
        </div>
        @can('platform.companies.manage')
            <a href="{{ route('platform.companies.edit', $company) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700 dark:text-gray-300">Edit profile</a>
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="{{ $card }}">
            <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Profile</h2>
            <dl class="grid grid-cols-2 gap-2 text-sm">
                <dt class="text-gray-500">Owner</dt><dd class="text-gray-800 dark:text-gray-200">{{ $company->owner?->name }} · {{ $company->owner?->email }}</dd>
                <dt class="text-gray-500">DOT / MC</dt><dd class="text-gray-800 dark:text-gray-200">{{ $company->dot_number ?: '—' }} / {{ $company->mc_number ?: '—' }}</dd>
                <dt class="text-gray-500">Contact</dt><dd class="text-gray-800 dark:text-gray-200">{{ $company->email ?: '—' }} · {{ $company->phone ?: '—' }}</dd>
                <dt class="text-gray-500">Billing email</dt><dd class="text-gray-800 dark:text-gray-200">{{ $company->billing_email ?: '—' }}</dd>
                <dt class="text-gray-500">Created</dt><dd class="text-gray-800 dark:text-gray-200">{{ $company->created_at?->format('M j, Y') }}</dd>
            </dl>
        </section>

        <section class="{{ $card }}">
            <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Usage</h2>
            <dl class="grid grid-cols-2 gap-2 text-sm">
                @foreach ($usage as $label => [$count, $max])
                    <dt class="text-gray-500">{{ $label }}</dt><dd class="text-gray-800 dark:text-gray-200">{{ $count }} / {{ $max ?? 'unlimited' }}</dd>
                @endforeach
                <dt class="text-gray-500">Storage</dt>
                <dd class="text-gray-800 dark:text-gray-200">{{ number_format($company->storage_bytes / 1024 ** 3, 2) }} GB / {{ $storageLimitGb !== null ? $storageLimitGb.' GB' : 'unlimited' }}</dd>
            </dl>
        </section>

        <section class="{{ $card }}">
            <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Subscription</h2>
            @if ($subscription)
                <p class="mb-3 text-sm text-gray-700 dark:text-gray-300">
                    {{ $subscription->planPrice?->plan?->name }} · {{ $subscription->interval }} · {{ str_replace('_', ' ', $subscription->status) }} ({{ $subscription->source }})
                    @if ($subscription->ends_at) · ends {{ $subscription->ends_at->format('M j, Y') }}@endif
                </p>
            @else
                <p class="mb-3 text-sm text-gray-500">No open subscription.</p>
            @endif

            @can('platform.subscriptions.manage')
                <form method="POST" action="{{ route('platform.companies.subscription.grant', $company) }}" class="mb-3 flex flex-wrap gap-2">
                    @csrf
                    <select name="plan_price_id" class="{{ $input }}" required>
                        @foreach ($prices as $price)
                            <option value="{{ $price->id }}">{{ $price->plan?->name }} · {{ $price->interval }} · {{ $price->amount }} {{ $price->currency }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="ends_at" class="{{ $input }}" required>
                    <button class="{{ $button }}">Grant complimentary</button>
                </form>
                @if ($subscription)
                    <form method="POST" action="{{ route('platform.companies.subscription.move-price', $company) }}" class="mb-3 flex flex-wrap gap-2">
                        @csrf
                        <select name="plan_price_id" class="{{ $input }}" required>
                            @foreach ($prices as $price)
                                <option value="{{ $price->id }}">{{ $price->plan?->name }} · {{ $price->interval }} · {{ $price->amount }} {{ $price->currency }}</option>
                            @endforeach
                        </select>
                        <button class="{{ $button }}">Move to price</button>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['suspend' => 'Cancel at period end', 'reactivate' => 'Resume', 'expire' => 'Expire now'] as $action => $label)
                            <form method="POST" action="{{ route('platform.companies.subscription.'.$action, $company) }}">
                                @csrf
                                <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:text-gray-300">{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            @endcan
        </section>

        <section class="{{ $card }}">
            <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Lifecycle and ownership</h2>
            @can('platform.companies.lifecycle')
                <form method="POST" action="{{ route('platform.companies.status', $company) }}" class="mb-4 flex flex-wrap gap-2">
                    @csrf
                    <select name="status" class="{{ $input }}">
                        @foreach (\App\Services\Platform\PlatformCompanyService::STATUSES as $status)
                            <option value="{{ $status }}" @selected($company->status === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <input name="reason" placeholder="Reason" class="{{ $input }}" required maxlength="255">
                    <button class="{{ $button }}">Change status</button>
                </form>
            @endcan
            @can('platform.companies.manage')
                <form method="POST" action="{{ route('platform.companies.owner', $company) }}" class="flex flex-wrap gap-2">
                    @csrf
                    <input type="email" name="email" placeholder="New owner's email (an active user of this company)" class="{{ $input }} min-w-72" required>
                    <button class="{{ $button }}">Transfer ownership</button>
                </form>
            @endcan
        </section>
    </div>

    @can('platform.overrides.manage')
        <section class="{{ $card }} mt-4">
            <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Feature overrides</h2>
            <form method="POST" action="{{ route('platform.companies.overrides.store', $company) }}" class="mb-4 flex flex-wrap items-center gap-2">
                @csrf
                <select name="feature" class="{{ $input }}" required>
                    @foreach ($features as $feature)
                        <option value="{{ $feature->value }}">{{ $feature->label() }}</option>
                    @endforeach
                </select>
                <label class="text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="enabled" value="1" checked> Enabled (modules)</label>
                <input type="number" name="limit_value" min="0" placeholder="Limit (limits)" class="{{ $input }} w-36">
                <label class="text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="is_unlimited" value="1"> Unlimited</label>
                <input type="date" name="ends_at" class="{{ $input }}">
                <input name="reason" placeholder="Reason" class="{{ $input }}" required maxlength="255">
                <button class="{{ $button }}">Save override</button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr><th class="py-2 pr-4">Feature</th><th class="py-2 pr-4">Value</th><th class="py-2 pr-4">Reason</th><th class="py-2 pr-4">Period</th><th class="py-2 pr-4">State</th><th></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($overrides as $override)
                            <tr class="text-gray-700 dark:text-gray-300">
                                <td class="py-2 pr-4">{{ $override->feature_key }}</td>
                                <td class="py-2 pr-4">{{ $override->is_unlimited ? 'unlimited' : ($override->limit_value ?? ($override->enabled ? 'on' : 'off')) }}</td>
                                <td class="py-2 pr-4">{{ $override->reason }}</td>
                                <td class="py-2 pr-4">{{ $override->starts_at }} → {{ $override->ends_at ?? 'open' }}</td>
                                <td class="py-2 pr-4">{{ $override->revoked_at ? 'revoked: '.$override->revoke_reason : 'open' }}</td>
                                <td class="py-2">
                                    @unless ($override->revoked_at)
                                        <form method="POST" action="{{ route('platform.companies.overrides.revoke', $company) }}" class="flex gap-2">
                                            @csrf
                                            <input type="hidden" name="feature" value="{{ $override->feature_key }}">
                                            <input name="reason" placeholder="Why" class="{{ $input }} w-32" required maxlength="255">
                                            <button class="text-sm text-red-600 hover:underline">Revoke</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-gray-500">No overrides.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endcan
@endsection
