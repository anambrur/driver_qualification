@if ($subscriptions->isEmpty())
    <x-ui.empty-state icon="credit-card" title="No subscriptions yet" class="py-6" />
@else
    <x-ui.table>
        <thead>
            <tr><th>Company</th><th>Plan</th><th>Status</th><th>Source</th><th>Amount</th><th>Ends</th></tr>
        </thead>
        <tbody>
            @foreach ($subscriptions as $subscription)
                <tr>
                    <td data-label="Company" data-primary>
                        @include('platform.partials.cell', [
                            'title' => $subscription->company?->company_name,
                            'href' => $subscription->company ? route('platform.companies.show', $subscription->company) : null,
                        ])
                    </td>
                    <td data-label="Plan">@include('platform.partials.cell', ['title' => $subscription->planPrice?->plan?->name, 'sub' => $subscription->interval])</td>
                    <td data-label="Status">@include('platform.subscriptions.partials.status-cell')</td>
                    <td data-label="Source">{{ str_replace('_', ' ', $subscription->source) }}</td>
                    <td data-label="Amount" class="tabular-nums">{{ $subscription->amount }} {{ $subscription->currency }}</td>
                    <td data-label="Ends" class="whitespace-nowrap text-muted-foreground">{{ $subscription->ends_at?->format('M j, Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif
