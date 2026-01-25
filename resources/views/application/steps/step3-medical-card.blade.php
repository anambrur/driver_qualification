@extends('layouts.application-form-layout')

@section('title', 'Medical Card | DOT Driver Qualification')

@section('content')
    <div class="min-h-screen bg-gray-50 flex flex-col items-center p-4 md:p-8">
        <div class="w-full max-w-4xl lg:max-w-5xl xl:max-w-6xl mx-auto">
            <!-- Header Section -->
            <div class="mb-8 md:mb-12 bg-blue-950 p-4 rounded-lg flex items-center justify-between">
                <h3 class="text-2xl font-bold text-white mb-1">
                    {{ $company->company_name }}
                </h3>
                <p class="text-gray-200 text-sm">
                    © {{ now()->year }} {{ url('/') }}
                </p>
            </div>

            <!-- Main Card -->
            <div class="p-4 mx-auto max-w-7xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                        @if ($isEditMode)
                            Edit Medical Card
                        @else
                            Upload Medical Card
                        @endif
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        @if ($isEditMode)
                            Update the driver's medical card (Step 3 of 10)
                        @else
                            Upload the driver's medical card (Step 3 of 10)
                        @endif
                    </p>
                    <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <i class="fas fa-user mr-2"></i>
                        <span>Driver: {{ $driver->first_name }} {{ $driver->last_name }}</span>
                        @if ($driver->company)
                            <span class="mx-2">•</span>
                            <i class="fas fa-building mr-2"></i>
                            <span>Company: {{ $driver->company->company_name }}</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-9">
                        @if ($errors->any())
                            <div
                                class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <h3 class="text-red-800 dark:text-red-200 font-medium">Please fix the following errors:
                                    </h3>
                                </div>
                                <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('public.application.store.step3', ['slug' => $company->slug]) }}"
                            method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="driver_id" value="{{ $driver->id }}">
                            <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

                            <!-- Medical Card Upload Section -->
                            <div
                                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                            Driver Medical Card
                                        </h3>
                                        @if ($isEditMode)
                                            <span
                                                class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Edit Mode - Step 3 of 10
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <div class="mb-6">
                                        <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Medical Card
                                            @if (!$isEditMode || !$driverDocument || !$driverDocument->medical_card)
                                                <span class="text-error-500">*</span>
                                            @endif
                                        </label>

                                        @if ($driverDocument && $driverDocument->medical_card)
                                            <div class="mb-4">
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Current Medical
                                                    Card:</p>
                                                @if (Str::endsWith($driverDocument->medical_card, ['.jpg', '.jpeg', '.png', '.gif', '.webp']))
                                                    <div class="relative group">
                                                        <img src="{{ Storage::url($driverDocument->medical_card) }}"
                                                            alt="Medical Card"
                                                            class="h-48 w-full object-contain rounded-lg border border-gray-200 dark:border-gray-700">
                                                        <div
                                                            class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-lg">
                                                            <a href="{{ Storage::url($driverDocument->medical_card) }}"
                                                                target="_blank"
                                                                class="text-white bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded-md text-sm">
                                                                View Full Size
                                                            </a>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div
                                                        class="flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                                        <svg class="h-12 w-12 text-red-400" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                            </path>
                                                        </svg>
                                                        <div class="ml-3 text-left">
                                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                Medical Card Document
                                                            </p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">PDF Document
                                                            </p>
                                                            <a href="{{ Storage::url($driverDocument->medical_card) }}"
                                                                target="_blank"
                                                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                                                                View Document
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endif
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Upload a new file to replace this one.
                                                </p>
                                            </div>
                                        @endif

                                        <div
                                            class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg dark:border-gray-600">
                                            <div class="space-y-1 text-center">
                                                <div class="flex flex-col items-center justify-center">
                                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor"
                                                        fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                        <path
                                                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                                            stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round" />
                                                    </svg>
                                                    <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                                        <label for="medical_card"
                                                            class="relative cursor-pointer rounded-md bg-white font-medium text-brand-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-2 hover:text-brand-500 dark:bg-gray-900">
                                                            <span>
                                                                @if ($driverDocument && $driverDocument->medical_card)
                                                                    Replace Medical Card
                                                                @else
                                                                    Upload Medical Card
                                                                @endif
                                                            </span>
                                                            <input id="medical_card" name="medical_card" type="file"
                                                                class="sr-only" accept="image/*,.pdf"
                                                                @if (!$isEditMode || !$driverDocument || !$driverDocument->medical_card) required @endif>
                                                        </label>
                                                    </div>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG, PDF
                                                        up to
                                                        5MB</p>
                                                </div>

                                                <!-- Preview container for images -->
                                                <div id="medical_card_preview" class="hidden mt-4">
                                                    <img id="medical_card_preview_img"
                                                        class="mx-auto h-32 object-contain rounded-lg border border-gray-200 dark:border-gray-700">
                                                    <button type="button" onclick="removePreview('medical_card')"
                                                        class="mt-2 text-sm text-red-600 hover:text-red-500">
                                                        Remove New File
                                                    </button>
                                                </div>

                                                <!-- Preview container for PDFs -->
                                                <div id="medical_card_pdf_preview" class="hidden mt-4">
                                                    <div id="medical_card_pdf_preview_content" class="text-center">
                                                        <!-- PDF preview will be shown here -->
                                                    </div>
                                                    <button type="button" onclick="removePreview('medical_card')"
                                                        class="mt-2 text-sm text-red-600 hover:text-red-500">
                                                        Remove New File
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        @error('medical_card')
                                            <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Medical Certificate Expiration Date -->
                                    @if ($driver->medical_certificate_expiration_date)
                                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                                            <h4 class="text-lg font-medium text-gray-800 dark:text-white/90 mb-4">Medical
                                                Certificate Information</h4>
                                            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                                                <div class="grid grid-cols-2 gap-2 text-sm">
                                                    <div class="font-medium text-gray-600 dark:text-gray-400">Expiration
                                                        Date:
                                                    </div>
                                                    <div class="text-gray-800 dark:text-white/90">
                                                        {{ \Carbon\Carbon::parse($driver->medical_certificate_expiration_date)->format('m/d/Y') }}
                                                    </div>

                                                    <div class="font-medium text-gray-600 dark:text-gray-400">Status:</div>
                                                    <div class="text-gray-800 dark:text-white/90">
                                                        @php
                                                            $expirationDate = \Carbon\Carbon::parse(
                                                                $driver->medical_certificate_expiration_date,
                                                            );
                                                            $today = \Carbon\Carbon::today();
                                                            $daysUntilExpiry = $today->diffInDays(
                                                                $expirationDate,
                                                                false,
                                                            );

                                                            if ($daysUntilExpiry > 30) {
                                                                $statusClass = 'text-green-600 dark:text-green-400';
                                                                $statusText = 'Valid';
                                                            } elseif ($daysUntilExpiry > 0) {
                                                                $statusClass = 'text-yellow-600 dark:text-yellow-400';
                                                                $statusText =
                                                                    'Expiring Soon (' . $daysUntilExpiry . ' days)';
                                                            } else {
                                                                $statusClass = 'text-red-600 dark:text-red-400';
                                                                $statusText = 'Expired';
                                                            }
                                                        @endphp
                                                        <span class="{{ $statusClass }} font-medium">
                                                            {{ $statusText }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div
                                class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                                @if ($isEditMode)
                                    <a href="{{ route('public.application.step2', ['id' => $driver_id]) }}"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Driver Edit
                                    </a>
                                @else
                                    <a href="{{ route('public.application.step2', ['driver_id' => $driver->id, 'slug' => $company->slug]) }}"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Step 2
                                    </a>
                                @endif

                                <div class="flex gap-4">
                                    <button type="submit" name="action" value="save"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        <i class="fas fa-save mr-2"></i>
                                        {{ $isEditMode ? 'Update & Continue to Step 4' : 'Save & Continue to Step 4' }}
                                    </button>

                                    @if ($isEditMode)
                                        <a href="{{ route('admin.driver.forfeiture', ['driver_id' => $driver_id, 'edit' => '1']) }}"
                                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                            Skip to Next Step
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Progress Bar Sidebar -->
                    <div class="md:col-span-3">
                        @include('components.progress-bar', [
                            'currentStep' => $currentStep,
                            'totalSteps' => 10,
                            'isEditMode' => $isEditMode,
                            'driver_id' => $driver->id,
                        ])

                        <!-- Driver Info Card -->
                        <div
                            class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4">
                            <h3 class="font-medium text-gray-800 dark:text-white/90 mb-3">Driver Information</h3>
                            <div class="space-y-2">
                                <div class="flex items-center text-sm">
                                    <span class="w-24 text-gray-500 dark:text-gray-400">Name:</span>
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $driver->first_name }}
                                        {{ $driver->last_name }}</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="w-24 text-gray-500 dark:text-gray-400">Email:</span>
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $driver->email }}</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="w-24 text-gray-500 dark:text-gray-400">Phone:</span>
                                    <span
                                        class="font-medium text-gray-800 dark:text-white/90">{{ $driver->main_phone }}</span>
                                </div>
                                @if ($driver->company)
                                    <div class="flex items-center text-sm">
                                        <span class="w-24 text-gray-500 dark:text-gray-400">Company:</span>
                                        <span
                                            class="font-medium text-gray-800 dark:text-white/90">{{ $driver->company->company_name }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center text-sm">
                                    <span class="w-24 text-gray-500 dark:text-gray-400">Status:</span>
                                    <span
                                        class="font-medium {{ $driver->status == 'active' ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                                        {{ ucfirst($driver->status) }}
                                    </span>
                                </div>
                                @if ($driver->medical_certificate_expiration_date)
                                    <div class="flex items-center text-sm">
                                        <span class="w-24 text-gray-500 dark:text-gray-400">Medical Exp.:</span>
                                        <span
                                            class="font-medium {{ \Carbon\Carbon::parse($driver->medical_certificate_expiration_date)->isFuture() ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ \Carbon\Carbon::parse($driver->medical_certificate_expiration_date)->format('m/d/Y') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupFilePreview('medical_card');
        });

        function setupFilePreview(inputId) {
            const input = document.getElementById(inputId);
            const imagePreview = document.getElementById(inputId + '_preview');
            const pdfPreview = document.getElementById(inputId + '_pdf_preview');
            const previewImg = document.getElementById(inputId + '_preview_img');
            const pdfPreviewContent = document.getElementById(inputId + '_pdf_preview_content');

            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Hide both previews first
                    imagePreview.classList.add('hidden');
                    pdfPreview.classList.add('hidden');

                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            imagePreview.classList.remove('hidden');
                        };
                        reader.readAsDataURL(file);
                    } else if (file.type === 'application/pdf') {
                        pdfPreviewContent.innerHTML = `
                            <div class="flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <svg class="h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <div class="ml-3 text-left">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">${file.name}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">PDF Document</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">${(file.size / 1024).toFixed(2)} KB</p>
                                </div>
                            </div>
                        `;
                        pdfPreview.classList.remove('hidden');
                    }
                }
            });
        }

        function removePreview(inputId) {
            const input = document.getElementById(inputId);
            const imagePreview = document.getElementById(inputId + '_preview');
            const pdfPreview = document.getElementById(inputId + '_pdf_preview');

            input.value = '';
            imagePreview.classList.add('hidden');
            pdfPreview.classList.add('hidden');
        }

        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const medicalCard = document.getElementById('medical_card');

            // Check if we're in edit mode
            const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
            const hasExistingMedicalCard =
                {{ $driverDocument && $driverDocument->medical_card ? 'true' : 'false' }};

            if (isEditMode) {
                // In edit mode, file is optional
                // Only validate if new file is uploaded
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes

                if (medicalCard.files[0] && medicalCard.files[0].size > maxSize) {
                    e.preventDefault();
                    alert('Medical card file is too large. Maximum size is 5MB.');
                    return false;
                }
            } else {
                // In create mode, file is required
                if (!medicalCard.files[0]) {
                    e.preventDefault();
                    alert('Please upload a medical card file.');
                    return false;
                }

                // Validate file size
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                const file = medicalCard.files[0];

                if (file.size > maxSize) {
                    e.preventDefault();
                    alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                    return false;
                }
            }
        });
    </script>
@endpush
