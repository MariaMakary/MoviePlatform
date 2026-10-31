import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
const FALLBACK_ZOOM = 13;
const USER_ZOOM = 14;

const MESSAGES = {
    locating: 'Finding your location…',
    located: 'Showing the area around you.',
    denied: 'Location is off, so the map shows Beirut. Allow location for this site in your browser, then press "Use my location".',
    unavailable: "Your location couldn't be found, so the map shows Beirut. Try again in a moment.",
    unsupported: "Your browser can't share its location, so the map shows Beirut.",
};

/**
 * Map of the "Cinemas near me" page: OpenStreetMap tiles centered on the browser's location, or on the fallback
 * (Beirut) given by the page when location is refused or unavailable.
 */
export function mountCinemaMap(root) {
    const status = root.querySelector('[data-map-status]');
    const button = root.querySelector('[data-map-locate]');
    const fallback = [Number(root.dataset.fallbackLat), Number(root.dataset.fallbackLng)];
    const color = getComputedStyle(document.documentElement).getPropertyValue('--color-cerulean').trim();

    const map = L.map(root.querySelector('[data-map]')).setView(fallback, FALLBACK_ZOOM);
    L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);

    let here = null;

    const showFallback = (message) => {
        here?.remove();
        here = null;
        map.setView(fallback, FALLBACK_ZOOM);
        status.textContent = message;
    };

    const showPosition = ({ coords }) => {
        const position = [coords.latitude, coords.longitude];

        here?.remove();
        here = L.layerGroup([
            L.circle(position, { radius: coords.accuracy, color, weight: 1, fillOpacity: 0.1, interactive: false }),
            L.circleMarker(position, { radius: 8, color: 'white', weight: 2, fillColor: color, fillOpacity: 1 })
                .bindTooltip('You are here'),
        ]).addTo(map);

        map.setView(position, USER_ZOOM);
        status.textContent = MESSAGES.located;
    };

    const locate = () => {
        if (!('geolocation' in navigator)) {
            showFallback(MESSAGES.unsupported);
            return;
        }

        status.textContent = MESSAGES.locating;
        button.disabled = true;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                button.disabled = false;
                showPosition(position);
            },
            (error) => {
                button.disabled = false;
                showFallback(error.code === error.PERMISSION_DENIED ? MESSAGES.denied : MESSAGES.unavailable);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
        );
    };

    button.addEventListener('click', locate);
    locate();

    return map;
}
