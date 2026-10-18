@extends('layouts.main-layout')

@section('title', 'Upload Medical Card')

@section('content')
    @php
        $card = $driver_document?->medical_card;
    @endphp
    <x-application.step admin :edit="$isEditMode" :driver="$driver" :step="$currentStep"
        :title="$isEditMode ? 'Edit Medical Card' : 'Upload Medical Card'"
        :description="$isEditMode ? 'Update the driver\'s medical card (Step 3 of 10)' : 'Upload the driver\'s medical card (Step 3 of 10)'">
        <form id="step-form" action="{{ route('admin.driver.medical.card.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver_id }}">
            <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

            <x-ui.card title="Driver Medical Card">
                @if ($isEditMode)
                    <x-slot:action><x-ui.badge tone="info">Edit Mode - Step 3 of 10</x-ui.badge></x-slot:action>
                @endif
                <div class="grid gap-6">
                    <x-application.upload name="medical_card" label="Medical Card" :required="! $isEditMode || ! $card" pdf accept="image/*,.pdf" types="PNG, JPG, JPEG, PDF up to 5MB"
                        :button="$card ? 'Replace Medical Card' : 'Upload Medical Card'"
                        :current="$card ? route('admin.driver.file', [$driver->id, 'medical_card']) : null"
                        :current-image="$card && Str::endsWith($card, ['.jpg', '.jpeg', '.png', '.gif', '.webp'])" current-label="Medical Card" current-title="Medical Card Document" />

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

            <x-application.step-actions
                :back="$isEditMode ? route('admin.driver.edit', ['id' => $driver_id]) : route('admin.driver.license', ['driver_id' => $driver_id])"
                :back-label="$isEditMode ? 'Back to Driver Edit' : 'Back to Step 2'"
                :submit="$isEditMode ? 'Update & Continue to Step 4' : 'Save & Continue to Step 4'"
                :skip="$isEditMode ? 'Skip to Next Step' : null" :skip-href="route('admin.driver.forfeiture', ['driver_id' => $driver_id, 'edit' => '1'])" />
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
            const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
            const hasExistingMedicalCard =
                {{ $driver_document && $driver_document->medical_card ? 'true' : 'false' }};

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
