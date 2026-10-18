@extends('layouts.main-layout')

@section('title', 'Upload Policy PDFs')

@section('content')
    <div class="p-4 mx-auto max-w-6xl md:p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-foreground dark:text-white/90">Upload Company Policy PDFs</h2>
            <p class="text-muted-foreground mt-2">Your company's policies. Until you upload one, your drivers and applicants see the platform default.</p>
        </div>

        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
                <div class="flex items-center">
                    <x-ui.icon name="circle-check" class="inline-block size-4 align-[-0.125em] text-green-500 mr-3" />
                    <p class="text-green-700 dark:text-green-400">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if ($errors->has('system_error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-center">
                    <x-ui.icon name="circle-alert" class="inline-block size-4 align-[-0.125em] text-red-500 mr-3" />
                    <p class="text-red-700 dark:text-red-400">{{ $errors->first('system_error') }}</p>
                </div>
            </div>
        @endif

        <form action="{{ route('admin.settings.policy.pdf.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- Display existing PDFs if any -->
            @if (isset($existingPolicies) && count($existingPolicies) > 0)
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                    <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-300 mb-2">Existing Policy PDFs</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($existingPolicies as $policy)
                            @if ($policy->alcohol_drug_test_policy_file_id)
                                <div
                                    class="flex items-center justify-between p-3 bg-card rounded-lg border border-border">
                                    <div class="flex items-center">
                                        <x-ui.icon name="file-text" class="inline-block size-5 align-[-0.125em] text-red-500 mr-3" />
                                        <div>
                                            <p class="font-medium text-foreground dark:text-white/90">Alcohol & Drug Policy
                                            </p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ $policy->fileFor('alcohol_drug_test')?->original_name }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ route('admin.settings.policy.pdf.file', 'alcohol_drug_test') }}"
                                        target="_blank"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        <x-ui.icon name="eye" class="inline-block size-4 align-[-0.125em]" /> View
                                    </a>
                                </div>
                            @endif

                            @if ($policy->general_work_policy_file_id)
                                <div
                                    class="flex items-center justify-between p-3 bg-card rounded-lg border border-border">
                                    <div class="flex items-center">
                                        <x-ui.icon name="file-text" class="inline-block size-5 align-[-0.125em] text-red-500 mr-3" />
                                        <div>
                                            <p class="font-medium text-foreground dark:text-white/90">General Work Policy</p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ $policy->fileFor('general_work')?->original_name }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ route('admin.settings.policy.pdf.file', 'general_work') }}" target="_blank"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        <x-ui.icon name="eye" class="inline-block size-4 align-[-0.125em]" /> View
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
                        class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                <x-ui.icon name="file-heart" class="inline-block size-4 align-[-0.125em] mr-2" />Alcohol & Drug Testing Policy
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-border p-5 sm:p-6">
                            <div>
                                <label for="alcohol_drug_test_policy_pdf"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Upload Alcohol & Drug Test Policy PDF
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="alcohol_drug_test_policy_pdf"
                                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-input border-dashed rounded-lg cursor-pointer bg-muted/50 hover:bg-muted transition-colors duration-200">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <x-ui.icon name="file-text" class="inline-block size-8 align-[-0.125em] mb-3 text-red-500 dark:text-red-400" />
                                            <p class="mb-2 text-sm text-muted-foreground">
                                                <span class="font-semibold">Click to upload</span> or drag and drop
                                            </p>
                                            <p class="text-xs text-muted-foreground">PDF files only (MAX. 10MB)
                                            </p>
                                            <div id="alcohol_pdf_filename"
                                                class="mt-2 text-sm font-medium text-foreground hidden">
                                            </div>
                                        </div>
                                        <input id="alcohol_drug_test_policy_pdf" name="alcohol_drug_test_policy_pdf"
                                            type="file" class="hidden" accept=".pdf,application/pdf" />
                                    </label>
                                </div>
                                <div class="mt-2 text-xs text-muted-foreground">
                                    <x-ui.icon name="info" class="inline-block size-4 align-[-0.125em] mr-1" />Upload a PDF file containing the Alcohol & Drug
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
                        class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                <x-ui.icon name="briefcase" class="inline-block size-4 align-[-0.125em] mr-2" />General Work Policy
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-border p-5 sm:p-6">
                            <div>
                                <label for="general_work_policy_pdf"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Upload General Work Policy PDF
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="general_work_policy_pdf"
                                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-input border-dashed rounded-lg cursor-pointer bg-muted/50 hover:bg-muted transition-colors duration-200">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <x-ui.icon name="file-text" class="inline-block size-8 align-[-0.125em] mb-3 text-red-500 dark:text-red-400" />
                                            <p class="mb-2 text-sm text-muted-foreground">
                                                <span class="font-semibold">Click to upload</span> or drag and drop
                                            </p>
                                            <p class="text-xs text-muted-foreground">PDF files only (MAX. 10MB)
                                            </p>
                                            <div id="general_pdf_filename"
                                                class="mt-2 text-sm font-medium text-foreground hidden">
                                            </div>
                                        </div>
                                        <input id="general_work_policy_pdf" name="general_work_policy_pdf" type="file"
                                            class="hidden" accept=".pdf,application/pdf" />
                                    </label>
                                </div>
                                <div class="mt-2 text-xs text-muted-foreground">
                                    <x-ui.icon name="info" class="inline-block size-4 align-[-0.125em] mr-1" />Upload a PDF file containing the General Work
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
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-border">
                <a href="{{ route('admin.dashboard') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-input bg-card px-4 py-2.5 text-sm font-medium text-foreground shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2 transition-colors duration-200">
                    <x-ui.icon name="x" class="inline-block size-4 align-[-0.125em] mr-2" />Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2 transition-colors duration-200">
                    <x-ui.icon name="save" class="inline-block size-4 align-[-0.125em] mr-2" />Upload Policies
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
                    this.classList.add('border-primary', 'bg-primary/10');
                });

                zone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-primary', 'bg-primary/10');
                });

                zone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('border-primary', 'bg-primary/10');

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
