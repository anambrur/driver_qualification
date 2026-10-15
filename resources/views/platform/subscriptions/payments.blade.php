@extends('layouts.platform')

@section('title', 'Payments')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">Payments</h1>

    <form method="GET" class="mb-4 flex gap-3">
        <select name="status" class="h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="">Any status</option>
            @foreach (['paid', 'failed', 'refunded', 'pending'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:text-gray-300">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-gray-500">
                <tr><th class="py-2 pr-4">Paid</th><th class="py-2 pr-4">Company</th><th class="py-2 pr-4">Plan</th><th class="py-2 pr-4">Amount</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4">Reason</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($payments as $payment)
                    <tr class="text-gray-700 dark:text-gray-300">
                        <td class="py-2 pr-4">{{ $payment->paid_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $payment->company?->company_name }}</td>
                        <td class="py-2 pr-4">{{ $payment->plan?->name }}</td>
                        <td class="py-2 pr-4">{{ $payment->amount }} {{ $payment->currency }}</td>
                        <td class="py-2 pr-4">{{ $payment->status }}</td>
                        <td class="py-2 pr-4">{{ $payment->billing_reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-gray-500">No payments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
@endsection
