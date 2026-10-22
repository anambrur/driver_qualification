{{-- Keeps the open tab of x-ui.tabs#$tabs in the URL hash, so links and reloads land on it. --}}
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.getElementById(@json($tabs));
            const open = (name) => tabs.querySelector(`#${CSS.escape(@json($tabs))}-tab-${CSS.escape(name)}`)?.click();
            if (location.hash) open(location.hash.slice(1));
            tabs.addEventListener('click', (event) => {
                const tab = event.target.closest('[role="tab"]');
                if (tab) history.replaceState(null, '', '#' + tab.id.replace(@json($tabs.'-tab-'), ''));
            });
        });
    </script>
@endpush
