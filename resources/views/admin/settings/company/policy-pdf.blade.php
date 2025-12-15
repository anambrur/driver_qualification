@extends('layouts.main-layout')

@section('title', 'Upload Policy PDFs')

@section('content')
    <div class="p-4 mx-auto max-w-6xl md:p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Upload Company Policy PDFs</h2>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Upload PDF files of your company policies</p>
        </div>

        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700 dark:text-green-400">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if ($errors->has('system_error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700 dark:text-red-400">{{ $errors->first('system_error') }}</p>
                </div>
            </div>
        @endif

        <form action="{{ route('admin.settings.company.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- Display existing PDFs if any -->
            @if (isset($existingPolicies) && count($existingPolicies) > 0)
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                    <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-300 mb-2">Existing Policy PDFs</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($existingPolicies as $policy)
                            @if ($policy->alcohol_drug_test_policy_pdf)
                                <div
                                    class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-pdf text-red-500 mr-3 text-xl"></i>
                                        <div>
                                            <p class="font-medium text-gray-800 dark:text-white/90">Alcohol & Drug Policy
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ basename($policy->alcohol_drug_test_policy_pdf) }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ asset('storage/' . $policy->alcohol_drug_test_policy_pdf) }}"
                                        target="_blank"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            @endif

                            @if ($policy->general_work_policy_pdf)
                                <div
                                    class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-pdf text-red-500 mr-3 text-xl"></i>
                                        <div>
                                            <p class="font-medium text-gray-800 dark:text-white/90">General Work Policy</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ basename($policy->general_work_policy_pdf) }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ asset('storage/' . $policy->general_work_policy_pdf) }}" target="_blank"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column - Alcohol & Drug Testing Policy -->
                <div class="space-y-6">
                    <div
                        class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <i class="fas fa-file-medical mr-2"></i>Alcohol & Drug Testing Policy
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                            <div>
                                <label for="alcohol_drug_test_policy_pdf"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Upload Alcohol & Drug Test Policy PDF
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="alcohol_drug_test_policy_pdf"
                                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-600 transition-colors duration-200">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fas fa-file-pdf mb-3 text-3xl text-red-500 dark:text-red-400"></i>
                                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="font-semibold">Click to upload</span> or drag and drop
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">PDF files only (MAX. 10MB)
                                            </p>
                                            <div id="alcohol_pdf_filename"
                                                class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300 hidden">
                                            </div>
                                        </div>
                                        <input id="alcohol_drug_test_policy_pdf" name="alcohol_drug_test_policy_pdf"
                                            type="file" class="hidden" accept=".pdf,application/pdf" />
                                    </label>
                                </div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-info-circle mr-1"></i>Upload a PDF file containing the Alcohol & Drug
                                    Testing Policy
                                </div>
                                @error('alcohol_drug_test_policy_pdf')
                                    <p class="mt-1 text-sm text-red-500 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - General Work Policy -->
                <div class="space-y-6">
                    <div
                        class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <i class="fas fa-briefcase mr-2"></i>General Work Policy
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                            <div>
                                <label for="general_work_policy_pdf"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Upload General Work Policy PDF
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="general_work_policy_pdf"
                                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-600 transition-colors duration-200">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fas fa-file-pdf mb-3 text-3xl text-red-500 dark:text-red-400"></i>
                                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="font-semibold">Click to upload</span> or drag and drop
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">PDF files only (MAX. 10MB)
                                            </p>
                                            <div id="general_pdf_filename"
                                                class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300 hidden">
                                            </div>
                                        </div>
                                        <input id="general_work_policy_pdf" name="general_work_policy_pdf" type="file"
                                            class="hidden" accept=".pdf,application/pdf" />
                                    </label>
                                </div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-info-circle mr-1"></i>Upload a PDF file containing the General Work
                                    Policy
                                </div>
                                @error('general_work_policy_pdf')
                                    <p class="mt-1 text-sm text-red-500 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-800">
                <a href="{{ route('admin.settings.company') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 transition-colors duration-200">
                    <i class="fas fa-save mr-2"></i>Upload Policies
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Alcohol & Drug Policy PDF upload
            const alcoholPdfInput = document.getElementById('alcohol_drug_test_policy_pdf');
            const alcoholFilenameDisplay = document.getElementById('alcohol_pdf_filename');

            alcoholPdfInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    if (file.type !== 'application/pdf') {
                        alert('Please upload only PDF files.');
                        this.value = '';
                        alcoholFilenameDisplay.classList.add('hidden');
                        return;
                    }

                    // Validate file size (10MB)
                    const maxSize = 10 * 1024 * 1024; // 10MB in bytes
                    if (file.size > maxSize) {
                        alert('File size must be less than 10MB.');
                        this.value = '';
                        alcoholFilenameDisplay.classList.add('hidden');
                        return;
                    }

                    // Display filename
                    alcoholFilenameDisplay.textContent = `Selected: ${file.name}`;
                    alcoholFilenameDisplay.classList.remove('hidden');
                    alcoholFilenameDisplay.classList.add('text-green-600', 'dark:text-green-400');
                } else {
                    alcoholFilenameDisplay.classList.add('hidden');
                }
            });

            // General Work Policy PDF upload
            const generalPdfInput = document.getElementById('general_work_policy_pdf');
            const generalFilenameDisplay = document.getElementById('general_pdf_filename');

            generalPdfInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    if (file.type !== 'application/pdf') {
                        alert('Please upload only PDF files.');
                        this.value = '';
                        generalFilenameDisplay.classList.add('hidden');
                        return;
                    }

                    // Validate file size (10MB)
                    const maxSize = 10 * 1024 * 1024; // 10MB in bytes
                    if (file.size > maxSize) {
                        alert('File size must be less than 10MB.');
                        this.value = '';
                        generalFilenameDisplay.classList.add('hidden');
                        return;
                    }

                    // Display filename
                    generalFilenameDisplay.textContent = `Selected: ${file.name}`;
                    generalFilenameDisplay.classList.remove('hidden');
                    generalFilenameDisplay.classList.add('text-green-600', 'dark:text-green-400');
                } else {
                    generalFilenameDisplay.classList.add('hidden');
                }
            });

            // Drag and drop functionality
            const dropZones = document.querySelectorAll('label[for*="policy_pdf"]');

            dropZones.forEach(zone => {
                zone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('border-brand-500', 'bg-brand-50', 'dark:bg-brand-900/10');
                });

                zone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-brand-500', 'bg-brand-50',
                    'dark:bg-brand-900/10');
                });

                zone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-brand-500', 'bg-brand-50',
                    'dark:bg-brand-900/10');

                    const files = e.dataTransfer.files;
                    const input = this.querySelector('input[type="file"]');

                    if (files.length > 0) {
                        input.files = files;
                        input.dispatchEvent(new Event('change'));
                    }
                });
            });
        });
    </script>
@endpush
