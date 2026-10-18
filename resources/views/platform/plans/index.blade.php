@extends('layouts.platform')

@section('title', 'Plans')

@section('content')
    <x-ui.page-header title="Plans" description="What companies can subscribe to: prices, features and whether the plan is offered.">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('platform.plans.create')">New plan</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($plans->isEmpty())
        <x-ui.empty-state icon="layers" title="No plans yet" description="Create a plan, then add its prices and features.">
            <x-ui.button icon="plus" size="sm" :href="route('platform.plans.create')">New plan</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.card flush class="py-0!">
            <x-ui.table>
                <thead>
                    <tr><th>Plan</th><th>Current prices</th><th>Public</th><th>Active</th></tr>
                </thead>
                <tbody>
                    @foreach ($plans as $plan)
                        <tr>
                            <td data-label="Plan" data-primary>
                                @include('platform.partials.cell', ['title' => $plan->name, 'href' => route('platform.plans.edit', $plan), 'sub' => $plan->slug])
                            </td>
                            <td data-label="Current prices">
                                @forelse ($plan->prices->whereNull('archived_at') as $price)
                                    <div class="tabular-nums">{{ $price->amount }} {{ $price->currency }} / {{ $price->interval }}</div>
                                @empty
                                    <span class="text-muted-foreground">No price</span>
                                @endforelse
                            </td>
                            @foreach (['Public' => $plan->is_public, 'Active' => $plan->is_active] as $label => $on)
                                <td data-label="{{ $label }}"><span class="badge" data-variant="{{ $on ? 'secondary' : 'outline' }}">{{ $on ? 'Yes' : 'No' }}</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif
@endsection
