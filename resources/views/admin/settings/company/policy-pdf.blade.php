@extends('layouts.main-layout')

@section('title', 'Upload Policy PDFs')

@php
    // field name => [card title, icon, upload label, filename element id, help text]
    $uploads = [
        'alcohol_drug_test_policy_pdf' => ['Alcohol & Drug Testing Policy', 'file-heart', 'Upload Alcohol & Drug Test Policy PDF', 'alcohol_pdf_filename', 'Upload a PDF file containing the Alcohol & Drug Testing Policy'],
        'general_work_policy_pdf' => ['General Work Policy', 'briefcase', 'Upload General Work Policy PDF', 'general_pdf_filename', 'Upload a PDF file containing the General Work Policy'],
    ];
@endphp

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Upload Company Policy PDFs" description="Your company's policies. Until you upload one, your drivers and applicants see the platform default." />

        <x-ui.flash />

        <form action="{{ route('admin.settings.policy.pdf.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            @if (isset($existingPolicies) && count($existingPolicies) > 0)
                <x-ui.card title="Existing Policy PDFs">
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($existingPolicies as $policy)
                            @foreach (['alcohol_drug_test' => 'Alcohol & Drug Policy', 'general_work' => 'General Work Policy'] as $kind => $title)
                                @if ($policy->{$kind.'_policy_file_id'})
                                    <div class="flex items-center justify-between gap-3 rounded-lg border bg-muted/40 p-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <x-ui.icon name="file-text" class="size-5 shrink-0 text-destructive" />
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-foreground">{{ $title }}</p>
                                                <p class="truncate text-xs text-muted-foreground">{{ $policy->fileFor($kind)?->original_name }}</p>
                                            </div>
                                        </div>
                                        <x-ui.button variant="ghost" size="sm" icon="eye" :href="route('admin.settings.policy.pdf.file', $kind)" target="_blank" rel="noopener">View</x-ui.button>
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($uploads as $name => [$title, $icon, $label, $filenameId, $help])
                    <x-ui.card :title="$title">
                        <div class="grid gap-2">
                            <p class="label">{{ $label }}</p>
                            <label for="{{ $name }}" data-dropzone @class([
                                'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed bg-muted/40 p-6 text-center transition-colors hover:bg-muted has-[input:focus-visible]:border-ring',
                                'border-destructive' => $errors->has($name),
                            ])>
                                <x-ui.icon :name="$icon" class="size-8 text-muted-foreground" />
                                <span class="text-sm text-muted-foreground"><span class="font-medium text-primary">Click to upload</span> or drag and drop</span>
                                <span class="text-xs text-muted-foreground">PDF files only (MAX. 10MB)</span>
                                <span id="{{ $filenameId }}" class="hidden text-sm font-medium text-success"></span>
                                <input id="{{ $name }}" name="{{ $name }}" type="file" class="sr-only" accept=".pdf,application/pdf"
                                    data-filename="{{ $filenameId }}" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror />
                            </label>
                            <p class="text-xs text-muted-foreground">{{ $help }}</p>
                            @error($name)
                                <p id="{{ $name }}-error" role="alert" class="text-sm text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="outline" :href="route('admin.dashboard')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="upload">Upload Policies</x-ui.button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const maxSize = 10 * 1024 * 1024; // 10MB

            document.querySelectorAll('input[data-filename]').forEach((input) => {
                const display = document.getElementById(input.dataset.filename);
                const reset = (message) => {
                    input.value = '';
                    display.classList.add('hidden');
                    window.showAppAlert(message, 'error', 'Invalid file');
                };

                input.addEventListener('change', function () {
                    const file = this.files[0];
                    if (!file) {
                        display.classList.add('hidden');
                        return;
                    }
                    if (file.type !== 'application/pdf') return reset('Please upload only PDF files.');
                    if (file.size > maxSize) return reset('File size must be less than 10MB.');

                    display.textContent = `Selected: ${file.name}`;
                    display.classList.remove('hidden');
                });
            });

            document.querySelectorAll('[data-dropzone]').forEach((zone) => {
                const highlight = (on) => zone.classList.toggle('border-primary', on);
                zone.addEventListener('dragover', (e) => { e.preventDefault(); highlight(true); });
                zone.addEventListener('dragleave', (e) => { e.preventDefault(); highlight(false); });
                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    highlight(false);
                    const input = zone.querySelector('input[type="file"]');
                    if (e.dataTransfer.files.length > 0) {
                        input.files = e.dataTransfer.files;
                        input.dispatchEvent(new Event('change'));
                    }
                });
            });
        });
    </script>
@endpush
