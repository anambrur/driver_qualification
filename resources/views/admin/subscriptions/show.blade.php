@extends('layouts.main-layout')

@section('title', 'Manage User Subscriptions')

@section('content')
    <div class="p-4 sm:p-6">

        <div class="mb-6 flex flex-col justify-between items-start">
            <a href="{{ route('admin.subscriptions.index') }}"
                class="text-sm text-gray-500 hover:underline mb-2 flex items-center">
                ← Back to Subscriptions
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Subscriptions for {{ $user->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $user->email }}</p>
            @if ($current?->isAccessible())
                <p class="text-sm text-green-600 mt-1">Current access: {{ $current->plan?->name }} ({{ $current->stripe_status }})</p>
            @endif
        </div>

        @if (session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="col-span-1 lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-800 dark:text-white">Subscription History</h2>
                    </div>
                    <div class="p-0">
                        @forelse($subscriptions as $sub)
                            <div class="p-5 border-b border-gray-50 last:border-0">
                                <div class="flex justify-between items-start gap-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <h3 class="font-bold text-gray-900 dark:text-white text-lg">{{ $sub->plan->name ?? 'Unknown Plan' }}</h3>
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 capitalize">{{ $sub->stripe_status }}</span>
                                            <span class="text-xs text-gray-400 capitalize">{{ $sub->billing_cycle }} · {{ $sub->source }}</span>
                                        </div>
                                        <div class="mt-2 text-sm text-gray-600 grid grid-cols-2 gap-2 max-w-md">
                                            <div>
                                                <span class="font-medium text-gray-700">Started:</span><br>
                                                {{ $sub->created_at?->format('M d, Y g:i A') ?? 'N/A' }}
                                            </div>
                                            <div>
                                                <span class="font-medium text-gray-700">Access ends:</span><br>
                                                @if ($sub->accessEndsAt())
                                                    {{ $sub->accessEndsAt()->format('M d, Y g:i A') }}
                                                @elseif ($sub->active())
                                                    <span class="text-green-600 font-medium">Ongoing</span>
                                                @else
                                                    —
                                                @endif
                                            </div>
                                            @if ($sub->stripe_subscription_id)
                                                <div class="col-span-2 text-xs font-mono text-gray-400 break-all">{{ $sub->stripe_subscription_id }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2 relative" x-data="{ open: false }">
                                        <button type="button" @click="open = !open"
                                            class="text-gray-500 hover:text-gray-700 px-2 py-1 rounded-md border border-gray-200 bg-white shadow-sm text-sm">
                                            Actions ▾
                                        </button>
                                        <div x-show="open" @click.away="open = false" style="display:none;"
                                            class="absolute top-full right-0 mt-1 w-44 bg-white border border-gray-200 rounded-md shadow-lg z-10 py-1">
                                            @if ($sub->isAccessible())
                                                <form action="{{ route('admin.subscriptions.expire', $sub) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-red-600 text-sm">Expire Now</button>
                                                </form>
                                                <form action="{{ route('admin.subscriptions.suspend', $sub) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-orange-600 text-sm">Cancel at period end</button>
                                                </form>
                                            @elseif ($sub->onGracePeriod())
                                                <form action="{{ route('admin.subscriptions.reactivate', $sub) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-green-600 text-sm">Reactivate</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-gray-500">No subscription history found for this user.</div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-800 dark:text-white">Payment History</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Invoice</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Amount</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date/Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            <div>{{ $payment->stripe_invoice_id ?? '—' }}</div>
                                            @if ($payment->hosted_invoice_url)
                                                <a href="{{ $payment->hosted_invoice_url }}" target="_blank" rel="noreferrer" class="text-xs text-blue-600 hover:underline">Open invoice</a>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                                        <td class="px-4 py-3">
                                            <span class="font-medium capitalize {{ $payment->status === 'paid' ? 'text-green-600' : 'text-orange-600' }}">{{ $payment->status }}</span>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-4 text-center text-gray-500">No payments found for this user.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 sticky top-6">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 rounded-t-xl">
                        <h2 class="font-semibold text-gray-800 dark:text-white">Grant Subscription Manually</h2>
                        <p class="text-xs text-gray-500 mt-1">Complimentary local access (no Stripe charge).</p>
                    </div>
                    <div class="p-5">
                        <form action="{{ route('admin.subscriptions.grant', $user) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label for="plan_id" class="block text-sm font-medium text-gray-700 mb-1">Select Plan<span class="text-red-500">*</span></label>
                                <select name="plan_id" id="plan_id" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
                                    <option value="" disabled selected>-- Choose a Plan --</option>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}">{{ $plan->name }}
                                            ({{ $plan->price > 0 ? '$' . number_format($plan->price, 2) : 'Free' }} /
                                            {{ $plan->billing_cycle }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="custom_end_date" class="block text-sm font-medium text-gray-700 mb-1">Custom End Date (Optional)</label>
                                <input type="date" name="custom_end_date" id="custom_end_date"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes / Reason</label>
                                <textarea name="notes" id="notes" rows="2"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm"
                                    placeholder="E.g., Complimentary access..."></textarea>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                                Grant Plan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
