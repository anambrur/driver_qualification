// Light / dark / system switch. The first paint is handled by the inline
// script in partials/theme-head; this store keeps the UI in sync afterwards.
const media = window.matchMedia("(prefers-color-scheme: dark)");

const read = () => {
    try {
        return localStorage.getItem("theme");
    } catch (_) {
        return null;
    }
};

const apply = (mode) => {
    const dark = mode === "dark" || (mode === "system" && media.matches);
    document.documentElement.classList.toggle("dark", dark);
};

const theme = {
    mode: read() || document.documentElement.dataset.themeDefault || "system",

    set(mode) {
        this.mode = mode;
        try {
            localStorage.setItem("theme", mode);
        } catch (_) {}
        apply(mode);
    },

    get dark() {
        return document.documentElement.classList.contains("dark");
    },
};

media.addEventListener("change", () => {
    if (theme.mode === "system") apply("system");
});

export default theme;
