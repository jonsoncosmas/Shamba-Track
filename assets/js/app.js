/**
 * Shamba Track — App shell JS
 * Registers the service worker, reflects online/offline status.
 */

(function () {
    'use strict';

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;

        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register('service-worker.js')
                .then((reg) => console.log('[ShambaTrack] Service worker registered:', reg.scope))
                .catch((err) => console.error('[ShambaTrack] Service worker registration failed:', err));
        });
    }

    function updateConnectionStatus() {
        const banner = document.getElementById('status-banner');
        if (!banner) return;

        if (navigator.onLine) {
            banner.classList.remove('is-visible');
        } else {
            banner.textContent = 'Uko nje ya mtandao — data itahifadhiwa kwenye kifaa chako. (Offline — data will be saved on your device.)';
            banner.classList.add('is-visible');
        }
    }

    window.addEventListener('online', updateConnectionStatus);
    window.addEventListener('offline', updateConnectionStatus);
    document.addEventListener('DOMContentLoaded', updateConnectionStatus);

    registerServiceWorker();
})();
