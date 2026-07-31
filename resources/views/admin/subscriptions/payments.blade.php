@extends('layouts.main-layout')

@section('title', 'Subscription Payments')

@section('content')
<div class="p-4 sm:p-6">

    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Subscription Payments</h1>
            <p class="text-sm text-gray-500 mt-1">
                Local payment records from Stripe. Total paid:
                <strong>${{ number_format($totalPaid ?? 0, 2) }}</strong>
            </p>
        </div>
        <a href="{{ route('admin.subscriptions.dashboard') }}" class="text-sm text-indigo-600 hover:underline">← Dashboard</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-6">
        <form action="{{ route('admin.subscriptions.payments') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
                    <option value="">All Statuses</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                    <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                </select>
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">Filter</button>
                <a href="{{ route('admin.subscriptions.payments') }}" class="py-2 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-md">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                {{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}
                                @if($payment->billing_reason)
                                    <div class="text-xs text-gray-400">{{ $payment->billing_reason }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if($payment->user)
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $payment->user->name }}</div>
                                    <div class="text-xs text-gray-500">
                                        <a href="{{ route('admin.subscriptions.show', $payment->user) }}" class="hover:underline">{{ $payment->user->email }}</a>
                                    </div>
                                @else
                                    <span class="text-gray-400">Deleted User</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-gray-700 dark:text-gray-300">{{ $payment->plan->name ?? 'N/A' }}</td>
                            <td class="px-4 py-4 font-bold text-gray-900 dark:text-white">
                                ${{ number_format($payment->amount, 2) }}
                                <span class="text-xs font-normal text-gray-500 uppercase">{{ $payment->currency }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize
                                    {{ $payment->status === 'paid' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $payment->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ !in_array($payment->status, ['paid','failed']) ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                    {{ $payment->status }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                @if($payment->hosted_invoice_url)
                                    <a href="{{ $payment->hosted_invoice_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline text-sm">View</a>
                                @elseif($payment->stripe_invoice_id)
                                    <span class="text-xs text-gray-400">{{ $payment->stripe_invoice_id }}</span>
                                @else
                                    <span class="text-gray-400 text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No payments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($payments, 'hasPages') && $payments->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
