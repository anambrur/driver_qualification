{{-- Session flash + validation summary. Rendered once by the layout. --}}
@php
    $messages = array_filter([
        'success' => session('success'),
        'danger' => session('error'),
        'warning' => session('warning'),
        'info' => session('info') ?? session('status'),
    ], fn ($message) => is_string($message) && $message !== '');
@endphp
@if ($messages || $errors->any())
    <div {{ $attributes->class('mb-6 space-y-3') }}>
        @foreach ($messages as $tone => $message)
            <x-ui.alert :tone="$tone" dismissible>{{ $message }}</x-ui.alert>
        @endforeach
        @if ($errors->any())
            <x-ui.alert tone="danger" :title="$errors->count() === 1 ? 'Please fix the error below.' : 'Please fix the '.$errors->count().' errors below.'">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif
    </div>
@endif
