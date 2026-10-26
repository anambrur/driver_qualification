// Address picker (x-maps.address-picker, req 6.1): search on a button (never as-you-type: the
// OpenStreetMap geocoder policies forbid autocomplete), a result list, and a Leaflet map with a
// draggable pin. A result fills the fields and place_source/place_ref; a pin set by hand is "manual".
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";

// Bundled builds can't find Leaflet's default icon by its CSS path
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });

const US_CENTER = [39.5, -98.35];

function initPicker(root) {
    const field = (name) => root.querySelector(`[data-picker-field="${name}"]`);
    const search = root.querySelector("[data-picker-search]");
    const query = root.querySelector("[data-picker-query]");
    const findButton = root.querySelector("[data-picker-find]");
    const message = root.querySelector("[data-picker-message]");
    const results = root.querySelector("[data-picker-results]");
    const reverseButton = root.querySelector("[data-picker-reverse]");
    const mapWrap = root.querySelector("[data-picker-map-wrap]");

    mapWrap.hidden = false;
    if (search) search.hidden = false;

    const map = L.map(root.querySelector("[data-picker-map]"), { scrollWheelZoom: false });
    L.tileLayer(root.dataset.tileUrl, { maxZoom: 19, attribution: root.dataset.attribution }).addTo(map);
    new ResizeObserver(() => map.invalidateSize()).observe(mapWrap);

    let marker = null;

    const coordinates = () => {
        const lat = parseFloat(field("latitude").value);
        const lng = parseFloat(field("longitude").value);

        return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? [lat, lng] : null;
    };

    const setMarker = (latlng) => {
        if (marker) {
            marker.setLatLng(latlng);
            return;
        }
        marker = L.marker(latlng, { draggable: true, autoPan: true, keyboard: true, title: "Location pin" }).addTo(map);
        marker.on("dragend", () => setPin(marker.getLatLng()));
    };

    // A pin placed or moved by hand: coordinates only, the typed address stays
    const setPin = (latlng) => {
        field("latitude").value = latlng.lat.toFixed(7);
        field("longitude").value = latlng.lng.toFixed(7);
        field("place_source").value = "manual";
        field("place_ref").value = "";
        setMarker(latlng);
    };

    const say = (text) => {
        if (message) message.textContent = text || "";
    };

    // A part the result doesn't have is cleared, so two addresses never mix
    const setValue = (name, value) => {
        const input = field(name);
        if (!input) return;
        const known = input.tagName !== "SELECT" || [...input.options].some((option) => option.value === value);
        input.value = value && known ? value : "";
    };

    // From the pin (keepPin) only the address is taken; the pin stays where it was put
    const fill = (place, keepPin = false) => {
        field("country_code").value = place.country_code;
        setValue("address_line1", place.address_line1);
        setValue("city", place.city);
        setValue("state_code", place.state_code);
        setValue("zip", place.zip);
        field("place_source").value = place.place_source;
        field("place_ref").value = place.place_ref || "";
        if (!keepPin) {
            field("latitude").value = Number(place.latitude).toFixed(7);
            field("longitude").value = Number(place.longitude).toFixed(7);
            setMarker([place.latitude, place.longitude]);
            map.setView([place.latitude, place.longitude], 16);
        }
        if (results) results.hidden = true;
        say("Address filled in. Check it, and drag the pin to the exact spot if needed.");
    };

    const request = async (url, params) => {
        const response = await fetch(`${url}?${new URLSearchParams(params)}`, {
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin",
        });
        if (response.status === 429) return { results: [], message: "Too many searches. Wait a minute and try again." };
        if (!response.ok) return { results: [], message: "Address search is unavailable right now. Enter the address by hand." };

        return response.json();
    };

    const showResults = (places) => {
        results.replaceChildren(...places.map((place) => {
            const item = document.createElement("li");
            const button = document.createElement("button");
            button.type = "button";
            button.className = "w-full px-3 py-2 text-left text-sm hover:bg-accent focus-visible:bg-accent outline-hidden";
            button.textContent = place.label;
            button.addEventListener("click", () => fill(place));
            item.append(button);

            return item;
        }));
        results.hidden = places.length === 0;
    };

    const find = async () => {
        const text = query.value.trim();
        if (text.length < 3) {
            say("Type at least 3 characters.");
            return;
        }
        findButton.disabled = true;
        say("Searching…");
        try {
            const data = await request(root.dataset.searchUrl, { q: text });
            showResults(data.results);
            say(data.message || "Pick the matching address.");
        } catch {
            say("Address search is unavailable right now. Enter the address by hand.");
        } finally {
            findButton.disabled = false;
        }
    };

    findButton?.addEventListener("click", find);
    // Enter searches instead of submitting the form
    query?.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            find();
        }
    });

    reverseButton?.addEventListener("click", async () => {
        const point = coordinates();
        if (!point) {
            say("Place the pin on the map first.");
            return;
        }
        reverseButton.disabled = true;
        try {
            const data = await request(root.dataset.reverseUrl, { lat: point[0], lng: point[1] });
            if (data.results.length) {
                fill(data.results[0], true);
            } else {
                say(data.message || "No address found at the pin.");
            }
        } catch {
            say("Address search is unavailable right now. Enter the address by hand.");
        } finally {
            reverseButton.disabled = false;
        }
    });

    map.on("click", (event) => setPin(event.latlng));

    // Typing over a picked address makes it a manual one
    ["address_line1", "city", "state_code", "zip", "country_code"].forEach((name) => field(name)?.addEventListener("input", () => {
        if (field("place_source").value && field("place_source").value !== "manual") {
            field("place_source").value = "manual";
            field("place_ref").value = "";
        }
    }));

    ["latitude", "longitude"].forEach((name) => field(name).addEventListener("change", () => {
        const point = coordinates();
        if (!point) {
            if (!field("latitude").value && !field("longitude").value && marker) {
                marker.remove();
                marker = null;
            }
            return;
        }
        setPin(L.latLng(point));
        map.setView(point, Math.max(map.getZoom(), 14));
    }));

    const start = coordinates();
    if (start) {
        setMarker(start);
        map.setView(start, 15);
    } else {
        map.setView(US_CENTER, 4);
    }
}

document.querySelectorAll("[data-address-picker]").forEach(initPicker);
