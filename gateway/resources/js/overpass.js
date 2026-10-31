// OpenStreetMap data through the public Overpass API (https://wiki.openstreetmap.org/wiki/Overpass_API).
const ENDPOINT = 'https://overpass-api.de/api/interpreter';
const TIMEOUT_MS = 25000;

/**
 * Cinemas (amenity=cinema) within `radius` meters of `[lat, lng]`, unsorted.
 * Throws when Overpass is unreachable, slow or answers with an error; an abort of `signal` rethrows the AbortError.
 *
 * @returns {Promise<Array<{id: string, name: string, address: ?string, position: [number, number]}>>}
 */
export async function findCinemas([lat, lng], radius, signal) {
    const query = `[out:json][timeout:20];nwr["amenity"="cinema"](around:${radius},${lat},${lng});out center tags;`;

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
    signal.addEventListener('abort', () => controller.abort(), { once: true });

    try {
        const response = await fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ data: query }),
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Overpass answered ${response.status}`);
        }

        const json = await response.json();

        // A query that runs out of time still answers 200, with the problem in "remark".
        if (json.remark?.includes('error')) {
            throw new Error(json.remark);
        }

        return json.elements.map(toCinema).filter(Boolean);
    } finally {
        clearTimeout(timer);
    }
}

// Cinemas can be mapped as a point (node) or as a building (way, relation): buildings come with their center.
function toCinema(element) {
    const lat = element.lat ?? element.center?.lat;
    const lng = element.lon ?? element.center?.lon;

    if (lat === undefined || lng === undefined) {
        return null;
    }

    const tags = element.tags ?? {};

    return {
        id: `${element.type}/${element.id}`,
        name: tags['name:en'] ?? tags.name ?? 'Unnamed cinema',
        address: address(tags),
        position: [lat, lng],
    };
}

function address(tags) {
    if (tags['addr:full']) {
        return tags['addr:full'];
    }

    const street = [tags['addr:housenumber'], tags['addr:street']].filter(Boolean).join(' ');
    const parts = [street, tags['addr:suburb'] ?? tags['addr:place'], tags['addr:city']].filter(Boolean);

    return parts.length ? [...new Set(parts)].join(', ') : null;
}
