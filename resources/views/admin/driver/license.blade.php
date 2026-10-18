@extends('layouts.main-layout')

@section('title', 'Upload Driver License')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground dark:text-white/90">
                @if ($isEditMode)
                    Edit Driver License
                @else
                    Upload Driver License
                @endif
            </h1>
            <p class="text-muted-foreground">
                @if ($isEditMode)
                    Update front and back images of the driver's license (Step 2 of 10)
                @else
                    Upload front and back images of the driver's license (Step 2 of 10)
                @endif
            </p>
            <div class="mt-2 flex items-center text-sm text-muted-foreground">
                <x-ui.icon name="user" class="inline-block size-4 align-[-0.125em] mr-2" />
                <span>Driver: {{ $driver->first_name }} {{ $driver->last_name }}</span>
                @if ($driver->company)
                    <span class="mx-2">•</span>
                    <x-ui.icon name="building-2" class="inline-block size-4 align-[-0.125em] mr-2" />
                    <span>Company: {{ $driver->company->company_name }}</span>
                @endif
            </div>
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

                <form action="{{ route('admin.driver.license.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">
                    <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

                    <!-- License Upload Section -->
                    <div class="rounded-2xl border border-border bg-card dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-border">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                    Driver License Upload
                                </h3>
                                @if ($isEditMode)
                                    <span
                                        class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Edit Mode - Step 2 of 10
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                                <!-- License Front -->
                                <div>
                                    <label class="mb-3 block text-sm font-medium text-foreground">
                                        License Front Side
                                        @if (!$isEditMode || !$driver_document || !$driver_document->license_front)
                                            <span class="text-error-500">*</span>
                                        @endif
                                    </label>

                                    @if ($driver_document && $driver_document->license_front)
                                        <div class="mb-4">
                                            <p class="text-sm text-muted-foreground mb-2">Current Front Image:
                                            </p>
                                            <div class="relative group">
                                                <img src="{{ route('admin.driver.file', [$driver->id, 'license_front']) }}"
                                                    alt="License Front"
                                                    class="h-48 w-full object-contain rounded-lg border border-border">
                                                <div
                                                    class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-lg">
                                                    <a href="{{ route('admin.driver.file', [$driver->id, 'license_front']) }}"
                                                        target="_blank"
                                                        class="text-white bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded-md text-sm">
                                                        View Full Size
                                                    </a>
                                                </div>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Upload a new image to replace this one.
                                            </p>
                                        </div>
                                    @endif

                                    <div
                                        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-input border-dashed rounded-lg">
                                        <div class="space-y-1 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <svg class="mx-auto h-12 w-12 text-muted-foreground" stroke="currentColor"
                                                    fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                    <path
                                                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                                <div class="flex text-sm text-muted-foreground">
                                                    <label for="license_front"
                                                        class="relative cursor-pointer rounded-md bg-card font-medium text-primary focus-within:outline-hidden focus-within:ring-2 focus-within:ring-primary focus-within:ring-offset-2 hover:text-primary">
                                                        <span>
                                                            @if ($driver_document && $driver_document->license_front)
                                                                Replace Front Image
                                                            @else
                                                                Upload Front Image
                                                            @endif
                                                        </span>
                                                        <input id="license_front" name="license_front" type="file"
                                                            class="sr-only" accept="image/*"
                                                            @if (!$isEditMode || !$driver_document || !$driver_document->license_front) required @endif>
                                                    </label>
                                                </div>
                                                <p class="text-xs text-muted-foreground">PNG, JPG, JPEG up to 5MB
                                                </p>
                                            </div>
                                            <div id="license_front_preview" class="hidden mt-4">
                                                <img id="license_front_preview_img"
                                                    class="mx-auto h-32 object-contain rounded-lg border border-border">
                                                <button type="button" onclick="removePreview('license_front')"
                                                    class="mt-2 text-sm text-red-600 hover:text-red-500">
                                                    Remove New Image
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
                                    <label class="mb-3 block text-sm font-medium text-foreground">
                                        License Back Side
                                        @if (!$isEditMode || !$driver_document || !$driver_document->license_back)
                                            <span class="text-error-500">*</span>
                                        @endif
                                    </label>

                                    @if ($driver_document && $driver_document->license_back)
                                        <div class="mb-4">
                                            <p class="text-sm text-muted-foreground mb-2">Current Back Image:</p>
                                            <div class="relative group">
                                                <img src="{{ route('admin.driver.file', [$driver->id, 'license_back']) }}"
                                                    alt="License Back"
                                                    class="h-48 w-full object-contain rounded-lg border border-border">
                                                <div
                                                    class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-lg">
                                                    <a href="{{ route('admin.driver.file', [$driver->id, 'license_back']) }}"
                                                        target="_blank"
                                                        class="text-white bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded-md text-sm">
                                                        View Full Size
                                                    </a>
                                                </div>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Upload a new image to replace this one.
                                            </p>
                                        </div>
                                    @endif

                                    <div
                                        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-input border-dashed rounded-lg">
                                        <div class="space-y-1 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <svg class="mx-auto h-12 w-12 text-muted-foreground" stroke="currentColor"
                                                    fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                    <path
                                                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                                <div class="flex text-sm text-muted-foreground">
                                                    <label for="license_back"
                                                        class="relative cursor-pointer rounded-md bg-card font-medium text-primary focus-within:outline-hidden focus-within:ring-2 focus-within:ring-primary focus-within:ring-offset-2 hover:text-primary">
                                                        <span>
                                                            @if ($driver_document && $driver_document->license_back)
                                                                Replace Back Image
                                                            @else
                                                                Upload Back Image
                                                            @endif
                                                        </span>
                                                        <input id="license_back" name="license_back" type="file"
                                                            class="sr-only" accept="image/*"
                                                            @if (!$isEditMode || !$driver_document || !$driver_document->license_back) required @endif>
                                                    </label>
                                                </div>
                                                <p class="text-xs text-muted-foreground">PNG, JPG, JPEG up to
                                                    5MB</p>
                                            </div>
                                            <div id="license_back_preview" class="hidden mt-4">
                                                <img id="license_back_preview_img"
                                                    class="mx-auto h-32 object-contain rounded-lg border border-border">
                                                <button type="button" onclick="removePreview('license_back')"
                                                    class="mt-2 text-sm text-red-600 hover:text-red-500">
                                                    Remove New Image
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    @error('license_back')
                                        <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- License Information Section -->
                            @if ($driver->licenses->isNotEmpty())
                                <div class="mt-8 pt-6 border-t border-border">
                                    <h4 class="text-lg font-medium text-foreground dark:text-white/90 mb-4">Current License
                                        Information</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @foreach ($driver->licenses as $license)
                                            <div class="bg-muted/50 rounded-lg p-4">
                                                <div class="grid grid-cols-2 gap-2 text-sm">
                                                    <div class="font-medium text-muted-foreground">License
                                                        Number:</div>
                                                    <div class="text-foreground dark:text-white/90">
                                                        {{ $license->license_number }}</div>

                                                    <div class="font-medium text-muted-foreground">Class:</div>
                                                    <div class="text-foreground dark:text-white/90">{{ $license->class }}
                                                    </div>

                                                    <div class="font-medium text-muted-foreground">State:</div>
                                                    <div class="text-foreground dark:text-white/90">{{ $license->state }}
                                                    </div>

                                                    <div class="font-medium text-muted-foreground">Expires:
                                                    </div>
                                                    <div class="text-foreground dark:text-white/90">
                                                        {{ \Carbon\Carbon::parse($license->expires)->format('m/d/Y') }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-border">
                        @if ($isEditMode)
                            <a href="{{ route('admin.driver.edit', ['id' => $driver_id]) }}"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-foreground bg-card border border-input rounded-lg shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <x-ui.icon name="arrow-left" class="inline-block size-4 align-[-0.125em] mr-2" />
                                Back to Driver Edit
                            </a>
                        @else
                            <a href="{{ route('admin.driver.create') }}"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-foreground bg-card border border-input rounded-lg shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <x-ui.icon name="arrow-left" class="inline-block size-4 align-[-0.125em] mr-2" />
                                Back to Step 1
                            </a>
                        @endif

                        <div class="flex gap-4">
                            <button type="submit" name="action" value="save"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-primary border border-transparent rounded-lg shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <x-ui.icon name="save" class="inline-block size-4 align-[-0.125em] mr-2" />
                                {{ $isEditMode ? 'Update & Continue to Step 3' : 'Save & Continue to Step 3' }}
                            </button>

                            @if ($isEditMode)
                                <button type="submit" name="action" value="skip"
                                    class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-foreground bg-card border border-input rounded-lg shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Skip to Next Step
                                </button>
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
                ])

                <!-- Driver Info Card -->
                <div class="mt-6 rounded-lg border border-border bg-card dark:bg-white/[0.03] p-4">
                    <h3 class="font-medium text-foreground dark:text-white/90 mb-3">Driver Information</h3>
                    <div class="space-y-2">
                        <div class="flex items-center text-sm">
                            <span class="w-24 text-muted-foreground">Name:</span>
                            <span class="font-medium text-foreground dark:text-white/90">{{ $driver->first_name }}
                                {{ $driver->last_name }}</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="w-24 text-muted-foreground">Email:</span>
                            <span class="font-medium text-foreground dark:text-white/90">{{ $driver->email }}</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="w-24 text-muted-foreground">Phone:</span>
                            <span class="font-medium text-foreground dark:text-white/90">{{ $driver->main_phone }}</span>
                        </div>
                        @if ($driver->company)
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-muted-foreground">Company:</span>
                                <span
                                    class="font-medium text-foreground dark:text-white/90">{{ $driver->company->company_name }}</span>
                            </div>
                        @endif
                        <div class="flex items-center text-sm">
                            <span class="w-24 text-muted-foreground">Status:</span>
                            <span
                                class="font-medium {{ $driver->status == 'active' ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                                {{ ucfirst($driver->status) }}
                            </span>
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
            // File preview functionality
            setupFilePreview('license_front');
            setupFilePreview('license_back');
        });

        function setupFilePreview(inputId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(inputId + '_preview');
            const previewImg = document.getElementById(inputId + '_preview_img');

            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (previewImg) {
                                previewImg.src = e.target.result;
                                preview.classList.remove('hidden');
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                }
            });
        }

        function removePreview(inputId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(inputId + '_preview');

            input.value = '';
            preview.classList.add('hidden');
        }

        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const licenseFront = document.getElementById('license_front');
            const licenseBack = document.getElementById('license_back');

            // Check if we're in edit mode
            const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
            const hasExistingFront = {{ $driver_document && $driver_document->license_front ? 'true' : 'false' }};
            const hasExistingBack = {{ $driver_document && $driver_document->license_back ? 'true' : 'false' }};

            if (isEditMode) {
                // In edit mode, files are optional (can skip)
                // Only validate if new files are uploaded
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes

                if (licenseFront.files[0] && licenseFront.files[0].size > maxSize) {
                    e.preventDefault();
                    showAppAlert('License front image is too large. Maximum size is 5MB.');
                    return false;
                }

                if (licenseBack.files[0] && licenseBack.files[0].size > maxSize) {
                    e.preventDefault();
                    showAppAlert('License back image is too large. Maximum size is 5MB.');
                    return false;
                }
            } else {
                // In create mode, both files are required
                if (!licenseFront.files[0] || !licenseBack.files[0]) {
                    e.preventDefault();
                    showAppAlert('Please upload both front and back images of the license.');
                    return false;
                }

                // Validate file sizes
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes

                if (licenseFront.files[0].size > maxSize) {
                    e.preventDefault();
                    showAppAlert('License front image is too large. Maximum size is 5MB.');
                    return false;
                }

                if (licenseBack.files[0].size > maxSize) {
                    e.preventDefault();
                    showAppAlert('License back image is too large. Maximum size is 5MB.');
                    return false;
                }
            }
        });
    </script>
@endpush
