@extends('layouts.platform')

@section('title', $company->company_name)

@php
    $priceOptions = $prices->mapWithKeys(fn ($price) => [$price->id => $price->plan?->name.' · '.$price->interval.' · '.$price->amount.' '.$price->currency])->all();
    $statuses = collect(\App\Services\Platform\PlatformCompanyService::STATUSES)->mapWithKeys(fn ($status) => [$status => ucfirst($status)])->all();
    $usageIcons = ['users', 'id-card', 'truck', 'caravan'];
@endphp

@section('content')
    <x-ui.page-header :title="$company->company_name" :description="'Customer since '.$company->created_at?->format('M j, Y').($company->status_reason ? ' · '.$company->status_reason : '')">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Companies', 'href' => route('platform.companies.index')], ['label' => $company->company_name]]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            @include('partials.ui.status-badge', ['status' => $company->status])
            @can('platform.companies.manage')
                <x-ui.button variant="outline" icon="pencil" :href="route('platform.companies.edit', $company)">Edit profile</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($usage as $label => [$count, $max])
            <x-ui.stat :label="$label" :value="$count.' / '.($max ?? '∞')" :icon="$usageIcons[$loop->index] ?? 'gauge'" :hint="$max === null ? 'Unlimited' : null" />
        @endforeach
        <x-ui.stat label="Storage" :value="number_format($company->storage_bytes / 1024 ** 3, 2).' GB'" icon="hard-drive"
            :hint="$storageLimitGb !== null ? 'of '.$storageLimitGb.' GB' : 'Unlimited'" />
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <x-ui.card title="Profile">
            <dl class="grid gap-4 sm:grid-cols-2">
                <x-ui.detail label="Owner" :value="$company->owner?->name" />
                <x-ui.detail label="Owner email" :value="$company->owner?->email" />
                <x-ui.detail label="DOT number" :value="$company->dot_number" mono />
                <x-ui.detail label="MC number" :value="$company->mc_number" mono />
                <x-ui.detail label="Contact email" :value="$company->email" />
                <x-ui.detail label="Phone" :value="$company->phone" />
                <x-ui.detail label="Billing email" :value="$company->billing_email" />
                <x-ui.detail label="Created" :value="$company->created_at?->format('M j, Y')" />
            </dl>
        </x-ui.card>

        <x-ui.card title="Subscription">
            <div class="space-y-6">
                @if ($subscription)
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <x-ui.detail label="Plan" :value="$subscription->planPrice?->plan?->name.' · '.$subscription->interval" />
                        <x-ui.detail label="Status">@include('partials.ui.status-badge', ['status' => $subscription->status])</x-ui.detail>
                        <x-ui.detail label="Source" :value="str_replace('_', ' ', $subscription->source)" />
                        <x-ui.detail label="Ends" :value="$subscription->ends_at?->format('M j, Y')" />
                    </dl>
                @else
                    <x-ui.empty-state icon="credit-card" title="No open subscription" class="py-6" />
                @endif

                @can('platform.subscriptions.manage')
                    <form method="POST" action="{{ route('platform.companies.subscription.grant', $company) }}" class="form-section">
                        @csrf
                        <h3 class="form-section-title">{{ svg('lucide-gift', '', ['aria-hidden' => 'true']) }} Grant complimentary</h3>
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end">
                            <x-ui.field label="Price" name="plan_price_id" for="grant-price">
                                <x-ui.select name="plan_price_id" id="grant-price" required :options="$priceOptions" />
                            </x-ui.field>
                            <x-ui.field label="Until" name="ends_at" for="grant-ends">
                                <x-ui.input type="date" name="ends_at" id="grant-ends" required />
                            </x-ui.field>
                            <x-ui.button type="submit">Grant</x-ui.button>
                        </div>
                    </form>

                    @if ($subscription)
                        <form method="POST" action="{{ route('platform.companies.subscription.move-price', $company) }}" class="form-section">
                            @csrf
                            <h3 class="form-section-title">{{ svg('lucide-arrow-right-left', '', ['aria-hidden' => 'true']) }} Move to another price</h3>
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                <x-ui.field label="Price" name="plan_price_id" for="move-price">
                                    <x-ui.select name="plan_price_id" id="move-price" required :options="$priceOptions" />
                                </x-ui.field>
                                <x-ui.button type="submit" variant="outline">Move</x-ui.button>
                            </div>
                        </form>

                        <div class="form-section">
                            <h3 class="form-section-title">{{ svg('lucide-calendar-clock', '', ['aria-hidden' => 'true']) }} Period</h3>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('platform.companies.subscription.suspend', $company) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="outline" size="sm">Cancel at period end</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('platform.companies.subscription.reactivate', $company) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="outline" size="sm">Resume</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('platform.companies.subscription.expire', $company) }}"
                                    data-confirm="Expire the subscription now?" data-confirm-button="Expire now"
                                    data-confirm-text="{{ $company->company_name }} loses its plan immediately, without waiting for the period to end.">
                                    @csrf
                                    <x-ui.button type="submit" variant="destructive" size="sm">Expire now</x-ui.button>
                                </form>
                            </div>
                        </div>
                    @endif
                @endcan
            </div>
        </x-ui.card>

        @canany(['platform.companies.lifecycle', 'platform.companies.manage'])
            <x-ui.card title="Lifecycle and ownership" class="lg:col-span-2">
                <div class="grid gap-6 lg:grid-cols-2">
                    @can('platform.companies.lifecycle')
                        <form method="POST" action="{{ route('platform.companies.status', $company) }}" class="grid gap-3"
                            data-confirm="Change {{ $company->company_name }}'s status?" data-confirm-button="Change status"
                            data-confirm-text="Suspending or deactivating logs the company's users out and ends any active support session.">
                            @csrf
                            <h3 class="form-section-title">{{ svg('lucide-power', '', ['aria-hidden' => 'true']) }} Status</h3>
                            <div class="grid gap-3 sm:grid-cols-[10rem_minmax(0,1fr)]">
                                <x-ui.field label="Status" name="status">
                                    <x-ui.select name="status" :value="$company->status" :options="$statuses" />
                                </x-ui.field>
                                <x-ui.field label="Reason" name="reason" required>
                                    <x-ui.input name="reason" required maxlength="255" />
                                </x-ui.field>
                            </div>
                            <div><x-ui.button type="submit" variant="outline">Change status</x-ui.button></div>
                        </form>
                    @endcan
                    @can('platform.companies.manage')
                        <form method="POST" action="{{ route('platform.companies.owner', $company) }}" class="grid gap-3"
                            data-confirm="Transfer ownership?" data-confirm-button="Transfer"
                            data-confirm-text="The new owner gets full control of {{ $company->company_name }}, and the current owner becomes an Administrator.">
                            @csrf
                            <h3 class="form-section-title">{{ svg('lucide-user-round-cog', '', ['aria-hidden' => 'true']) }} Ownership</h3>
                            <x-ui.field label="New owner's email" name="email" required hint="Must be an active user of this company.">
                                <x-ui.input type="email" name="email" required />
                            </x-ui.field>
                            <div><x-ui.button type="submit" variant="outline">Transfer ownership</x-ui.button></div>
                        </form>
                    @endcan
                </div>
            </x-ui.card>
        @endcanany

        @can('platform.overrides.manage')
            <x-ui.card title="Feature overrides" description="Grants or limits one feature for this company only, on top of its plan. Limit and Unlimited apply to limits, Enabled to modules; a blank end date means open-ended." class="lg:col-span-2">
                <div class="space-y-6">
                    <form method="POST" action="{{ route('platform.companies.overrides.store', $company) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.5fr)_8rem_10rem_minmax(0,1.5fr)_auto] lg:items-end">
                        @csrf
                        <x-ui.field label="Feature" name="feature" required>
                            <x-ui.select name="feature" required :options="collect($features)->mapWithKeys(fn ($feature) => [$feature->value => $feature->label()])->all()" />
                        </x-ui.field>
                        <x-ui.field label="Limit" name="limit_value">
                            <x-ui.input type="number" name="limit_value" min="0" placeholder="Limits only" />
                        </x-ui.field>
                        <x-ui.field label="Ends" name="ends_at">
                            <x-ui.input type="date" name="ends_at" />
                        </x-ui.field>
                        <x-ui.field label="Reason" name="reason" required>
                            <x-ui.input name="reason" required maxlength="255" />
                        </x-ui.field>
                        <x-ui.button type="submit" icon="check">Save override</x-ui.button>
                        <div class="flex flex-wrap gap-6 sm:col-span-2 lg:col-span-5">
                            <x-ui.checkbox name="enabled" label="Enabled" :checked="true" />
                            <x-ui.checkbox name="is_unlimited" label="Unlimited" />
                        </div>
                    </form>

                    @if ($overrides->isEmpty())
                        <x-ui.empty-state icon="sliders-horizontal" title="No overrides" description="The company gets exactly what its plan includes." class="py-6" />
                    @else
                        <x-ui.table>
                            <thead>
                                <tr><th>Feature</th><th>Value</th><th>Reason</th><th>Period</th><th>State</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($overrides as $override)
                                    <tr>
                                        <td data-label="Feature" class="font-mono text-xs">{{ $override->feature_key }}</td>
                                        <td data-label="Value">{{ $override->is_unlimited ? 'unlimited' : ($override->limit_value ?? ($override->enabled ? 'on' : 'off')) }}</td>
                                        <td data-label="Reason">{{ $override->reason }}</td>
                                        <td data-label="Period" class="whitespace-nowrap text-xs text-muted-foreground">{{ $override->starts_at }} → {{ $override->ends_at ?? 'open' }}</td>
                                        <td data-label="State">
                                            @if ($override->revoked_at)
                                                <span class="badge" data-variant="outline" title="{{ $override->revoke_reason }}">Revoked</span>
                                            @else
                                                @include('partials.ui.status-badge', ['status' => 'active'])
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @unless ($override->revoked_at)
                                                <form method="POST" action="{{ route('platform.companies.overrides.revoke', $company) }}" class="flex justify-end gap-2">
                                                    @csrf
                                                    <input type="hidden" name="feature" value="{{ $override->feature_key }}">
                                                    <x-ui.input name="reason" placeholder="Why" required maxlength="255" class="w-32!" aria-label="Reason for revoking {{ $override->feature_key }}" :id="'revoke-'.$override->id" />
                                                    <x-ui.button type="submit" variant="ghost" size="sm">Revoke</x-ui.button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    @endif
                </div>
            </x-ui.card>
        @endcan
    </div>
@endsection
