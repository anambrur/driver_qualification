@extends('layouts.application-form-layout')

@section('title', 'Medical Card Upload | DOT Driver Qualification')

@section('content')
    @php
        $card = $driverDocument?->medical_card;
    @endphp
    <x-application.step :company="$company" :driver="$driver" :step="3" title="Upload Medical Card"
        description="Upload the driver's medical card and its expiration date.">
        <form id="step-form" action="{{ route('public.application.store.step3', ['slug' => $company->slug]) }}" method="POST" enctype="multipart/form-data" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver->id }}">

            <x-ui.card title="Driver Medical Card">
                <div class="grid gap-6">
                    <x-application.upload name="medical_card" label="Medical Card" required pdf accept="image/*,.pdf" types="PNG, JPG, JPEG, PDF up to 5MB"
                        :button="$card ? 'Replace Medical Card' : 'Upload Medical Card'"
                        :current="$card ? route('public.application.file', [$company->slug, $driver->id, 'medical_card']) : null"
                        :current-image="$card && Str::endsWith($card, ['.jpg', '.jpeg', '.png', '.gif', '.webp'])" current-label="Medical Card" />

                    <x-ui.field label="Medical Certificate Expiration Date" name="medical_certificate_expiration_date" for="medical_certificate_expiration_date" required class="max-w-xs">
                        <x-ui.input type="date" id="medical_certificate_expiration_date" name="medical_certificate_expiration_date"
                            :value="$driver->medical_certificate_expiration_date?->toDateString()" />
                    </x-ui.field>
                </div>

                @if ($driver->medical_certificate_expiration_date)
                    @php
                        $daysUntilExpiry = \Carbon\Carbon::today()->diffInDays(\Carbon\Carbon::parse($driver->medical_certificate_expiration_date), false);
                        [$statusTone, $statusText] = match (true) {
                            $daysUntilExpiry > 30 => ['success', 'Valid'],
                            $daysUntilExpiry > 0 => ['warning', 'Expiring Soon ('.$daysUntilExpiry.' days)'],
                            default => ['danger', 'Expired'],
                        };
                    @endphp
                    <div class="mt-8 border-t pt-6">
                        <h3 class="mb-4 font-semibold">Medical Certificate Information</h3>
                        <dl class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/40 p-4">
                            <x-ui.detail label="Expiration Date" :value="\Carbon\Carbon::parse($driver->medical_certificate_expiration_date)->format('m/d/Y')" />
                            <x-ui.detail label="Status"><x-ui.badge :tone="$statusTone">{{ $statusText }}</x-ui.badge></x-ui.detail>
                        </dl>
                    </div>
                @endif
            </x-ui.card>

            <x-application.step-actions :back="route('public.application.step2', ['driver_id' => $driver->id, 'slug' => $company->slug])" back-label="Back to Step 2" submit="Save & Continue to Step 4" />
        </form>
    </x-application.step>
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
            const medicalCard = document.getElementById('medical_card');

            // Check if we're in edit mode
            const isEditMode = false;
            const hasExistingMedicalCard =
                {{ $driverDocument && $driverDocument->medical_card ? 'true' : 'false' }};

            if (isEditMode) {
                // In edit mode, file is optional
                // Only validate if new file is uploaded
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes

                if (medicalCard.files[0] && medicalCard.files[0].size > maxSize) {
                    e.preventDefault();
                    showAppAlert('Medical card file is too large. Maximum size is 5MB.');
                    return false;
                }
            } else {
                // In create mode, file is required
                if (!medicalCard.files[0]) {
                    e.preventDefault();
                    showAppAlert('Please upload a medical card file.');
                    return false;
                }

                // Validate file size
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                const file = medicalCard.files[0];

                if (file.size > maxSize) {
                    e.preventDefault();
                    showAppAlert(`File ${file.name} is too large. Maximum size is 5MB.`);
                    return false;
                }
            }
        });
    </script>
@endpush
