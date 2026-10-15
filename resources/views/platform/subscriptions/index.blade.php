@extends('layouts.platform')

@section('title', 'All subscriptions')

@php($input = 'h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">All subscriptions</h1>

    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <select name="status" class="{{ $input }}">
            <option value="">Any status</option>
            @foreach (['trialing', 'active', 'past_due', 'unpaid', 'canceled', 'incomplete', 'incomplete_expired'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
            @endforeach
        </select>
        <select name="plan_id" class="{{ $input }}">
            <option value="">Any plan</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}" @selected((int) request('plan_id') === $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="open" value="1" @checked(request()->boolean('open'))> Open only</label>
        <button class="rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:text-gray-300">Filter</button>
    </form>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        @include('platform.subscriptions.table')
    </div>
    <div class="mt-4">{{ $subscriptions->links() }}</div>
@endsection
