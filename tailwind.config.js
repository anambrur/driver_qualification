import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
import plugin from "tailwindcss/plugin";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],

    darkMode: "class",

    theme: {
        extend: {
            fontFamily: {
                outfit: ["Outfit", ...defaultTheme.fontFamily.sans],
                sans: ["Outfit", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                current: "currentColor",
                transparent: "transparent",
                white: "#ffffff",
                black: "#101828",

                brand: {
                    25: "#f2f7ff",
                    50: "#ecf3ff",
                    100: "#dde9ff",
                    200: "#c2d6ff",
                    300: "#9cb9ff",
                    400: "#7592ff",
                    500: "#465fff",
                    600: "#3641f5",
                    700: "#2a31d8",
                    800: "#252dae",
                    900: "#262e89",
                    950: "#161950",
                },

                "blue-light": {
                    25: "#f5fbff",
                    50: "#f0f9ff",
                    100: "#e0f2fe",
                    200: "#b9e6fe",
                    300: "#7cd4fd",
                    400: "#36bffa",
                    500: "#0ba5ec",
                    600: "#0086c9",
                    700: "#026aa2",
                    800: "#065986",
                    900: "#0b4a6f",
                    950: "#062c41",
                },

                gray: {
                    25: "#fcfcfd",
                    50: "#f9fafb",
                    100: "#f2f4f7",
                    200: "#e4e7ec",
                    300: "#d0d5dd",
                    400: "#98a2b3",
                    500: "#667085",
                    600: "#475467",
                    700: "#344054",
                    800: "#1d2939",
                    900: "#101828",
                    950: "#0c111d",
                    dark: "#1a2231",
                },

                orange: {
                    25: "#fffaf5",
                    50: "#fff6ed",
                    100: "#ffead5",
                    200: "#fddcab",
                    300: "#feb273",
                    400: "#fd853a",
                    500: "#fb6514",
                    600: "#ec4a0a",
                    700: "#c4320a",
                    800: "#9c2a10",
                    900: "#7e2410",
                    950: "#511c10",
                },

                success: {
                    25: "#f6fef9",
                    50: "#ecfdf3",
                    100: "#d1fadf",
                    200: "#a6f4c5",
                    300: "#6ce9a6",
                    400: "#32d583",
                    500: "#12b76a",
                    600: "#039855",
                    700: "#027a48",
                    800: "#05603a",
                    900: "#054f31",
                    950: "#053321",
                },

                error: {
                    25: "#fffbfa",
                    50: "#fef3f2",
                    100: "#fee4e2",
                    200: "#fecdca",
                    300: "#fda29b",
                    400: "#f97066",
                    500: "#f04438",
                    600: "#d92d20",
                    700: "#b42318",
                    800: "#912018",
                    900: "#7a271a",
                    950: "#55160c",
                },

                warning: {
                    25: "#fffcf5",
                    50: "#fffaeb",
                    100: "#fef0c7",
                    200: "#fedf89",
                    300: "#fec84b",
                    400: "#fdb022",
                    500: "#f79009",
                    600: "#dc6803",
                    700: "#b54708",
                    800: "#93370d",
                    900: "#7a2e0e",
                    950: "#4e1d09",
                },

                pink: {
                    500: "#ee46bc",
                },

                purple: {
                    500: "#7a5af8",
                },
            },
            fontSize: {
                "title-2xl": ["72px", "90px"],
                "title-xl": ["60px", "72px"],
                "title-lg": ["48px", "60px"],
                "title-md": ["36px", "44px"],
                "title-sm": ["30px", "38px"],
                "theme-xl": ["20px", "30px"],
                "theme-sm": ["14px", "20px"],
                "theme-xs": ["12px", "18px"],
            },
            screens: {
                "2xsm": "375px",
                xsm: "425px",
                "3xl": "2000px",
            },
            boxShadow: {
                "theme-md":
                    "0px 4px 8px -2px rgba(16, 24, 40, 0.1), 0px 2px 4px -2px rgba(16, 24, 40, 0.06)",
                "theme-lg":
                    "0px 12px 16px -4px rgba(16, 24, 40, 0.08), 0px 4px 6px -2px rgba(16, 24, 40, 0.03)",
                "theme-sm":
                    "0px 1px 3px 0px rgba(16, 24, 40, 0.1), 0px 1px 2px 0px rgba(16, 24, 40, 0.06)",
                "theme-xs": "0px 1px 2px 0px rgba(16, 24, 40, 0.05)",
                "theme-xl":
                    "0px 20px 24px -4px rgba(16, 24, 40, 0.08), 0px 8px 8px -4px rgba(16, 24, 40, 0.03)",
                datepicker: "-5px 0 0 #262d3c, 5px 0 0 #262d3c",
                "focus-ring": "0px 0px 0px 4px rgba(70, 95, 255, 0.12)",
                "slider-navigation":
                    "0px 1px 2px 0px rgba(16, 24, 40, 0.1), 0px 1px 3px 0px rgba(16, 24, 40, 0.1)",
                tooltip:
                    "0px 4px 6px -2px rgba(16, 24, 40, 0.05), -8px 0px 20px 8px rgba(16, 24, 40, 0.05)",
                "4xl": "0 35px 35px rgba(0, 0, 0, 0.25), 0 45px 65px rgba(0, 0, 0, 0.15)",
            },
            zIndex: {
                1: "1",
                9: "9",
                99: "99",
                999: "999",
                9999: "9999",
                99999: "99999",
                999999: "999999",
            },
            borderColor: {
                DEFAULT: "var(--color-gray-200, currentColor)",
            },
        },
    },

    plugins: [
        forms,
        plugin(function ({ addUtilities, addComponents, addBase }) {
            // Add base styles
            addBase({
                body: {
                    "@apply relative text-base font-normal font-outfit z-[1] bg-gray-50":
                        {},
                },
                "*": {
                    "border-color": "var(--color-gray-200, currentColor)",
                },
                'button:not(:disabled), [role="button"]:not(:disabled)': {
                    cursor: "pointer",
                },
            });

            // Add utilities
            addUtilities({
                ".no-scrollbar": {
                    /* Chrome, Safari and Opera */
                    "&::-webkit-scrollbar": {
                        display: "none",
                    },
                    /* IE and Edge */
                    "-ms-overflow-style": "none",
                    /* Firefox */
                    "scrollbar-width": "none",
                },
                ".custom-scrollbar": {
                    "&::-webkit-scrollbar": {
                        width: "6px",
                        height: "6px",
                    },
                    "&::-webkit-scrollbar-track": {
                        "border-radius": "9999px",
                    },
                    "&::-webkit-scrollbar-thumb": {
                        "background-color": "#e4e7ec",
                        "border-radius": "9999px",
                    },
                },
                ".dark .custom-scrollbar::-webkit-scrollbar-thumb": {
                    "background-color": "#344054",
                },
                ".menu-item": {
                    "@apply relative flex items-center gap-3 px-3 py-2 font-medium rounded-lg text-theme-sm":
                        {},
                },
                ".menu-item-active": {
                    "@apply bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400":
                        {},
                },
                ".menu-item-inactive": {
                    "@apply text-gray-700 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-gray-300":
                        {},
                },
                ".menu-item-icon-active": {
                    "@apply fill-brand-500 dark:fill-brand-400": {},
                },
                ".menu-item-icon-inactive": {
                    "@apply fill-gray-500 group-hover:fill-gray-700 dark:fill-gray-400 dark:group-hover:fill-gray-300":
                        {},
                },
                ".menu-item-arrow": {
                    "@apply absolute top-1/2 right-2.5 -translate-y-1/2": {},
                },
                ".menu-item-arrow-active": {
                    "@apply rotate-180 stroke-brand-500 dark:stroke-brand-400":
                        {},
                },
                ".menu-item-arrow-inactive": {
                    "@apply stroke-gray-500 group-hover:stroke-gray-700 dark:stroke-gray-400 dark:group-hover:stroke-gray-300":
                        {},
                },
                ".menu-dropdown-item": {
                    "@apply text-theme-sm relative flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium":
                        {},
                },
                ".menu-dropdown-item-active": {
                    "@apply bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400":
                        {},
                },
                ".menu-dropdown-item-inactive": {
                    "@apply text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5":
                        {},
                },
                ".menu-dropdown-badge": {
                    "@apply text-brand-500 dark:text-brand-400 block rounded-full px-2.5 py-0.5 text-xs font-medium uppercase":
                        {},
                },
                ".menu-dropdown-badge-active": {
                    "@apply bg-brand-100 dark:bg-brand-500/20": {},
                },
                ".menu-dropdown-badge-inactive": {
                    "@apply bg-brand-50 group-hover:bg-brand-100 dark:bg-brand-500/15 dark:group-hover:bg-brand-500/20":
                        {},
                },
            });

            // Add components
            addComponents({
                ".sidebar:hover": {
                    width: "290px",
                    "& .logo": {
                        display: "block",
                    },
                    "& .logo-icon": {
                        display: "none",
                    },
                    "& .sidebar-header": {
                        "justify-content": "space-between",
                    },
                    "& .menu-group-title": {
                        display: "block",
                    },
                    "& .menu-group-icon": {
                        display: "none",
                    },
                    "& .menu-item-text": {
                        display: "inline",
                    },
                    "& .menu-item-arrow": {
                        display: "block",
                    },
                    "& .menu-dropdown": {
                        display: "flex",
                    },
                },
                ".tableCheckbox:checked ~ span span": {
                    "@apply opacity-100": {},
                },
                ".tableCheckbox:checked ~ span": {
                    "@apply border-brand-500 bg-brand-500": {},
                },
                ".taskCheckbox:checked ~ .box span": {
                    "@apply opacity-100": {},
                },
                ".taskCheckbox:checked ~ p": {
                    "@apply text-gray-400 line-through": {},
                },
                ".taskCheckbox:checked ~ .box": {
                    "@apply border-brand-500 bg-brand-500 dark:border-brand-500":
                        {},
                },
                ".task": {
                    transition: "all 0.2s ease",
                },
                ".task.is-dragging": {
                    "border-radius": "0.75rem",
                    "box-shadow":
                        "0px 1px 3px 0px rgba(16, 24, 40, 0.1), 0px 1px 2px 0px rgba(16, 24, 40, 0.06)",
                    opacity: "0.8",
                    cursor: "grabbing",
                },
                ".form-check-input:checked ~ span": {
                    "@apply border-brand-500 dark:border-brand-500 border-[6px]":
                        {},
                },
            });
        }),
    ],
};
