// Alerts and confirms on the layout's <x-ui.confirm-dialog>. The result has the SweetAlert shape
// ({ isConfirmed, isDismissed, value }) so callers written for Swal keep working unchanged.
const tones = {
    warning: ["triangle-alert", "warning"],
    question: ["circle-help", null],
    error: ["circle-x", "danger"],
    success: ["circle-check", "success"],
    info: ["info", null],
};

const titles = {
    warning: "Attention",
    error: "Error",
    success: "Success",
    info: "Notice",
    question: "Please Confirm",
};

const result = (confirmed) => ({ isConfirmed: confirmed, isDismissed: !confirmed, value: confirmed || undefined });

export function openDialog({ title, text = "", icon = "warning", confirmText = "OK", cancelText = null, variant = "primary" }) {
    const dialog = document.getElementById("confirm-dialog");
    if (!dialog) {
        if (cancelText) return Promise.resolve(result(window.confirm(text)));
        window.alert(text);
        return Promise.resolve(result(true));
    }

    const [iconName, tone] = tones[icon] ?? tones.warning;
    const iconSlot = dialog.querySelector(".dialog-icon");
    const template = dialog.querySelector(`template[data-dialog-icon="${iconName}"]`);
    if (iconSlot && template) {
        iconSlot.replaceChildren(template.content.cloneNode(true));
        iconSlot.toggleAttribute("data-tone", Boolean(tone));
        if (tone) iconSlot.dataset.tone = tone;
    }

    dialog.querySelector("#confirm-dialog-title").textContent = title ?? titles[icon] ?? titles.warning;
    dialog.querySelector("#confirm-dialog-text").textContent = text;
    const ok = dialog.querySelector("[data-confirm-ok]");
    ok.textContent = confirmText;
    ok.dataset.variant = variant;
    const cancel = dialog.querySelector("[data-confirm-cancel]");
    cancel.hidden = !cancelText;
    cancel.textContent = cancelText ?? "";

    if (dialog.open) dialog.close();
    dialog.returnValue = "";

    return new Promise((resolve) => {
        dialog.addEventListener("close", () => resolve(result(dialog.returnValue === "ok")), { once: true });
        dialog.showModal();
    });
}

// Layouts that still load SweetAlert define their own versions inline (before this module runs).
window.showAppAlert ??= (message, icon = "warning", title = null) => openDialog({ title, text: message, icon });

window.showAppConfirm ??= (message, options = {}) =>
    openDialog({
        title: options.title || "Please Confirm",
        text: message,
        icon: options.icon || "question",
        confirmText: options.confirmButtonText || "Yes, continue",
        cancelText: options.cancelButtonText || "Cancel",
        variant: options.variant || "primary",
    });
