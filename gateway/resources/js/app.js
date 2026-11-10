import './bootstrap';

// Leaflet is downloaded only on pages that have a map.
const mountCinemaMaps = () => {
    document.querySelectorAll('[data-cinema-map]:not([data-mounted])').forEach((root) => {
        root.dataset.mounted = 'true';
        import('./cinema-map').then(({ mountCinemaMap }) => mountCinemaMap(root));
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountCinemaMaps);
} else {
    mountCinemaMaps();
}
document.addEventListener('livewire:navigated', mountCinemaMaps);
