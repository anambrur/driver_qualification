@extends('layouts.main-layout')

@section('title', 'Upload Medical Card')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Upload Medical Card</h1>
            <p class="text-gray-600 dark:text-gray-400">Upload images of the driver's medical card</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9">
                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd" />
                            </svg>
                            <h3 class="text-red-800 dark:text-red-200 font-medium">Please fix the following errors:</h3>
                        </div>
                        <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div
                        class="mb-6 p-4 rounded-lg bg-green-50 border border-green-200 dark:bg-green-900/20 dark:border-green-800">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <h3 class="text-green-800 dark:text-green-200 font-medium">{{ session('success') }}</h3>
                        </div>
                    </div>
                @endif

                <form action="{{ route('admin.driver.medical.card.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">

                    <!-- Medical Card Upload Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Driver Medical Card
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-6">
                                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Medical Card
                                </label>
                                <div
                                    class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg dark:border-gray-600">
                                    <div class="space-y-1 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor"
                                                fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path
                                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                                <label for="medical_card"
                                                    class="relative cursor-pointer rounded-md bg-white font-medium text-brand-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-2 hover:text-brand-500 dark:bg-gray-900">
                                                    <span>Upload medical card</span>
                                                    <input id="medical_card" name="medical_card" type="file"
                                                        class="sr-only" accept="image/*,.pdf" required>
                                                </label>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG, PDF up to
                                                5MB</p>
                                        </div>

                                        <!-- Preview container for images -->
                                        <div id="medical_card_preview" class="hidden mt-4">
                                            <img id="medical_card_preview_img"
                                                class="mx-auto h-32 object-contain rounded-lg border border-gray-200 dark:border-gray-700">
                                            <button type="button" onclick="removePreview('medical_card')"
                                                class="mt-2 text-sm text-red-600 hover:text-red-500">
                                                Remove
                                            </button>
                                        </div>

                                        <!-- Preview container for PDFs -->
                                        <div id="medical_card_pdf_preview" class="hidden mt-4">
                                            <div id="medical_card_pdf_preview_content" class="text-center">
                                                <!-- PDF preview will be shown here -->
                                            </div>
                                            <button type="button" onclick="removePreview('medical_card')"
                                                class="mt-2 text-sm text-red-600 hover:text-red-500">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @error('medical_card')
                                    <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                        <a href="{{ route('admin.driver.license', $driver_id) }}"
                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium 
                            text-gray-700 bg-gray-100 border border-gray-300 rounded-lg 
                            hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 
                            focus:ring-gray-400 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                            Back
                        </a>

                        <a href="{{ route('admin.driver.forfeiture', $driver_id) }}"
                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium 
                            text-yellow-700 bg-yellow-100 border border-yellow-300 rounded-lg 
                            hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 
                            focus:ring-yellow-400 dark:bg-yellow-800 dark:text-yellow-200 dark:border-yellow-700 dark:hover:bg-yellow-700">
                            Skip
                        </a>


                        <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                            Save & Continue
                        </button>
                    </div>
                </form>
            </div>
            <div class="md:col-span-3">
                @include('components.progress-bar', ['currentStep' => $currentStep])
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

            if (!medicalCard.files[0]) {
                e.preventDefault();
                alert('Please upload a medical card file.');
                return false;
            }

            // Validate file size (5MB limit)
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            const file = medicalCard.files[0];

            if (file && file.size > maxSize) {
                e.preventDefault();
                alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                return false;
            }
        });
    </script>
@endpush
