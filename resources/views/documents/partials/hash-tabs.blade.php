{{--
    Keeps the open tab of x-ui.tabs#$tabs in the URL hash, so links, reloads and redirects land on it.
    Runs at once, not on DOMContentLoaded: Basecoat initializes the tabs on that event and selects
    whichever tab is marked aria-selected, so the hash's tab is marked before that happens.
--}}
@push('scripts')
    <script>
        (() => {
            const id = @json($tabs);
            const root = document.getElementById(id);
            if (!root) return;

            const open = (name) => {
                const tab = document.getElementById(`${id}-tab-${name}`);
                if (!tab || !root.contains(tab)) return;
                if (root.select) return root.select(tab);

                root.querySelectorAll('[role="tab"]').forEach((other) => {
                    const selected = other === tab;
                    other.setAttribute('aria-selected', selected ? 'true' : 'false');
                    other.setAttribute('tabindex', selected ? '0' : '-1');
                    const panel = document.getElementById(other.getAttribute('aria-controls'));
                    if (panel) panel.hidden = !selected;
                });
            };

            if (location.hash) open(decodeURIComponent(location.hash.slice(1)));
            window.addEventListener('hashchange', () => open(decodeURIComponent(location.hash.slice(1))));
            root.addEventListener('click', (event) => {
                const tab = event.target.closest('[role="tab"]');
                if (tab) history.replaceState(null, '', '#' + tab.id.replace(`${id}-tab-`, ''));
            });
        })();
    </script>
@endpush
