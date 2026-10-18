@extends('layouts.application-form-layout')

@section('title', 'Forfeiture Document Upload | DOT Driver Qualification')

@section('content')
    @php($doc = $driverDocument?->forfeiture_document)
    <x-application.step :company="$company" :driver="$driver" :step="4" title="Upload Forfeiture Document"
        description="Upload the driver's forfeiture document.">
        <form id="step-form" action="{{ route('public.application.store.step4', ['slug' => $company->slug]) }}" method="POST" enctype="multipart/form-data" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver->id }}">
            <input type="hidden" name="from_edit" value="0">

            <x-ui.card title="Driver Forfeiture Document">
                <x-application.upload name="forfeiture_document" label="Forfeiture Document" required pdf accept="image/*,.pdf" types="PNG, JPG, JPEG, PDF up to 5MB"
                    :button="$doc ? 'Replace Forfeiture Document' : 'Upload Forfeiture Document'"
                    :current="$doc ? route('public.application.file', [$company->slug, $driver->id, 'forfeiture_document']) : null"
                    :current-image="$doc && Str::endsWith($doc, ['.jpg', '.jpeg', '.png', '.gif', '.webp'])" current-label="Forfeiture Document" />

                @if ($driver->forfeitures && $driver->forfeitures->isNotEmpty())
                    <div class="mt-8 border-t pt-6">
                        <h3 class="mb-4 font-semibold">Forfeiture Information</h3>
                        <div class="grid gap-4">
                            @foreach ($driver->forfeitures as $forfeiture)
                                <dl class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/40 p-4">
                                    <x-ui.detail label="License Denied" :value="ucfirst($forfeiture->denied_license)" />
                                    <x-ui.detail label="License Revoked" :value="ucfirst($forfeiture->license_revoked)" />
                                    @if ($forfeiture->forfeitures)
                                        <x-ui.detail label="Additional Details" :value="$forfeiture->forfeitures" class="col-span-2" />
                                    @endif
                                </dl>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-ui.card>

            <x-application.step-actions :back="route('public.application.step3', ['driver_id' => $driver->id, 'slug' => $company->slug])" back-label="Back to Step 3" submit="Save & Continue to Step 5" />
        </form>
    </x-application.step>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupFilePreview('forfeiture_document');
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
                            <div class="flex items-center gap-3 rounded-lg border bg-muted/40 p-4 text-left">
                                ${@json(svg('lucide-file-text', 'size-10 shrink-0 text-destructive', ['aria-hidden' => 'true'])->toHtml())}
                                <div class="grid gap-0.5">
                                    <p class="text-sm font-medium">${file.name}</p>
                                    <p class="text-xs text-muted-foreground">PDF Document</p>
                                    <p class="text-xs text-muted-foreground">${(file.size / 1024).toFixed(2)} KB</p>
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
        const form = document.getElementById('step-form');
        form.addEventListener('submit', function(e) {
            const forfeitureDocument = document.getElementById('forfeiture_document');

            // Check if we're in edit mode
            const isEditMode = false;
            const hasExistingDocument =
                {{ $driverDocument && $driverDocument->forfeiture_document ? 'true' : 'false' }};

            if (isEditMode) {
                // In edit mode, file is optional
                // Only validate if new file is uploaded
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes

                if (forfeitureDocument.files[0] && forfeitureDocument.files[0].size > maxSize) {
                    e.preventDefault();
                    showAppAlert('Forfeiture document is too large. Maximum size is 5MB.');
                    return false;
                }
            } else {
                // In create mode, file is required
                if (!forfeitureDocument.files[0]) {
                    e.preventDefault();
                    showAppAlert('Please upload a forfeiture document.');
                    return false;
                }

                // Validate file size
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                const file = forfeitureDocument.files[0];

                if (file.size > maxSize) {
                    e.preventDefault();
                    showAppAlert(`File ${file.name} is too large. Maximum size is 5MB.`);
                    return false;
                }
            }
        });
    </script>
@endpush
