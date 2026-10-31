import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { findPlace } from './nominatim';
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
    lookingUp: (query) => `Looking up "${query}"…`,
    place: (name) => `Showing the area around ${name}.`,
    point: 'Showing the area around the point you chose. Click elsewhere on the map to move it.',
    notFound: (query) => `No place called "${query}" was found. Try another name, or click on the map.`,
    placeFailed: "The place search isn't responding right now. Try again in a moment, or click on the map.",
    searching: 'Searching for cinemas…',
    none: (km) => `No cinemas found within ${km} km.`,
    found: (count, km) => `${count} ${count === 1 ? 'cinema' : 'cinemas'} within ${km} km, nearest first.`,
    failed: "Cinemas can't be loaded right now: the OpenStreetMap search isn't responding. Try again in a moment.",
};

/**
 * Map of the "Cinemas near me" page: OpenStreetMap tiles centered on the browser's location, or on the fallback
 * (Beirut) given by the page when location is refused or unavailable, with the cinemas around that point as markers
 * and as a list sorted by distance. The user can move that point with a place search or a click on the map.
 */
export function mountCinemaMap(root) {
    const status = root.querySelector('[data-map-status]');
    const button = root.querySelector('[data-map-locate]');
    const cinemaStatus = root.querySelector('[data-cinemas-status]');
    const list = root.querySelector('[data-cinemas-list]');
    const retry = root.querySelector('[data-cinemas-retry]');
    const placeForm = root.querySelector('[data-place-search]');
    const placeInput = placeForm.querySelector('input');
    const placeButton = placeForm.querySelector('button[type="submit"]');
    const fallback = [Number(root.dataset.fallbackLat), Number(root.dataset.fallbackLng)];
    const radius = Number(root.dataset.radius);
    const km = radius / 1000;
    const theme = getComputedStyle(document.documentElement);
    const color = theme.getPropertyValue('--color-cerulean').trim();
    const accent = theme.getPropertyValue('--color-neon-rose').trim();

    const map = L.map(root.querySelector('[data-map]')).setView(fallback, FALLBACK_ZOOM);
    L.tileLayer(TILES, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);

    const cinemaLayer = L.layerGroup().addTo(map);
    let here = null; // marker of the point cinemas are searched around
    let center = null;
    let search = null; // running Overpass search
    let placeLookup = null; // running Nominatim search
    let choice = 0;

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

    // Moves the point cinemas are searched around. `label` adds a marker there (none for the Beirut fallback).
    const setCenter = (position, { message, label = null, markerColor = color, accuracy = null, zoom = USER_ZOOM }) => {
        center = position;

        here?.remove();
        here = null;

        if (label) {
            const marker = L.circleMarker(position, {
                radius: 8, color: 'white', weight: 2, fillColor: markerColor, fillOpacity: 1, bubblingMouseEvents: false,
            }).bindTooltip(element('span', '', label)); // never a string: Leaflet would insert it as HTML
            here = L.layerGroup(accuracy
                ? [L.circle(position, { radius: accuracy, color: markerColor, weight: 1, fillOpacity: 0.1, interactive: false }), marker]
                : [marker]).addTo(map);
        }

        map.setView(position, zoom);
        status.textContent = message;
        searchCinemas();
    };

    // Each choice (browser location, place search, map click) cancels the ones still running,
    // so a late answer can never move the map away from a newer choice.
    const newChoice = () => {
        placeLookup?.abort();
        button.disabled = false;
        placeButton.disabled = false;

        return ++choice;
    };

    const showFallback = (message) => setCenter(fallback, { message, zoom: FALLBACK_ZOOM });

    const locate = () => {
        const current = newChoice();

        if (!('geolocation' in navigator)) {
            showFallback(MESSAGES.unsupported);
            return;
        }

        status.textContent = MESSAGES.locating;
        button.disabled = true;

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                if (current !== choice) {
                    return;
                }

                button.disabled = false;
                setCenter([coords.latitude, coords.longitude], { message: MESSAGES.located, label: 'You are here', accuracy: coords.accuracy });
            },
            (error) => {
                if (current !== choice) {
                    return;
                }

                button.disabled = false;
                showFallback(error.code === error.PERMISSION_DENIED ? MESSAGES.denied : MESSAGES.unavailable);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
        );
    };

    const searchPlace = async (event) => {
        event.preventDefault();
        const query = placeInput.value.trim();

        if (!query) {
            placeInput.focus();
            return;
        }

        const current = newChoice();
        placeLookup = new AbortController();
        const { signal } = placeLookup;

        placeButton.disabled = true;
        status.textContent = MESSAGES.lookingUp(query);

        try {
            const place = await findPlace(query, signal);

            if (!place) {
                status.textContent = MESSAGES.notFound(query);
                return;
            }

            setCenter(place.position, { message: MESSAGES.place(place.name), label: place.name, markerColor: accent });
        } catch (error) {
            if (signal.aborted) {
                return; // a newer choice replaced this one
            }

            console.warn('Nominatim search failed', error);
            status.textContent = MESSAGES.placeFailed;
        } finally {
            if (current === choice) {
                placeButton.disabled = false;
            }
        }
    };

    const choosePoint = ({ latlng }) => {
        newChoice();
        setCenter([latlng.lat, latlng.lng], { message: MESSAGES.point, label: 'Chosen location', markerColor: accent, zoom: map.getZoom() });
    };

    button.addEventListener('click', locate);
    retry.addEventListener('click', searchCinemas);
    placeForm.addEventListener('submit', searchPlace);
    map.on('click', choosePoint);
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
