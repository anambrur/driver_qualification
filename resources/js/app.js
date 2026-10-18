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

Alpine.start();
