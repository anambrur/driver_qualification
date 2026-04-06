import "./bootstrap";
import "../css/app.css";

import "flatpickr/dist/flatpickr.min.css";
import Alpine from "alpinejs";
import persist from "@alpinejs/persist";
import collapse from "@alpinejs/collapse";
import flatpickr from "flatpickr";
import Dropzone from "dropzone";

import chart01 from "./components/charts/chart-01";
import chart02 from "./components/charts/chart-02";
import chart03 from "./components/charts/chart-03";
import map01 from "./components/map-01";
import "./components/calendar-init.js";
import "./components/image-resize";

// URL pattern matching function
window.isCurrentPath = (pattern) => {
    const path = window.location.pathname;
    const regex = new RegExp("^" + pattern.replace("*", ".*") + "$");
    return regex.test(path);
};

Alpine.plugin(persist);
Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

// Initialize flatpickr after DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
    // Initialize flatpickr
    const datepickers = document.querySelectorAll(".datepicker");

    if (datepickers.length > 0) {
        flatpickr(".datepicker", {
            mode: "range",
            static: true,
            monthSelectorType: "static",
            dateFormat: "M j, Y",
            defaultDate: [
                new Date().setDate(new Date().getDate() - 6),
                new Date(),
            ],
            prevArrow:
                '<svg class="stroke-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.25 6L9 12.25L15.25 18.5" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            nextArrow:
                '<svg class="stroke-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.75 19L15 12.75L8.75 6.5" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            onReady: (selectedDates, dateStr, instance) => {
                instance.element.value = dateStr.replace("to", "-");
                const customClass = instance.element.getAttribute("data-class");
                if (customClass) {
                    instance.calendarContainer.classList.add(customClass);
                }
            },
            onChange: (selectedDates, dateStr, instance) => {
                instance.element.value = dateStr.replace("to", "-");
            },
        });
    }
});

// Init Dropzone
const dropzoneElement = document.getElementById("demo-upload");
if (dropzoneElement) {
    new Dropzone("#demo-upload", { url: "/file/post" });
}

// Document Loaded
document.addEventListener("DOMContentLoaded", () => {
    if (typeof chart01 === "function") chart01();
    if (typeof chart02 === "function") chart02();
    if (typeof chart03 === "function") chart03();
    if (typeof map01 === "function") map01();
});

// Get the current year
document.addEventListener("DOMContentLoaded", () => {
    const year = document.getElementById("year");
    if (year) {
        year.textContent = new Date().getFullYear();
    }
});

// For Copy
document.addEventListener("DOMContentLoaded", () => {
    const copyButton = document.getElementById("copy-button");
    const websiteInput = document.getElementById("website-input");
    const copyText = document.getElementById("copy-text");

    if (copyButton && websiteInput) {
        copyButton.addEventListener("click", () => {
            navigator.clipboard.writeText(websiteInput.value).then(() => {
                if (copyText) {
                    copyText.textContent = "Copied";
                    setTimeout(() => {
                        copyText.textContent = "Copy";
                    }, 2000);
                }
            });
        });
    }
});

// For Search
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("search-input");
    const searchButton = document.getElementById("search-button");

    if (searchInput && searchButton) {
        searchButton.addEventListener("click", () => searchInput.focus());

        document.addEventListener("keydown", function (event) {
            if ((event.metaKey || event.ctrlKey) && event.key === "k") {
                event.preventDefault();
                searchInput.focus();
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "/" && document.activeElement !== searchInput) {
                event.preventDefault();
                searchInput.focus();
            }
        });
    }
});
