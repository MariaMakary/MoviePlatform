// Place and cinema search through OpenStreetMap's public Nominatim geocoder (https://nominatim.org/release-docs/latest/api/Search/).
// Usage policy: at most 1 request per second and no search-as-you-type, so only call this when the chosen location changes.
const ENDPOINT = 'https://nominatim.openstreetmap.org/search';
const TIMEOUT_MS = 10000;
const MIN_INTERVAL_MS = 1000;
const METERS_PER_DEGREE = 111320;
const MAX_RESULTS = 40; // Nominatim's own maximum

let nextSlot = 0; // earliest time the next request may start, shared by every search on the page

/**
 * The best match for `query` (a city, area or address), or null when nothing matches.
 * Throws when Nominatim is unreachable, slow or answers with an error; an abort of `signal` rethrows the AbortError.
 *
 * @returns {Promise<?{name: string, position: [number, number]}>}
 */
export async function findPlace(query, signal) {
    const [place] = await search({ q: query, limit: '1' }, signal);

    return place ? { name: place.name || place.display_name, position: [Number(place.lat), Number(place.lon)] } : null;
}

/**
 * Cinemas (amenity=cinema) in the square of side 2 × `radius` meters around `[lat, lng]`, unsorted: the caller keeps
 * the ones within the radius. Throws like findPlace().
 *
 * @returns {Promise<Array<{id: string, name: string, address: ?string, position: [number, number]}>>}
 */
export async function findCinemas([lat, lng], radius, signal) {
    const dLat = radius / METERS_PER_DEGREE;
    const dLng = radius / (METERS_PER_DEGREE * Math.cos(lat * Math.PI / 180));

    const places = await search({
        amenity: 'cinema',
        viewbox: [lng - dLng, lat + dLat, lng + dLng, lat - dLat].join(','),
        bounded: '1',
        limit: String(MAX_RESULTS),
        addressdetails: '1',
    }, signal);

    return places.map((place) => ({
        id: `${place.osm_type}/${place.osm_id}`,
        name: place.name || 'Unnamed cinema',
        address: address(place.address ?? {}),
        position: [Number(place.lat), Number(place.lon)],
    }));
}

async function search(params, signal) {
    const wait = Math.max(0, nextSlot - Date.now());
    nextSlot = Date.now() + wait + MIN_INTERVAL_MS;

    if (wait) {
        await new Promise((resolve) => setTimeout(resolve, wait));
    }

    signal.throwIfAborted();

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
    signal.addEventListener('abort', () => controller.abort(), { once: true });

    try {
        // English names when OpenStreetMap has them: many Lebanese places are otherwise in Arabic.
        const query = new URLSearchParams({ ...params, format: 'jsonv2', 'accept-language': 'en' });
        const response = await fetch(`${ENDPOINT}?${query}`, { signal: controller.signal });

        if (!response.ok) {
            throw new Error(`Nominatim answered ${response.status}`);
        }

        return await response.json();
    } finally {
        clearTimeout(timer);
    }
}

function address(details) {
    const street = [details.house_number, details.road].filter(Boolean).join(' ');
    const parts = [street, details.suburb, details.city ?? details.town ?? details.village].filter(Boolean);

    return parts.length ? [...new Set(parts)].join(', ') : null;
}
