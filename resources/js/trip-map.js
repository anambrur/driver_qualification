// The trip's stops on a read-only Leaflet map (req 6.1): numbered markers in route order, fitted to
// the route. The map may start in a hidden tab, so it is fitted again whenever its size changes.
import L from "leaflet";
import "leaflet/dist/leaflet.css";

function initTripMap(root) {
    const stops = JSON.parse(root.dataset.stops || "[]");
    if (stops.length === 0) return;

    const map = L.map(root, { scrollWheelZoom: false });
    L.tileLayer(root.dataset.tileUrl, { maxZoom: 19, attribution: root.dataset.attribution }).addTo(map);

    const points = stops.map((stop, index) => {
        const icon = L.divIcon({
            className: "grid place-items-center rounded-full bg-primary text-xs font-semibold text-primary-foreground shadow ring-2 ring-background",
            html: `<span>${index + 1}</span>`,
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
        // A string tooltip is set as HTML; the label is user text, so pass a text node
        const label = document.createElement("span");
        label.textContent = stop.label;
        L.marker([stop.lat, stop.lng], { icon, title: stop.label, keyboard: false }).addTo(map).bindTooltip(label);

        return [stop.lat, stop.lng];
    });

    if (points.length > 1) L.polyline(points, { color: "currentColor", weight: 3, opacity: 0.6, dashArray: "6 6" }).addTo(map);

    const fit = () => {
        map.invalidateSize();
        if (root.clientWidth === 0) return;
        points.length === 1 ? map.setView(points[0], 13) : map.fitBounds(points, { padding: [32, 32] });
    };

    fit();
    new ResizeObserver(fit).observe(root);
}

document.querySelectorAll("[data-trip-map]").forEach(initTripMap);
