@extends('layouts.main-layout')

@section('title', 'Upload Driver License')

@section('content')
    @php($hasFront = $driver_document?->license_front)
    @php($hasBack = $driver_document?->license_back)
    <x-application.step admin :edit="$isEditMode" :driver="$driver" :step="$currentStep"
        :title="$isEditMode ? 'Edit Driver License' : 'Upload Driver License'"
        :description="($isEditMode ? 'Update' : 'Upload').' front and back images of the driver\'s license (Step 2 of 10)'">
        <form id="step-form" action="{{ route('admin.driver.license.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver_id }}">
            <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

            <x-ui.card title="Driver License Upload">
                @if ($isEditMode)
                    <x-slot:action><x-ui.badge tone="info">Edit Mode - Step 2 of 10</x-ui.badge></x-slot:action>
                @endif
                <div class="grid gap-8 md:grid-cols-2">
                    <x-application.upload name="license_front" label="License Front Side" :required="! $isEditMode || ! $hasFront"
                        :button="$hasFront ? 'Replace Front Image' : 'Upload Front Image'" remove-label="Remove New Image"
                        :current="$hasFront ? route('admin.driver.file', [$driver->id, 'license_front']) : null" current-label="Front Image" current-alt="License Front" />
                    <x-application.upload name="license_back" label="License Back Side" :required="! $isEditMode || ! $hasBack"
                        :button="$hasBack ? 'Replace Back Image' : 'Upload Back Image'" remove-label="Remove New Image"
                        :current="$hasBack ? route('admin.driver.file', [$driver->id, 'license_back']) : null" current-label="Back Image" current-alt="License Back" />
                </div>

                @if ($driver->licenses->isNotEmpty())
                    <div class="mt-8 border-t pt-6">
                        <h3 class="mb-4 font-semibold">Current License Information</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($driver->licenses as $license)
                                <dl class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/40 p-4">
                                    <x-ui.detail label="License Number" :value="$license->license_number" mono />
                                    <x-ui.detail label="Class" :value="$license->class" />
                                    <x-ui.detail label="State" :value="$license->state" />
                                    <x-ui.detail label="Expires" :value="\Carbon\Carbon::parse($license->expires)->format('m/d/Y')" />
                                </dl>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-ui.card>

            <x-application.step-actions
                :back="$isEditMode ? route('admin.driver.edit', ['id' => $driver_id]) : route('admin.driver.create')"
                :back-label="$isEditMode ? 'Back to Driver Edit' : 'Back to Step 1'"
                :submit="$isEditMode ? 'Update & Continue to Step 3' : 'Save & Continue to Step 3'"
                :skip="$isEditMode ? 'Skip to Next Step' : null" />
        </form>
    </x-application.step>
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
        const form = document.getElementById('step-form');
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
