import "./bootstrap";

import "basecoat-css/basecoat";
import "basecoat-css/dropdown-menu";
import "basecoat-css/popover";
import "basecoat-css/tabs";
import "basecoat-css/toast";

import Alpine from "alpinejs";
import persist from "@alpinejs/persist";
import collapse from "@alpinejs/collapse";
import focus from "@alpinejs/focus";

import theme from "./theme";
import { openDialog } from "./dialogs";

// Legacy helper still used by pages not yet migrated (phase 2).
window.isCurrentPath = (pattern) => {
    const path = window.location.pathname.replace(/^\/+/, "");
    const regex = new RegExp("^" + pattern.replace(/\*/g, ".*") + "$");
    return regex.test(path);
};

Alpine.plugin(persist);
Alpine.plugin(collapse);
Alpine.plugin(focus);
Alpine.store("theme", theme);

// Shell state: mobile sheet + desktop rail (persisted; pre-painted by partials/theme-head).
Alpine.data("appShell", () => ({
    sidebarOpen: false,
    collapsed: Alpine.$persist(false).as("sidebarCollapsed"),
    init() {
        this.$watch("collapsed", (value) => {
            document.documentElement.dataset.sidebar = value ? "collapsed" : "expanded";
        });
    },
}));

// Basecoat inserts title/description as HTML, so escape them here.
const escapeHtml = (value) => {
    const div = document.createElement("div");
    div.textContent = value ?? "";
    return div.innerHTML;
};

window.notify = (category, title, description = "") => {
    const toaster = document.getElementById("toaster");
    if (toaster && typeof toaster.toast === "function") {
        toaster.toast({ category, title: escapeHtml(title), description: escapeHtml(description) });
    }
};
window.Alpine = Alpine;
// Open the native date picker on click, not only on the (hidden) calendar indicator.
document.addEventListener('click', (event) => {
    const input = event.target.closest?.('input.input:is([type=date], [type=datetime-local], [type=month], [type=week])');
    if (!input || input.readOnly || input.disabled) return;
    try {
        input.showPicker?.();
    } catch {
        // showPicker throws when the picker is already open or the page is cross-origin framed.
    }
});

// <a href="..." data-confirm="Title" data-confirm-text="..."> asks in the same dialog before following the link.
document.addEventListener("click", (event) => {
    const link = event.target instanceof Element ? event.target.closest("a[data-confirm]") : null;
    if (!link || !document.getElementById("confirm-dialog") || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;

    event.preventDefault();
    openDialog({
        title: link.dataset.confirm,
        text: link.dataset.confirmText ?? "",
        confirmText: link.dataset.confirmButton ?? "Continue",
        cancelText: link.dataset.confirmCancel ?? "Cancel",
    }).then(({ isConfirmed }) => {
        if (isConfirmed) window.location.href = link.href;
    });
});

// <form data-confirm="Title" data-confirm-text="..." data-confirm-button="Delete" data-confirm-cancel="Keep">
// asks in the layout's <x-ui.confirm-dialog> first, and submits only on confirm.
document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || !document.getElementById("confirm-dialog")) return;
    if (form.dataset.confirmed) {
        delete form.dataset.confirmed;
        return;
    }

    event.preventDefault();
    const submitter = event.submitter;
    openDialog({
        title: form.dataset.confirm,
        text: form.dataset.confirmText ?? "",
        confirmText: form.dataset.confirmButton ?? "Confirm",
        cancelText: form.dataset.confirmCancel ?? "Cancel",
        variant: "destructive",
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        form.dataset.confirmed = "1";
        form.requestSubmit(submitter);
    });
});

Alpine.start();
