{{-- resources/views/subscription/my.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'My Subscription')

@section('content')
    <div class="container mx-auto px-4 py-4">
        <h2 class="font-bold text-2xl mb-4">My Subscription</h2>

        @if ($subscription)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                {{-- Subscription Card --}}
                <div class="col-span-1">
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 flex justify-between items-center">
                            <span class="font-semibold">Current Plan</span>
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium bg-{{ $subscription->status_color }}-100 text-{{ $subscription->status_color }}-700">
                                {{ $subscription->status_label }}
                            </span>
                        </div>
                        <div class="p-4">
                            <h4 class="font-bold text-xl">{{ $subscription->plan->name }}</h4>
                            <p class="text-gray-500 mb-3">{{ $subscription->plan->description }}</p>

                            <div class="space-y-1 text-sm">
                                <div class="flex">
                                    <span class="text-gray-500 w-24">Started</span>
                                    <span>{{ $subscription->starts_at->format('M d, Y') }}</span>
                                </div>
                                @if ($subscription->ends_at)
                                    <div class="flex">
                                        <span class="text-gray-500 w-24">Expires</span>
                                        <span>
                                            {{ $subscription->ends_at->format('M d, Y') }}
                                            @if ($subscription->isExpiringSoon())
                                                <span
                                                    class="ml-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                                    {{ $subscription->daysRemaining() }} days left
                                                </span>
                                            @endif
                                        </span>
                                    </div>
                                @else
                                    <div class="flex">
                                        <span class="text-gray-500 w-24">Expires</span>
                                        <span><span
                                                class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Lifetime</span></span>
                                    </div>
                                @endif
                                <div class="flex">
                                    <span class="text-gray-500 w-24">Auto-renew</span>
                                    <span>{{ $subscription->auto_renew ? 'Yes' : 'No' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 bg-gray-50 px-4 py-3 flex gap-2">
                            <a href="{{ route('admin.plans.index') }}"
                                class="px-3 py-1 text-sm bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded transition-colors">
                                Change Plan
                            </a>
                            @if ($subscription->isAccessible())
                                <a href="{{ route('subscription.renew') }}"
                                    class="px-3 py-1 text-sm bg-white border border-green-500 hover:bg-green-50 text-green-600 rounded transition-colors">
                                    Renew
                                </a>
                                <form action="{{ route('subscription.cancel') }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to cancel?')">
                                    @csrf
                                    <button
                                        class="px-3 py-1 text-sm bg-white border border-red-500 hover:bg-red-50 text-red-600 rounded transition-colors">Cancel</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Features Card --}}
                <div class="col-span-1">
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm h-full">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 font-semibold">Plan Features</div>
                        <div class="p-4">
                            <ul class="list-none space-y-2">
                                @foreach ($subscription->plan->features ?? [] as $feature)
                                    <li class="flex items-start">
                                        <i class="bi bi-check-circle-fill text-green-500 mr-2 mt-1"></i>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">
                <p>
                    You don't have an active subscription.
                    <a href="{{ route('subscription.plans') }}" class="font-bold underline">Browse Plans →</a>
                </p>
            </div>
        @endif

        {{-- Payment History --}}
        <h4 class="font-bold text-lg mb-3">Payment History</h4>
        @if ($payments->count())
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Invoice</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="px-4 py-3"><code class="text-sm">{{ $payment->invoice_number }}</code></td>
                                <td class="px-4 py-3">{{ $payment->plan->name }}</td>
                                <td class="px-4 py-3">${{ number_format($payment->amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2 py-1 text-xs rounded-full 
                                        {{ $payment->status === 'paid'
                                            ? 'bg-green-100 text-green-700'
                                            : ($payment->status === 'pending'
                                                ? 'bg-yellow-100 text-yellow-700'
                                                : 'bg-red-100 text-red-700') }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $payment->created_at->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    @if ($payment->isPaid())
                                        <a href="{{ route('subscription.invoice.download', $payment) }}"
                                            class="inline-flex items-center px-2 py-1 text-xs bg-white border border-gray-300 hover:bg-gray-50 rounded">
                                            <i class="bi bi-download mr-1"></i> PDF
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $payments->links() }}
            </div>
        @else
            <p class="text-gray-500">No payment records found.</p>
        @endif
    </div>
@endsection
