{{-- resources/views/admin/subscriptions/dashboard.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Subscription Dashboard')

@section('content')
    <div class="p-4 sm:p-6">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Subscription Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Overview of all subscriptions and revenue</p>
        </div>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 uppercase font-semibold tracking-wide">Active</p>
                <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['total_active'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 uppercase font-semibold tracking-wide">Expired</p>
                <p class="text-3xl font-bold text-red-500 mt-1">{{ $stats['total_expired'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 uppercase font-semibold tracking-wide">Expiring (7d)</p>
                <p class="text-3xl font-bold text-yellow-500 mt-1">{{ $stats['expiring_soon'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500 uppercase font-semibold tracking-wide">New This Month</p>
                <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['new_this_month'] }}</p>
            </div>
        </div>

        {{-- Revenue --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
                <p class="text-sm text-gray-500 font-medium">Total Revenue</p>
                <p class="text-4xl font-bold text-gray-900 dark:text-white mt-1">
                    ${{ number_format($stats['total_revenue'], 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
                <p class="text-sm text-gray-500 font-medium">Revenue This Month</p>
                <p class="text-4xl font-bold text-green-600 mt-1">${{ number_format($stats['monthly_revenue'], 2) }}</p>
            </div>
        </div>

        {{-- Plans breakdown --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-6 p-5">
            <h2 class="font-semibold text-gray-800 dark:text-white mb-4">Active Subscribers by Plan</h2>
            <div class="space-y-3">
                @foreach ($plans as $plan)
                    @php $pct = $stats['total_active'] > 0 ? ($plan->subscriptions_count / $stats['total_active'] * 100) : 0; @endphp
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-600 dark:text-gray-400 w-28 shrink-0">{{ $plan->name }}</span>
                        <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width:{{ $pct }}%"></div>
                        </div>
                        <span
                            class="text-sm font-semibold text-gray-800 dark:text-white w-8 text-right">{{ $plan->subscriptions_count }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Recent Subscriptions --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-white">Recent Subscriptions</h2>
                <a href="{{ route('admin.subscriptions.index') }}" class="text-sm text-blue-600 hover:underline">View All
                    →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">User</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Plan</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Expires</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($recentSubscriptions as $sub)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $sub->user->name }}</div>
                                    <div class="text-xs text-gray-400">{{ $sub->user->email }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $sub->plan->name }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $colors = [
                                            'active' => 'bg-green-100 text-green-700',
                                            'trial' => 'bg-blue-100 text-blue-700',
                                            'grace' => 'bg-yellow-100 text-yellow-700',
                                            'expired' => 'bg-red-100 text-red-700',
                                            'cancelled' => 'bg-gray-100 text-gray-600',
                                            'suspended' => 'bg-orange-100 text-orange-700',
                                        ];
                                    @endphp
                                    <span
                                        class="px-2 py-1 rounded-full text-xs font-medium {{ $colors[$sub->status] ?? 'bg-gray-100' }}">
                                        {{ $sub->stripe_status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ $sub->ends_at?->format('M d, Y') ?? 'Lifetime' }}
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.subscriptions.show', $sub->user) }}"
                                        class="text-blue-600 hover:underline text-xs">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
