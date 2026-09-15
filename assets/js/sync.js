/**
 * Shamba Track — Shared offline sync engine
 *
 * One place that knows how to push any IndexedDB store's unsynced records
 * to its matching API endpoint. Runs on the 'online' event and once at
 * startup if already online. Screens listen for 'shambatrack:synced' to
 * refresh their own rendered lists after a pass completes.
 */

const ShambaSync = (function () {
    'use strict';

    const BASE = (typeof window.ST_BASE === 'string') ? window.ST_BASE : '';

    // store name -> API endpoint
    const STORE_ENDPOINTS = {
        batches: '/batches',
        infrastructure: '/infrastructure',
        feed_purchases: '/feed-purchases',
        feed_consumption: '/feed-consumption',
        eggs: '/eggs',
        mortality: '/mortality',
        costs: '/costs',
        vaccinations: '/vaccinations',
    };

    let inFlight = false;

    async function apiCall(path, body) {
        try {
            const res = await fetch(BASE + '/api' + path, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            return { ok: res.ok };
        } catch (e) {
            return { ok: false, networkError: true };
        }
    }

    async function syncPending() {
        if (inFlight || !navigator.onLine) return;
        inFlight = true;

        try {
            for (const [storeName, endpoint] of Object.entries(STORE_ENDPOINTS)) {
                const unsynced = await ShambaDB.getUnsynced(storeName);
                for (const record of unsynced) {
                    const { ok } = await apiCall(endpoint, record);
                    if (ok) {
                        record.synced = true;
                        await ShambaDB.put(storeName, record);
                    }
                }
            }
        } finally {
            inFlight = false;
            window.dispatchEvent(new CustomEvent('shambatrack:synced'));
        }
    }

    window.addEventListener('online', syncPending);

    return { syncPending };
})();
