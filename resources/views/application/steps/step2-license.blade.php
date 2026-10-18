@extends('layouts.application-form-layout')

@section('title', 'Driver License Upload | DOT Driver Qualification')

@section('content')
    @php($hasFront = $driver_document?->license_front)
    @php($hasBack = $driver_document?->license_back)
    <x-application.step :company="$company" :driver="$driver" :step="2" title="Upload Driver License"
        description="Upload front and back images of the driver's license.">
        <form id="step-form" action="{{ route('public.application.store.step2', ['slug' => $company->slug]) }}" method="POST" enctype="multipart/form-data" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver->id }}">

            <x-ui.card title="Driver License Upload">
                <div class="grid gap-8 md:grid-cols-2">
                    <x-application.upload name="license_front" label="License Front Side" required
                        :button="$hasFront ? 'Replace Front Image' : 'Upload Front Image'" remove-label="Remove New Image"
                        :current="$hasFront ? route('public.application.file', [$company->slug, $driver->id, 'license_front']) : null" current-label="Front Image" />
                    <x-application.upload name="license_back" label="License Back Side" required
                        :button="$hasBack ? 'Replace Back Image' : 'Upload Back Image'" remove-label="Remove New Image"
                        :current="$hasBack ? route('public.application.file', [$company->slug, $driver->id, 'license_back']) : null" current-label="Back Image" />
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

            <x-application.step-actions :back="route('public.application.step1', ['slug' => $company->slug])" back-label="Back to Step 1" submit="Save & Continue to Step 3" />
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
                const isEditMode = false;
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
