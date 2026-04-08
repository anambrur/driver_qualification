@extends('layouts.main-layout')

@section('title', 'All Subscriptions')

@section('content')
<div class="p-4 sm:p-6">

    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">All Subscriptions</h1>
            <p class="text-sm text-gray-500 mt-1">Manage and view all user subscriptions.</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-6">
        <form action="{{ route('admin.subscriptions.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search User</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Name or Email" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan</label>
                <select name="plan_id" id="plan_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Plans</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" {{ request('plan_id') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="trial" {{ request('status') === 'trial' ? 'selected' : '' }}>Trial</option>
                    <option value="grace" {{ request('status') === 'grace' ? 'selected' : '' }}>Grace Period</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                    Filter
                </button>
                <a href="{{ route('admin.subscriptions.index') }}" class="py-2 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-md transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Subscriptions Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($subscriptions as $sub)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $sub->user->name ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">{{ $sub->user->email ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $sub->plan->name ?? 'Deleted Plan' }}</div>
                                <div class="text-xs text-gray-400 capitalize">{{ $sub->plan->billing_cycle ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $colors = [
                                        'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                        'trial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                        'grace' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                        'expired' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                        'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        'suspended' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
                                    ];
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $colors[$sub->status] ?? 'bg-gray-100' }}">
                                    {{ $sub->stripe_status }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-xs text-gray-500">
                                    <span class="font-medium">Starts:</span> {{ $sub->created_at?->format('M d, Y') ?? 'N/A' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span class="font-medium">Ends:</span> 
                                    @if($sub->ends_at)
                                        {{ $sub->ends_at->format('M d, Y') }}
                                    @else
                                        Lifetime
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @if($sub->user)
                                    <a href="{{ route('admin.subscriptions.show', $sub->user) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm">Manage</a>
                                @else
                                    <span class="text-gray-400 text-sm">Unavailable</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No subscriptions found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($subscriptions->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
