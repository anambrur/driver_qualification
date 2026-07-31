@extends('layouts.main-layout')

@section('title', 'Manage User Subscriptions')

@section('content')
    <div class="p-4 sm:p-6">

        <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <a href="{{ route('admin.subscriptions.index') }}"
                    class="text-sm text-gray-500 hover:underline mb-2 inline-flex items-center">
                    ← Back to Subscriptions
                </a>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $user->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $user->email }}</p>
            </div>
            @if ($current?->isAccessible())
                <div class="rounded-xl border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-green-700 dark:text-green-300 font-semibold">Current access</p>
                    <p class="text-sm font-bold text-green-900 dark:text-green-100 mt-0.5">{{ $current->plan?->name }}</p>
                    <p class="text-sm text-green-800 dark:text-green-200 mt-1">
                        @if ((float) $current->amount > 0)
                            ${{ number_format($current->amount, 2) }} {{ $current->currency }}
                            <span class="capitalize">/ {{ $current->billing_cycle }}</span>
                        @else
                            Free · <span class="capitalize">{{ $current->billing_cycle }}</span>
                        @endif
                        · <span class="capitalize">{{ $current->stripe_status }}</span>
                    </p>
                </div>
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
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <h3 class="font-bold text-gray-900 dark:text-white text-lg">{{ $sub->plan->name ?? 'Unknown Plan' }}</h3>
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $sub->isAccessible() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }} capitalize">
                                                {{ $sub->stripe_status }}
                                            </span>
                                            <span class="text-xs text-gray-400 capitalize">{{ $sub->source }}</span>
                                        </div>

                                        <p class="text-base font-semibold text-gray-900 dark:text-white mb-3">
                                            @if ((float) $sub->amount > 0)
                                                ${{ number_format($sub->amount, 2) }}
                                                <span class="text-sm font-normal text-gray-500 uppercase">{{ $sub->currency }}</span>
                                                <span class="text-sm font-normal text-gray-500 capitalize">/ {{ $sub->billing_cycle }}</span>
                                            @else
                                                <span class="text-green-600">Free</span>
                                                <span class="text-sm font-normal text-gray-500 capitalize">· {{ $sub->billing_cycle }}</span>
                                            @endif
                                        </p>

                                        <div class="text-sm text-gray-600 grid grid-cols-2 gap-3 max-w-lg">
                                            <div>
                                                <span class="font-medium text-gray-700">Started</span><br>
                                                {{ $sub->created_at?->format('M d, Y g:i A') ?? 'N/A' }}
                                            </div>
                                            <div>
                                                <span class="font-medium text-gray-700">Access ends</span><br>
                                                @if ($sub->accessEndsAt())
                                                    {{ $sub->accessEndsAt()->format('M d, Y g:i A') }}
                                                @elseif ($sub->active())
                                                    <span class="text-green-600 font-medium">Renews automatically</span>
                                                @else
                                                    —
                                                @endif
                                            </div>
                                            @if ($sub->stripe_subscription_id)
                                                <div class="col-span-2">
                                                    <span class="font-medium text-gray-700">Stripe ID</span>
                                                    <div class="text-xs font-mono text-gray-400 break-all mt-0.5">{{ $sub->stripe_subscription_id }}</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2 relative shrink-0" x-data="{ open: false }">
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
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="font-semibold text-gray-800 dark:text-white">Payment History</h2>
                        <a href="{{ route('admin.subscriptions.payments') }}?search={{ urlencode($user->email) }}" class="text-xs text-indigo-600 hover:underline">All payments</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Invoice</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Amount</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date / Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($payments as $payment)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            <div>{{ $payment->raw['number'] ?? $payment->stripe_invoice_id ?? '—' }}</div>
                                            @if ($payment->plan)
                                                <div class="text-xs text-gray-400">{{ $payment->plan->name }}</div>
                                            @endif
                                            @if ($payment->hosted_invoice_url)
                                                <a href="{{ $payment->hosted_invoice_url }}" target="_blank" rel="noreferrer" class="text-xs text-blue-600 hover:underline">Open invoice</a>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                            ${{ number_format($payment->amount, 2) }}
                                            <span class="text-xs font-normal text-gray-500 uppercase">{{ $payment->currency }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize
                                                {{ $payment->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">
                                                {{ $payment->status }}
                                            </span>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                            No payments recorded yet.
                                            <div class="text-xs mt-1 text-gray-400">Paid invoices sync from checkout success or the Stripe webhook.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 sticky top-6">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 rounded-t-xl">
                        <h2 class="font-semibold text-gray-800 dark:text-white">Grant Subscription Manually</h2>
                        <p class="text-xs text-gray-500 mt-1">Complimentary local access (no Stripe charge).</p>
                    </div>
                    <div class="p-5">
                        <form action="{{ route('admin.subscriptions.grant', $user) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Plan<span class="text-red-500">*</span></label>
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
                                <label for="custom_end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom End Date (Optional)</label>
                                <input type="date" name="custom_end_date" id="custom_end_date"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes / Reason</label>
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
