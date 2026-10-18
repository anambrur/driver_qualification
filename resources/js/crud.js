// Small helpers for list pages that add/edit/delete rows in a dialog (loaded with datatable.js).

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? "";

/** fetch() with the CSRF token and JSON accept; resolves to { ok, status, json }. */
const request = (url, method = "GET", body = undefined) =>
    fetch(url, {
        method,
        headers: { "X-CSRF-TOKEN": csrf(), Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        body,
    }).then(async (response) => ({ ok: response.ok, status: response.status, json: await response.json().catch(() => ({})) }));

const controlsOf = (form, name) => {
    const control = form.elements.namedItem(name);
    if (!control) return [];
    return control instanceof RadioNodeList ? [...control] : [control];
};

/** Show Laravel 422 errors ({ field: [message] }) under each field; no argument clears them. */
const setErrors = (form, errors = {}) => {
    form.querySelectorAll(".js-error").forEach((el) => el.remove());
    form.querySelectorAll("[aria-invalid]").forEach((el) => el.removeAttribute("aria-invalid"));
    let first = null;
    Object.entries(errors).forEach(([name, messages]) => {
        const controls = controlsOf(form, name);
        const field = controls[0]?.closest(".field, fieldset");
        if (!field) return;
        const error = document.createElement("p");
        error.className = "js-error text-sm text-destructive";
        error.setAttribute("role", "alert");
        error.textContent = [].concat(messages)[0];
        field.append(error);
        controls.forEach((el) => el.setAttribute("aria-invalid", "true"));
        first ??= controls[0];
    });
    first?.focus();
};

/** Fill named controls from a record; missing keys clear the control. `name[]` checkboxes take an array. */
const fill = (form, data = {}) => {
    [...form.elements].forEach((el) => {
        if (!el.name || el.type === "file" || el.name.startsWith("_")) return;
        const value = data[el.name] ?? data[el.name.replace(/\[\]$/, "")] ?? "";
        if (el.type === "checkbox" && Array.isArray(value)) el.checked = value.map(String).includes(el.value);
        else if (el.type === "checkbox") el.checked = Boolean(value) && value !== "0";
        else if (el.type === "radio") el.checked = String(el.value) === String(value === true ? 1 : value === false ? 0 : value);
        else el.value = value;
    });
};

/** SweetAlert confirm, then run the async action; returns its result or null. */
const confirm = (options, action) =>
    window.Swal.fire({ icon: "warning", showCancelButton: true, reverseButtons: true, ...options }).then((result) => (result.isConfirmed ? action() : null));

/** Toast the JSON message and reload the table when ok. */
const report = (table, { ok, json }, fallback) => {
    window.notify(ok ? "success" : "error", json.message || (ok ? "Saved" : fallback));
    if (ok) table?.ajax.reload(null, false);
    return ok;
};

const at = (url, id) => url.replace(":id", encodeURIComponent(id));

/**
 * Wire a list page's add/edit dialog and its row actions (data-action="create|edit|delete|restore").
 *
 *   dqCrud.page({ table, dialog: '#vehicle-dialog', singular: 'vehicle',
 *                 routes: { store, edit, update, destroy, restore } })   // ':id' placeholders
 *
 * Optional hooks: defaults (a blank record), toForm(record) before filling, afterFill(form, record),
 * and actions: { view: (id, button) => … } for any other data-action.
 */
const page = ({ table, dialog, singular, routes, defaults = {}, toForm = (record) => record, afterFill = () => {}, actions = {} }) => {
    const dialogEl = typeof dialog === "string" ? document.querySelector(dialog) : dialog;
    const form = dialogEl?.querySelector("form");
    const title = dialogEl?.querySelector("h2");
    const submit = dialogEl?.querySelector('button[type="submit"]');
    let editingId = null;

    const open = (heading, record) => {
        editingId = record?.id ?? null;
        title.textContent = heading;
        setErrors(form);
        fill(form, { ...defaults, ...toForm(record ?? {}) });
        afterFill(form, record ?? {});
        dialogEl.showModal();
        form.querySelector("input:not([type=hidden]), select, textarea")?.focus();
    };

    document.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-action]");
        if (!button || button.closest("dialog")) return;
        const { action, id, name } = button.dataset;

        if (actions[action]) {
            actions[action](id, button);
        } else if (action === "create" && form) {
            open(`Add ${singular}`);
        } else if (action === "edit" && form) {
            button.disabled = true;
            const { ok, json } = await request(at(routes.edit, id));
            button.disabled = false;
            if (ok) open(`Edit ${singular}`, json.data);
            else window.notify("error", json.message || `Could not load the ${singular}.`);
        } else if (action === "delete") {
            confirm(
                { title: `Delete ${singular}?`, text: `${name} will be deleted.`, confirmButtonText: "Delete", customClass: { confirmButton: "swal2-deny" } },
                async () => report(table, await request(at(routes.destroy, id), "DELETE"), `Could not delete the ${singular}.`),
            );
        } else if (action === "restore") {
            confirm(
                { icon: "question", title: `Restore ${singular}?`, text: `${name} will be active again.`, confirmButtonText: "Restore" },
                async () => report(table, await request(at(routes.restore, id), "POST"), `Could not restore the ${singular}.`),
            );
        }
    });

    form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const body = new FormData(form);
        if (editingId) body.append("_method", "PUT");

        submit.disabled = true;
        const response = await request(editingId ? at(routes.update, editingId) : routes.store, "POST", body);
        submit.disabled = false;

        if (response.status === 422) setErrors(form, response.json.errors);
        else if (report(table, response, "Something went wrong. Please try again.")) dialogEl.close();
    });

    return { open };
};

window.dqCrud = { request, setErrors, fill, confirm, report, page, at };
