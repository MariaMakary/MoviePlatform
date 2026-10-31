import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { findCinemas } from './overpass';

// Leaflet guesses its marker images' path from its CSS, which breaks once Vite renames them: give the bundled URLs.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
const FALLBACK_ZOOM = 13;
const USER_ZOOM = 14;
const CINEMA_ZOOM = 16;

const MESSAGES = {
    locating: 'Finding your location…',
    located: 'Showing the area around you.',
    denied: 'Location is off, so the map shows Beirut. Allow location for this site in your browser, then press "Use my location".',
    unavailable: "Your location couldn't be found, so the map shows Beirut. Try again in a moment.",
    unsupported: "Your browser can't share its location, so the map shows Beirut.",
    searching: 'Searching for cinemas…',
    none: (km) => `No cinemas found within ${km} km.`,
    found: (count, km) => `${count} ${count === 1 ? 'cinema' : 'cinemas'} within ${km} km, nearest first.`,
    failed: "Cinemas can't be loaded right now: the OpenStreetMap search isn't responding. Try again in a moment.",
};

/**
 * Map of the "Cinemas near me" page: OpenStreetMap tiles centered on the browser's location, or on the fallback
 * (Beirut) given by the page when location is refused or unavailable, with the cinemas around that point as markers
 * and as a list sorted by distance.
 */
export function mountCinemaMap(root) {
    const status = root.querySelector('[data-map-status]');
    const button = root.querySelector('[data-map-locate]');
    const cinemaStatus = root.querySelector('[data-cinemas-status]');
    const list = root.querySelector('[data-cinemas-list]');
    const retry = root.querySelector('[data-cinemas-retry]');
    const fallback = [Number(root.dataset.fallbackLat), Number(root.dataset.fallbackLng)];
    const radius = Number(root.dataset.radius);
    const km = radius / 1000;
    const color = getComputedStyle(document.documentElement).getPropertyValue('--color-cerulean').trim();

    const map = L.map(root.querySelector('[data-map]')).setView(fallback, FALLBACK_ZOOM);
    L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);

    const cinemaLayer = L.layerGroup().addTo(map);
    let here = null;
    let center = null;
    let search = null;

    const showCinemas = (cinemas) => {
        cinemaStatus.textContent = cinemas.length ? MESSAGES.found(cinemas.length, km) : MESSAGES.none(km);

        for (const cinema of cinemas) {
            const marker = L.marker(cinema.position, { title: cinema.name }).bindPopup(popup(cinema)).addTo(cinemaLayer);

            list.append(listItem(cinema, () => {
                map.setView(cinema.position, Math.max(map.getZoom(), CINEMA_ZOOM));
                marker.openPopup();
                map.getContainer().scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }));
        }

        if (cinemas.length) {
            map.fitBounds(L.latLngBounds([center, ...cinemas.map((cinema) => cinema.position)]), { padding: [32, 32], maxZoom: USER_ZOOM });
        }
    };

    const searchCinemas = async () => {
        search?.abort();
        search = new AbortController();
        const { signal } = search;

        cinemaLayer.clearLayers();
        list.replaceChildren();
        retry.hidden = true;
        cinemaStatus.textContent = MESSAGES.searching;

        try {
            const from = L.latLng(center);
            const cinemas = (await findCinemas(center, radius, signal))
                .map((cinema) => ({ ...cinema, distance: from.distanceTo(cinema.position) }))
                .sort((a, b) => a.distance - b.distance);

            showCinemas(cinemas);
        } catch (error) {
            if (signal.aborted) {
                return; // a newer search replaced this one
            }

            console.warn('Overpass search failed', error);
            cinemaStatus.textContent = MESSAGES.failed;
            retry.hidden = false;
        }
    };

    const showFallback = (message) => {
        here?.remove();
        here = null;
        center = fallback;
        map.setView(fallback, FALLBACK_ZOOM);
        status.textContent = message;
        searchCinemas();
    };

    const showPosition = ({ coords }) => {
        center = [coords.latitude, coords.longitude];

        here?.remove();
        here = L.layerGroup([
            L.circle(center, { radius: coords.accuracy, color, weight: 1, fillOpacity: 0.1, interactive: false }),
            L.circleMarker(center, { radius: 8, color: 'white', weight: 2, fillColor: color, fillOpacity: 1 })
                .bindTooltip('You are here'),
        ]).addTo(map);

        map.setView(center, USER_ZOOM);
        status.textContent = MESSAGES.located;
        searchCinemas();
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
    retry.addEventListener('click', searchCinemas);
    locate();

    return map;
}

function formatDistance(meters) {
    return meters < 1000 ? `${Math.round(meters / 10) * 10} m` : `${(meters / 1000).toFixed(1)} km`;
}

// OpenStreetMap text is written by anyone: always set it with textContent, never as HTML.
function element(tag, className, text) {
    const el = document.createElement(tag);
    el.className = className;

    if (text !== undefined) {
        el.textContent = text;
    }

    return el;
}

function popup(cinema) {
    const el = element('div', 'space-y-0.5');
    el.append(
        element('strong', 'block text-sm', cinema.name),
        element('span', 'block', cinema.address ?? 'No address listed'),
        element('span', 'block text-gray-500', `${formatDistance(cinema.distance)} away`),
    );

    return el;
}

function listItem(cinema, onSelect) {
    const button = element('button', 'w-full px-4 py-3 text-left transition hover:bg-white/5 focus-visible:bg-white/5 focus-visible:outline-none');
    button.type = 'button';

    const heading = element('span', 'flex items-baseline justify-between gap-3');
    heading.append(
        element('span', 'font-medium text-white', cinema.name),
        element('span', 'shrink-0 text-xs text-gray-400', formatDistance(cinema.distance)),
    );
    button.append(heading, element('span', 'mt-0.5 block text-sm text-gray-400', cinema.address ?? 'No address listed'));
    button.addEventListener('click', onSelect);

    const item = element('li', '');
    item.append(button);

    return item;
}
