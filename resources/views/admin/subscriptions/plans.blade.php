@extends('layouts.main-layout')

@section('title', 'Manage Plans')

@section('content')
<div class="p-4 sm:p-6">

    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Subscription Plans</h1>
            <p class="text-sm text-gray-500 mt-1">Manage pricing tiers, features, and plan statuses.</p>
        </div>
        <a href="{{ route('admin.plans.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors flex items-center gap-2">
            <span>+ Create New Plan</span>
        </a>
    </div>

    

    {{-- Plans Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider w-16">Sort</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Plan Name</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Pricing</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Cycle</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">Subscribers</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($plans as $plan)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-4 py-4 text-gray-500">{{ $plan->sort_order }}</td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    {{ $plan->name }}
                                    @if($plan->is_featured)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-yellow-100 text-yellow-800 border border-yellow-200">PROMO</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 truncate max-w-xs">{{ $plan->description }}</div>
                            </td>
                            <td class="px-4 py-4 font-bold text-gray-900 dark:text-white">
                                @if($plan->price == 0)
                                    <span class="text-green-600">Free</span>
                                @else
                                    ${{ number_format($plan->price, 2) }} <span class="text-xs font-normal text-gray-500 uppercase">{{ $plan->currency }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 capitalize text-gray-600 dark:text-gray-300">
                                {{ $plan->billing_cycle }}
                                <div class="text-xs text-gray-500 mt-0.5">{{ $plan->duration_days }} days</div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-50 text-blue-700 font-bold border border-blue-100">
                                    {{ $plan->subscriptions_count }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <form action="{{ route('admin.plans.toggle', $plan) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $plan->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200' }} transition-colors">
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.plans.edit', $plan) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm">Edit</a>
                                    
                                    @if($plan->subscriptions_count == 0)
                                        <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this plan?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-sm border-l border-gray-300 pl-2 ml-1">Delete</button>
                                        </form>
                                    @else
                                        <button title="Cannot delete: Plan has active subscribers." class="text-gray-400 font-medium text-sm border-l border-gray-200 pl-2 ml-1 cursor-not-allowed">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No tracking plans found. <a href="{{ route('admin.plans.create') }}" class="text-blue-600 hover:underline">Create your first plan.</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
