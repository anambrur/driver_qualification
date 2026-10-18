// DataTables 2 entry, loaded only by pages that render <x-ui.datatable> (via @push('datatable')).
import $ from "jquery";
import DataTable from "datatables.net-dt";
import "datatables.net-dt/css/dataTables.dataTables.css";
import "../css/datatable.css";
import "./crud";

window.$ = window.jQuery = $;
window.DataTable = DataTable;

const icon = (path) =>
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${path}"/></svg>`;

/**
 * Server-side (yajra) table with the app's defaults.
 *
 *   const table = dqTable('#drivers', { ajax: url, columns: [...], primary: 1 });
 *
 * Inside the wrapper:
 *   - [data-dt-filter="status"] select, or buttons with [data-dt-filter="status"][data-value="x"],
 *     are sent as request params and reload the table on change.
 *   - <template data-dt-empty> / <template data-dt-zero> hold the empty-state HTML.
 */
window.dqTable = (root, options = {}) => {
    const wrapper = typeof root === "string" ? document.querySelector(root) : root;
    const tableEl = wrapper.querySelector("table");
    const labels = [...tableEl.querySelectorAll("thead th")].map((th) => th.textContent.trim());
    const filters = {};
    const { ajax, data: extraData, primary = null, ...rest } = options;
    const template = (name) => wrapper.querySelector(`template[data-dt-${name}]`)?.innerHTML.trim();

    const table = new DataTable(tableEl, {
        serverSide: true,
        processing: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        searchDelay: 400,
        stateSave: true,
        stateDuration: 60 * 60,
        layout: {
            topStart: "search",
            topEnd: "pageLength",
            bottomStart: "info",
            bottomEnd: "paging",
        },
        ajax: {
            url: ajax,
            data: (d) => {
                Object.entries(filters).forEach(([key, value]) => {
                    if (value !== "" && value !== "all") d[key] = value;
                });
                if (typeof extraData === "function") extraData(d);
            },
        },
        language: {
            search: "",
            searchPlaceholder: "Search…",
            lengthMenu: "_MENU_ per page",
            info: "_START_–_END_ of _TOTAL_",
            infoEmpty: "No results",
            infoFiltered: "(filtered from _MAX_)",
            processing: '<span class="dt-spinner" role="status" aria-label="Loading"></span>',
            emptyTable: template("empty") || "No records yet.",
            zeroRecords: template("zero") || "No matching records.",
            paginate: {
                first: icon("m11 17-5-5 5-5M18 17l-5-5 5-5"),
                previous: icon("m15 18-6-6 6-6"),
                next: icon("m9 18 6-6-6-6"),
                last: icon("m6 17 5-5-5-5M13 17l5-5-5-5"),
            },
            aria: { paginate: { first: "First page", previous: "Previous page", next: "Next page", last: "Last page" } },
        },
        createdRow: (row) => {
            row.querySelectorAll("td").forEach((td, i) => {
                td.dataset.label = labels[i] ?? "";
                if (i === primary) td.dataset.primary = "";
            });
        },
        ...rest,
    });

    const setFilter = (name, value) => {
        filters[name] = value;
        wrapper.querySelectorAll(`button[data-dt-filter="${name}"]`).forEach((btn) => {
            btn.setAttribute("aria-pressed", String(btn.dataset.value === value));
        });
        table.ajax.reload();
    };

    wrapper.querySelectorAll("[data-dt-filter]").forEach((el) => {
        const name = el.dataset.dtFilter;
        if (el.tagName === "BUTTON") {
            if (el.getAttribute("aria-pressed") === "true") filters[name] = el.dataset.value;
            el.addEventListener("click", () => setFilter(name, el.dataset.value));
        } else {
            filters[name] = el.value;
            el.addEventListener("change", () => setFilter(name, el.value));
        }
    });

    wrapper.querySelector("[data-dt-reset]")?.addEventListener("click", () => {
        wrapper.querySelectorAll("[data-dt-filter]").forEach((el) => {
            if (el.tagName !== "BUTTON") el.value = "";
        });
        Object.keys(filters).forEach((name) => (filters[name] = "all"));
        wrapper.querySelectorAll('button[data-dt-filter][data-value="all"]').forEach((btn) => btn.click());
        table.search("").draw();
    });

    return table;
};
