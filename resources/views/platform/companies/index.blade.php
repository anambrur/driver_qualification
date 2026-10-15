@extends('layouts.platform')

@section('title', 'Companies')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Companies</h1>
        @can('platform.companies.manage')
            <a href="{{ route('platform.companies.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">New company</a>
        @endcan
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <input type="search" name="search" value="{{ $search }}" placeholder="Name or email"
            class="h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
        <select name="status" class="h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="">Any status</option>
            @foreach (\App\Services\Platform\PlatformCompanyService::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:text-gray-300">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                <tr><th class="px-4 py-3">Company</th><th class="px-4 py-3">Owner</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Created</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($companies as $company)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('platform.companies.show', $company) }}" class="font-medium text-brand-600 hover:underline">{{ $company->company_name }}</a></td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $company->owner?->name }}<br><span class="text-xs">{{ $company->owner?->email }}</span></td>
                        <td class="px-4 py-3 capitalize text-gray-700 dark:text-gray-300">{{ $company->status }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $company->created_at?->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No companies found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $companies->links() }}</div>
@endsection
