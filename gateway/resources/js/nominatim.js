// Place search through OpenStreetMap's public Nominatim geocoder (https://nominatim.org/release-docs/latest/api/Search/).
// Usage policy: at most 1 request per second and no search-as-you-type, so only call this when a form is submitted.
const ENDPOINT = 'https://nominatim.openstreetmap.org/search';
const TIMEOUT_MS = 10000;

/**
 * The best match for `query` (a city, area or address), or null when nothing matches.
 * Throws when Nominatim is unreachable, slow or answers with an error; an abort of `signal` rethrows the AbortError.
 *
 * @returns {Promise<?{name: string, position: [number, number]}>}
 */
export async function findPlace(query, signal) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
    signal.addEventListener('abort', () => controller.abort(), { once: true });

    try {
        const url = `${ENDPOINT}?${new URLSearchParams({ q: query, format: 'jsonv2', limit: '1' })}`;
        const response = await fetch(url, { signal: controller.signal });

        if (!response.ok) {
            throw new Error(`Nominatim answered ${response.status}`);
        }

        const [place] = await response.json();

        return place ? { name: place.name || place.display_name, position: [Number(place.lat), Number(place.lon)] } : null;
    } finally {
        clearTimeout(timer);
    }
}
