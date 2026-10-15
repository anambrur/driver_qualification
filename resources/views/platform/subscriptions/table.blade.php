<div class="overflow-x-auto">
    <table class="min-w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-500">
            <tr><th class="py-2 pr-4">Company</th><th class="py-2 pr-4">Plan</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4">Source</th><th class="py-2 pr-4">Amount</th><th class="py-2 pr-4">Ends</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($subscriptions as $subscription)
                <tr class="text-gray-700 dark:text-gray-300">
                    <td class="py-2 pr-4">
                        @if ($subscription->company)
                            <a href="{{ route('platform.companies.show', $subscription->company) }}" class="text-brand-600 hover:underline">{{ $subscription->company->company_name }}</a>
                        @endif
                    </td>
                    <td class="py-2 pr-4">{{ $subscription->planPrice?->plan?->name }} · {{ $subscription->interval }}</td>
                    <td class="py-2 pr-4">{{ str_replace('_', ' ', $subscription->status) }}{{ $subscription->ended_at ? ' (ended)' : '' }}</td>
                    <td class="py-2 pr-4">{{ $subscription->source }}</td>
                    <td class="py-2 pr-4">{{ $subscription->amount }} {{ $subscription->currency }}</td>
                    <td class="py-2 pr-4">{{ $subscription->ends_at?->format('M j, Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-4 text-gray-500">No subscriptions.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
