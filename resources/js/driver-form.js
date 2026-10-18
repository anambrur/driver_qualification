// Admin driver create/edit: repeating rows, "same address" copy and the section nav.

function initRepeater(root) {
    const rows = root.querySelector('[data-repeater-rows]');
    const template = root.querySelector('[data-repeater-template]');
    let next = Number(root.dataset.nextIndex) || 0;

    const renumber = () => rows.querySelectorAll(':scope > [data-repeater-row] [data-row-number]')
        .forEach((el, i) => { el.textContent = i + 1; });

    root.querySelector('[data-repeater-add]').addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', next++).replaceAll('__NUMBER__', rows.children.length + 1);
        rows.insertAdjacentHTML('beforeend', html);
        renumber();
        rows.lastElementChild.querySelector('input:not([type=hidden]), select')?.focus();
    });

    rows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-repeater-remove]');
        if (!button) return;
        const row = button.closest('[data-repeater-row]');
        const previous = row.previousElementSibling;
        row.remove();
        renumber();
        previous?.querySelector('[data-repeater-remove], input:not([type=hidden])')?.focus();
    });
}

function initSameAddress(form) {
    const checkbox = form.querySelector('#same_address_checkbox');
    const firstRow = form.querySelector('[data-repeater="residences"] [data-repeater-rows] > [data-repeater-row]');
    if (!checkbox || !firstRow) return;

    const pairs = { address: 'residence_address', city: 'residence_city', country: 'residence_country', state: 'residence_state', postal_code: 'residence_postal_code' };
    checkbox.addEventListener('change', () => {
        if (!checkbox.checked) return;
        Object.entries(pairs).forEach(([from, to]) => {
            const source = form.querySelector(`[name="${from}"]`);
            const target = firstRow.querySelector(`[name^="${to}["]`);
            if (source && target) target.value = source.value;
        });
    });
}

function initSectionNav() {
    const links = [...document.querySelectorAll('[data-section-link]')];
    const sections = [...new Set(links.map((link) => link.hash))].map((hash) => document.querySelector(hash)).filter(Boolean);
    if (!sections.length || !('IntersectionObserver' in window)) return;

    const visible = new Set();
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => (entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target)));
        const current = sections.find((section) => visible.has(section));
        if (!current) return;
        links.forEach((link) => {
            const active = link.hash === `#${current.id}`;
            if (active) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
            if (link.classList.contains('badge')) {
                if (active) delete link.dataset.variant;
                else link.dataset.variant = 'outline';
            }
        });
    }, { rootMargin: '-80px 0px -60% 0px' });

    sections.forEach((section) => observer.observe(section));
}

const form = document.getElementById('driverForm');
if (form) {
    form.querySelectorAll('[data-repeater]').forEach(initRepeater);
    initSameAddress(form);
    initSectionNav();
}
