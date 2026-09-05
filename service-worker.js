/**
 * Shamba Track — Service Worker
 * Phase 0: caches the static app shell so the app opens with no connection.
 * Phase 3+ will add IndexedDB-backed data caching and background sync.
 */

const CACHE_VERSION = 'shamba-track-shell-v1';

const SHELL_ASSETS = [
    '/',
    '/index.php',
    '/offline.html',
    '/manifest.webmanifest',
    '/assets/css/app.css',
    '/assets/js/app.js',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
];

// --- Install: pre-cache the shell ---
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

// --- Activate: drop old cache versions ---
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

// --- Fetch strategy ---
// Shell/static assets: cache-first (fast, works offline).
// API calls (/api/...): network-first, no fallback here — Phase 3's sync
// engine owns offline writes via IndexedDB, not the service worker cache.
self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return; // never cache non-GET; writes go through the sync queue
    }

    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
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
                        return caches.match('/offline.html');
                    }
                });
        })
    );
});
