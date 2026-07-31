@extends('layouts.application-form-layout')

@section('title', 'Application Status | DOT Driver Qualification')

@section('content')
    @php
        $statusColors = [
            'In Progress' => 'bg-blue-100 text-blue-800',
            'Under Review' => 'bg-amber-100 text-amber-800',
            'Approved' => 'bg-green-100 text-green-800',
            'Not Approved' => 'bg-red-100 text-red-800',
            'Inactive' => 'bg-gray-100 text-gray-800',
        ];
        $badgeClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
    @endphp

    <div class="min-h-screen bg-gray-50 flex flex-col items-center p-4 md:p-8">
        <div class="w-full max-w-4xl lg:max-w-5xl xl:max-w-6xl mx-auto">
            <div class="mb-8 md:mb-12 bg-blue-950 p-4 rounded-lg flex items-center justify-between">
                <h3 class="text-2xl font-bold text-white mb-1">
                    {{ $company->company_name }}
                </h3>
                <p class="text-gray-200 text-sm">
                    © {{ now()->year }} {{ url('/') }}
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-10 border border-gray-200 text-center">
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-clipboard-list text-3xl"></i>
                </div>

                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Application Status</h2>
                <p class="text-gray-600 text-lg mb-8">
                    Here is the current status of your driver application.
                </p>

                <div class="mb-8 rounded-xl border border-gray-200 bg-gray-50 p-5 text-left max-w-xl mx-auto">
                    <dl class="space-y-3 text-sm md:text-base">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Applicant</dt>
                            <dd class="font-medium text-gray-800">
                                {{ $driver->first_name }} {{ $driver->last_name }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Phone</dt>
                            <dd class="font-medium text-gray-800">{{ $driver->main_phone }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Status</dt>
                            <dd>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $badgeClass }}">
                                    {{ $status }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Last Updated</dt>
                            <dd class="font-medium text-gray-800">
                                {{ optional($driver->updated_at)->format('M d, Y g:i A') }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="flex flex-col md:flex-row items-center justify-center gap-4">
                    <a href="{{ route('public.application.status', $company->slug) }}"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-300 w-full md:w-auto">
                        Check Another Application
                    </a>
                    <a href="{{ route('application.form', $company->slug) }}"
                        class="text-blue-600 hover:text-blue-800 transition-colors duration-300 font-medium">
                        Back to Application Home
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
