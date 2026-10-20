{{-- One per layout. From JS: window.notify('success', 'Saved', 'Optional description'). From PHP: App\Support\Ui\Toast::success('Saved') --}}
<div id="toaster" class="toaster" aria-live="polite"></div>
@php($toasts = session()->pull(\App\Support\Ui\Toast::SESSION_KEY, []))
@if ($toasts)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            {{ Js::from($toasts) }}.forEach((toast) => window.notify(toast.category, toast.message));
        });
    </script>
@endif
