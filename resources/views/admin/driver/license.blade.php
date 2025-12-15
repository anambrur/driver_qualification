@extends('layouts.main-layout')

@section('title', 'Upload Driver License')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Upload Driver License</h1>
            <p class="text-gray-600 dark:text-gray-400">Upload front and back images of the driver's license</p>
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

                <form action="{{ route('admin.driver.license.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">

                    <!-- License Upload Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Driver License Upload
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                                <!-- License Front -->
                                <div>
                                    <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        License Front Side <span class="text-error-500">*</span>
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
                                                    <label for="license_front"
                                                        class="relative cursor-pointer rounded-md bg-white font-medium text-brand-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-2 hover:text-brand-500 dark:bg-gray-900">
                                                        <span>Upload front image</span>
                                                        <input id="license_front" name="license_front" type="file"
                                                            class="sr-only" accept="image/*" required>
                                                    </label>
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG up to 5MB
                                                </p>
                                            </div>
                                            <div id="license_front_preview" class="hidden mt-4">
                                                <img id="license_front_preview_img"
                                                    class="mx-auto h-32 object-contain rounded-lg border border-gray-200 dark:border-gray-700">
                                                <button type="button" onclick="removePreview('license_front')"
                                                    class="mt-2 text-sm text-red-600 hover:text-red-500">
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    @error('license_front')
                                        <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- License Back -->
                                <div>
                                    <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        License Back Side <span class="text-error-500">*</span>
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
                                                    <label for="license_back"
                                                        class="relative cursor-pointer rounded-md bg-white font-medium text-brand-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-2 hover:text-brand-500 dark:bg-gray-900">
                                                        <span>Upload back image</span>
                                                        <input id="license_back" name="license_back" type="file"
                                                            class="sr-only" accept="image/*" required>
                                                    </label>
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG up to 5MB
                                                </p>
                                            </div>
                                            <div id="license_back_preview" class="hidden mt-4">
                                                <img id="license_back_preview_img"
                                                    class="mx-auto h-32 object-contain rounded-lg border border-gray-200 dark:border-gray-700">
                                                <button type="button" onclick="removePreview('license_back')"
                                                    class="mt-2 text-sm text-red-600 hover:text-red-500">
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    @error('license_back')
                                        <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                        <a href="{{ route('admin.driver.create') }}"
                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Back to Drivers
                        </a>

                        <div class="flex gap-4">
                            <button type="submit" name="action" value="submit"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                Save & Continue
                            </button>
                        </div>
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
            // File preview functionality
            setupFilePreview('license_front');
            setupFilePreview('license_back');
            setupFilePreview('medical_card');
            setupMultipleFilesPreview('other_documents');
        });

        function setupFilePreview(inputId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(inputId + '_preview');
            const previewImg = document.getElementById(inputId + '_preview_img');
            const previewContent = document.getElementById(inputId + '_preview_content');

            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (previewImg) {
                                previewImg.src = e.target.result;
                                preview.classList.remove('hidden');
                            } else if (previewContent) {
                                previewContent.innerHTML = `
                                <div class="flex items-center justify-center">
                                    <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">${file.name}</span>
                                </div>
                            `;
                                preview.classList.remove('hidden');
                            }
                        };
                        reader.readAsDataURL(file);
                    } else if (file.type === 'application/pdf') {
                        previewContent.innerHTML = `
                        <div class="flex items-center justify-center">
                            <svg class="h-8 w-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">${file.name}</span>
                        </div>
                    `;
                        preview.classList.remove('hidden');
                    }
                }
            });
        }

        function setupMultipleFilesPreview(inputId) {
            const input = document.getElementById(inputId);
            const previewContainer = document.getElementById(inputId + '_preview');

            input.addEventListener('change', function(e) {
                const files = e.target.files;
                previewContainer.innerHTML = '';

                if (files.length > 0) {
                    previewContainer.classList.remove('hidden');

                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        const fileElement = document.createElement('div');
                        fileElement.className =
                            'flex items-center justify-between p-2 bg-gray-50 rounded-lg dark:bg-gray-800';

                        fileElement.innerHTML = `
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">${file.name}</span>
                        </div>
                        <button type="button" onclick="removeFilePreview(this)" class="text-red-600 hover:text-red-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    `;

                        previewContainer.appendChild(fileElement);
                    }
                } else {
                    previewContainer.classList.add('hidden');
                }
            });
        }

        function removePreview(inputId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(inputId + '_preview');

            input.value = '';
            preview.classList.add('hidden');
        }

        function removeFilePreview(button) {
            const fileElement = button.closest('div');
            fileElement.remove();

            // Update the file input (this is a simplified approach)
            const previewContainer = document.getElementById('other_documents_preview');
            if (previewContainer.children.length === 0) {
                previewContainer.classList.add('hidden');
            }
        }

        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const licenseFront = document.getElementById('license_front');
            const licenseBack = document.getElementById('license_back');

            if (!licenseFront.files[0] || !licenseBack.files[0]) {
                e.preventDefault();
                alert('Please upload both front and back images of the license.');
                return false;
            }

            // Validate file sizes (5MB limit)
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            const files = [licenseFront.files[0], licenseBack.files[0]];

            for (let file of files) {
                if (file && file.size > maxSize) {
                    e.preventDefault();
                    alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                    return false;
                }
            }
        });
    </script>
@endpush
