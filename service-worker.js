/**
 * Shamba Track — Service Worker
 * Mount-path aware: self.location.pathname is this script's own URL, so we
 * derive BASE from it instead of hardcoding '/'. Works whether served from
 * a domain root (production) or a local subfolder (dev) automatically.
 */

const CACHE_VERSION = 'shamba-track-shell-v10';

const BASE = self.location.pathname.replace(/service-worker\.js$/, '');

const SHELL_ASSETS = [
    BASE,
    BASE + 'index.php',
    BASE + 'offline.html',
    BASE + 'manifest.webmanifest',
    BASE + 'assets/css/app.css',
    BASE + 'assets/js/app.js',
    BASE + 'assets/js/db.js',
    BASE + 'assets/js/sync.js',
    BASE + 'assets/js/auth.js',
    BASE + 'assets/js/batches.js',
    BASE + 'assets/js/logs.js',
    BASE + 'assets/js/vaccinations.js',
    BASE + 'assets/js/insights.js',
    BASE + 'assets/icons/icon-192.png',
    BASE + 'assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_VERSION)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.pathname.startsWith(BASE + 'api/')) {
        event.respondWith(
            fetch(request).catch(() =>
                new Response(
                    JSON.stringify({ error: 'offline', message: 'No connection — request queued locally.' }),
                    { status: 503, headers: { 'Content-Type': 'application/json' } }
                )
            )
        );
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;

            return fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => {
                    if (request.mode === 'navigate') {
                        return caches.match(BASE + 'offline.html');
                    }
                });
        })
    );
});
