@extends('layouts.main-layout')

@section('title', 'Subscription Payments')

@section('content')
<div class="p-4 sm:p-6">

    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Subscription Payments</h1>
            <p class="text-sm text-gray-500 mt-1">Manage and view all subscription payments and invoices.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-6">
        <form action="{{ route('admin.subscriptions.payments') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Invoice # or Email" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                    Filter
                </button>
                <a href="{{ route('admin.subscriptions.payments') }}" class="py-2 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-md transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Payments Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-4 py-4">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $payment->invoice_number }}</span>
                                <div class="text-xs text-gray-500 mt-1">{{ $payment->created_at->format('M d, Y') }}</div>
                            </td>
                            <td class="px-4 py-4">
                                @if($payment->user)
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $payment->user->name }}</div>
                                    <div class="text-xs text-gray-500"><a href="{{ route('admin.subscriptions.show', $payment->user) }}" class="hover:underline">{{ $payment->user->email }}</a></div>
                                @else
                                    <span class="text-gray-400">Deleted User</span>
                                @endif
                                <div class="text-xs text-blue-600 mt-1">Plan: {{ $payment->plan->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 py-4 font-bold text-gray-900 dark:text-white">
                                ${{ number_format($payment->amount, 2) }} <span class="text-xs font-normal text-gray-500 uppercase">{{ $payment->currency ?? 'USD' }}</span>
                            </td>
                            <td class="px-4 py-4 text-gray-600 dark:text-gray-300 capitalize">
                                {{ $payment->payment_method ?? 'Unknown' }}
                                @if($payment->transaction_id)
                                    <div class="text-xs text-gray-400 truncate max-w-[120px]" title="{{ $payment->transaction_id }}">{{ $payment->transaction_id }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if($payment->status === 'paid')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                        Paid {{ $payment->paid_at ? ' ('.$payment->paid_at->format('M d').')' : '' }}
                                    </span>
                                @elseif($payment->status === 'failed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                        Failed
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300 capitalize">
                                        {{ $payment->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if($payment->status !== 'paid')
                                    <form action="{{ route('admin.subscriptions.payments.mark-paid', $payment) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to mark this transaction as Paid? This will activate the associated subscription.');">
                                        @csrf
                                        <button type="submit" class="text-blue-600 hover:text-blue-800 font-medium text-sm border border-blue-600 hover:bg-blue-50 px-3 py-1 rounded-md transition-colors">
                                            Mark as Paid
                                        </button>
                                    </form>
                                @else
                                    <span class="text-gray-400 text-sm italic">No action needed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No payments found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
