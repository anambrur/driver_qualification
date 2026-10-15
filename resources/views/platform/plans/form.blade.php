@extends('layouts.platform')

@section('title', $plan->exists ? 'Edit plan' : 'New plan')

@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]';
    $input = 'mt-1 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $button = 'rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600';
@endphp

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">{{ $plan->exists ? $plan->name : 'New plan' }}</h1>

    <form method="POST" action="{{ $plan->exists ? route('platform.plans.update', $plan) : route('platform.plans.store') }}" class="{{ $card }} max-w-3xl space-y-4">
        @csrf
        @if ($plan->exists)
            @method('PUT')
        @endif
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="block text-sm text-gray-700 dark:text-gray-300">Name<input name="name" value="{{ old('name', $plan->name) }}" class="{{ $input }}" required maxlength="100"></label>
            <label class="block text-sm text-gray-700 dark:text-gray-300">Slug<input name="slug" value="{{ old('slug', $plan->slug) }}" class="{{ $input }}" required maxlength="100"></label>
            <label class="block text-sm text-gray-700 dark:text-gray-300">Sort order<input type="number" min="0" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="{{ $input }}"></label>
            <div class="flex items-end gap-6 text-sm text-gray-700 dark:text-gray-300">
                <label><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $plan->is_public))> Public</label>
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))> Active</label>
            </div>
        </div>
        <label class="block text-sm text-gray-700 dark:text-gray-300">Description
            <textarea name="description" rows="3" class="{{ $input }} h-auto py-2">{{ old('description', $plan->description) }}</textarea>
        </label>
        <label class="block text-sm text-gray-700 dark:text-gray-300">Marketing bullets (one per line, display only)
            @php($bullets = old('marketing_bullets', $plan->marketing_bullets ?? []))
            <textarea name="marketing_bullets" rows="4" class="{{ $input }} h-auto py-2">{{ is_array($bullets) ? implode("\n", $bullets) : $bullets }}</textarea>
        </label>
        <div class="flex gap-3">
            <button class="{{ $button }}">Save</button>
            <a href="{{ route('platform.plans.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700 dark:text-gray-300">Back</a>
        </div>
    </form>

    @if ($plan->exists)
        <section class="{{ $card }} mt-4 max-w-3xl">
            <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Prices</h2>
            <p class="mb-3 text-xs text-gray-500">Prices are never edited. A new price for the same interval and currency archives the current one; existing subscriptions keep theirs. Create the Price in Stripe first and paste its id.</p>
            <table class="mb-4 min-w-full text-left text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($plan->prices as $price)
                        <tr class="text-gray-700 dark:text-gray-300">
                            <td class="py-2 pr-4">{{ $price->amount }} {{ $price->currency }} / {{ $price->interval }}</td>
                            <td class="py-2 pr-4 text-xs">{{ $price->stripe_price_id ?? 'no Stripe price' }}</td>
                            <td class="py-2 pr-4">{{ $price->archived_at ? 'archived '.$price->archived_at->format('M j, Y') : 'current' }}</td>
                            <td class="py-2">
                                @unless ($price->archived_at)
                                    <form method="POST" action="{{ route('platform.plans.prices.archive', [$plan, $price->id]) }}">
                                        @csrf
                                        <button class="text-sm text-red-600 hover:underline">Archive</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <form method="POST" action="{{ route('platform.plans.prices.store', $plan) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <select name="interval" class="h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="month">Monthly</option>
                    <option value="year">Yearly</option>
                </select>
                <input name="amount" placeholder="Amount" class="h-10 w-28 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" required>
                <input name="currency" value="USD" maxlength="3" class="h-10 w-20 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" required>
                <input name="stripe_price_id" placeholder="price_..." class="h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <button class="{{ $button }}">Add price</button>
            </form>
        </section>

        <section class="{{ $card }} mt-4 max-w-3xl">
            <h2 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Features</h2>
            <p class="mb-3 text-xs text-gray-500">Changes apply immediately to every company on this plan. A blank limit means unlimited.</p>
            <form method="POST" action="{{ route('platform.plans.features', $plan) }}">
                @csrf
                @method('PUT')
                <table class="mb-4 min-w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($features as $feature)
                            @php($row = $planFeatures->get($feature->value))
                            <tr class="text-gray-700 dark:text-gray-300">
                                <td class="py-2 pr-4">{{ $feature->label() }} <span class="text-xs text-gray-500">{{ $feature->group() }}</span></td>
                                <td class="py-2 pr-4">
                                    <input type="hidden" name="features[{{ $feature->value }}][enabled]" value="0">
                                    <input type="checkbox" name="features[{{ $feature->value }}][enabled]" value="1" @checked($row?->enabled)>
                                </td>
                                <td class="py-2">
                                    @if ($feature->isLimit())
                                        <input type="number" min="0" name="features[{{ $feature->value }}][limit_value]" value="{{ $row?->limit_value }}" class="h-9 w-28 rounded-lg border border-gray-300 px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <span class="text-xs text-gray-500">{{ $feature->unit() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button class="{{ $button }}">Save features</button>
            </form>
        </section>

        <form method="POST" action="{{ route('platform.plans.destroy', $plan) }}" class="mt-4" onsubmit="return confirm('Delete this plan? Only a plan nobody ever used can be deleted.')">
            @csrf
            @method('DELETE')
            <button class="text-sm text-red-600 hover:underline">Delete plan</button>
        </form>
    @endif
@endsection
