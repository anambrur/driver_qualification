@extends('layouts.platform')

@section('title', $plan->exists ? 'Edit plan' : 'New plan')

@php
    // old() holds the split list after a failed save, the raw text before one
    $bullets = old('marketing_bullets', $plan->marketing_bullets ?? []);
@endphp

@section('content')
    <x-ui.page-header :title="$plan->exists ? $plan->name : 'New plan'"
        :description="$plan->exists ? 'Details, prices and the feature matrix. Feature changes apply to every company on the plan at once.' : 'Create the plan first, then add its prices and features.'">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Plans', 'href' => route('platform.plans.index')], ['label' => $plan->exists ? $plan->name : 'New plan']]" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <div class="max-w-3xl space-y-6">
        <form method="POST" action="{{ $plan->exists ? route('platform.plans.update', $plan) : route('platform.plans.store') }}">
            @csrf
            @if ($plan->exists)
                @method('PUT')
            @endif
            <x-ui.card title="Details">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Name" name="name" required>
                        <x-ui.input name="name" :value="$plan->name" required maxlength="100" />
                    </x-ui.field>
                    <x-ui.field label="Slug" name="slug" required hint="Letters, numbers, dashes and underscores.">
                        <x-ui.input name="slug" :value="$plan->slug" required maxlength="100" class="font-mono" />
                    </x-ui.field>
                    <x-ui.field label="Sort order" name="sort_order">
                        <x-ui.input type="number" min="0" name="sort_order" :value="$plan->sort_order ?? 0" />
                    </x-ui.field>
                    <div class="flex flex-wrap items-end gap-6 pb-2">
                        <x-ui.switch name="is_public" label="Public" :checked="(bool) $plan->is_public" />
                        <x-ui.switch name="is_active" label="Active" :checked="(bool) $plan->is_active" />
                    </div>
                    <x-ui.field label="Description" name="description" class="sm:col-span-2">
                        <x-ui.textarea name="description" rows="3" maxlength="2000" :value="$plan->description" />
                    </x-ui.field>
                    <x-ui.field label="Marketing bullets" name="marketing_bullets" hint="One per line, display only." class="sm:col-span-2">
                        <textarea name="marketing_bullets" id="f-marketing_bullets" rows="4" class="textarea"
                            @error('marketing_bullets') aria-invalid="true" @enderror>{{ is_array($bullets) ? implode("\n", $bullets) : $bullets }}</textarea>
                    </x-ui.field>
                </div>
                <x-slot:footer class="justify-end">
                    <x-ui.button variant="outline" :href="route('platform.plans.index')">Back</x-ui.button>
                    <x-ui.button type="submit" icon="check">{{ $plan->exists ? 'Save details' : 'Create plan' }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        @if ($plan->exists)
            <x-ui.card title="Prices" description="Prices are never edited. A new price for the same interval and currency archives the current one; existing subscriptions keep theirs. Create the Price in Stripe first and paste its id.">
                <div class="space-y-5">
                    @if ($plan->prices->isNotEmpty())
                        <x-ui.table>
                            <thead>
                                <tr><th>Price</th><th>Stripe price</th><th>State</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($plan->prices as $price)
                                    <tr>
                                        <td data-label="Price" class="font-medium">{{ $price->amount }} {{ $price->currency }} / {{ $price->interval }}</td>
                                        <td data-label="Stripe price" class="font-mono text-xs text-muted-foreground">{{ $price->stripe_price_id ?? 'none' }}</td>
                                        <td data-label="State">
                                            @if ($price->archived_at)
                                                <span class="badge" data-variant="outline">Archived {{ $price->archived_at->format('M j, Y') }}</span>
                                            @else
                                                @include('partials.ui.status-badge', ['status' => 'active'])
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @unless ($price->archived_at)
                                                <form method="POST" action="{{ route('platform.plans.prices.archive', [$plan, $price->id]) }}"
                                                    data-confirm="Archive this price?" data-confirm-button="Archive"
                                                    data-confirm-text="{{ $price->amount }} {{ $price->currency }} / {{ $price->interval }} stops being offered. Existing subscriptions keep it.">
                                                    @csrf
                                                    <x-ui.button type="submit" variant="ghost" size="sm" icon="archive">Archive</x-ui.button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    @endif

                    <form method="POST" action="{{ route('platform.plans.prices.store', $plan) }}" class="grid gap-3 sm:grid-cols-[8rem_8rem_5rem_minmax(0,1fr)_auto] sm:items-end">
                        @csrf
                        <x-ui.field label="Interval" name="interval">
                            <x-ui.select name="interval" :options="['month' => 'Monthly', 'year' => 'Yearly']" />
                        </x-ui.field>
                        <x-ui.field label="Amount" name="amount" required>
                            <x-ui.input name="amount" required inputmode="decimal" placeholder="49.00" />
                        </x-ui.field>
                        <x-ui.field label="Currency" name="currency" required>
                            <x-ui.input name="currency" value="USD" required maxlength="3" class="uppercase" />
                        </x-ui.field>
                        <x-ui.field label="Stripe price id" name="stripe_price_id">
                            <x-ui.input name="stripe_price_id" placeholder="price_..." class="font-mono" />
                        </x-ui.field>
                        <x-ui.button type="submit" icon="plus">Add price</x-ui.button>
                    </form>
                </div>
            </x-ui.card>

            <form method="POST" action="{{ route('platform.plans.features', $plan) }}">
                @csrf
                @method('PUT')
                <x-ui.card title="Features" description="Changes apply immediately to every company on this plan. A blank limit means unlimited.">
                    <x-ui.table>
                        <thead>
                            <tr><th>Feature</th><th>Included</th><th>Limit</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($features as $feature)
                                @php($row = $planFeatures->get($feature->value))
                                <tr>
                                    <td data-label="Feature">
                                        <span class="font-medium">{{ $feature->label() }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ $feature->group() }}</span>
                                    </td>
                                    <td data-label="Included">
                                        <input type="hidden" name="features[{{ $feature->value }}][enabled]" value="0">
                                        <input type="checkbox" name="features[{{ $feature->value }}][enabled]" value="1" class="input" role="switch"
                                            aria-label="Include {{ $feature->label() }}" @checked($row?->enabled)>
                                    </td>
                                    <td data-label="Limit">
                                        @if ($feature->isLimit())
                                            <div class="flex items-center gap-2">
                                                <input type="number" min="0" name="features[{{ $feature->value }}][limit_value]" value="{{ $row?->limit_value }}"
                                                    class="input w-28" aria-label="{{ $feature->label() }} limit" placeholder="Unlimited">
                                                <span class="text-xs text-muted-foreground">{{ $feature->unit() }}</span>
                                            </div>
                                        @else
                                            <span class="text-muted-foreground">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                    <x-slot:footer class="justify-end">
                        <x-ui.button type="submit" icon="check">Save features</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>

            <x-ui.card title="Delete plan" description="Only a plan no subscription, payment or trial setting uses can be deleted. Otherwise switch off Active instead.">
                <form method="POST" action="{{ route('platform.plans.destroy', $plan) }}"
                    data-confirm="Delete {{ $plan->name }}?" data-confirm-button="Delete plan" data-confirm-text="The plan and its prices are removed. This can't be undone.">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="destructive" icon="trash-2">Delete plan</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
@endsection
