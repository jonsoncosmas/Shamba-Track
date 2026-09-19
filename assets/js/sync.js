/**
 * Shamba Track — Shared offline sync engine (hardened in Phase 8)
 *
 * One place that knows how to push any IndexedDB store's unsynced records
 * to its matching API endpoint. Runs on the 'online' event and once at
 * startup if already online. Screens listen for 'shambatrack:synced' to
 * refresh their own rendered lists after a pass completes.
 *
 * Phase 8 hardening:
 * - A 409 response means the server rejected an update because the record
 *   changed elsewhere since this device last knew about it. That is
 *   recorded as an unresolved conflict, not silently retried or dropped —
 *   ShambaDB.getUnsynced() already excludes conflicted records from future
 *   passes until the farmer resolves it (see vaccinations.js).
 * - A record that fails repeatedly for any OTHER reason (a genuine bug,
 *   a persistent validation error) is capped at MAX_ATTEMPTS and then
 *   marked sync_error instead of being retried forever every time the
 *   device comes online — a stuck record shouldn't silently block or
 *   slow down every future sync pass indefinitely.
 */

const ShambaSync = (function () {
    'use strict';

    const BASE = (typeof window.ST_BASE === 'string') ? window.ST_BASE : '';
    const MAX_ATTEMPTS = 10;

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
        sales: '/sales',
        capital_sources: '/capital-sources',
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
            const data = await res.json().catch(() => ({}));
            return { ok: res.ok, status: res.status, data };
        } catch (e) {
            return { ok: false, status: 0, data: {}, networkError: true };
        }
    }

    async function syncPending() {
        if (inFlight) return;
        if (!navigator.onLine) {
            renderSyncStatus(); // still reflect newly-created pending records even though we can't push them yet
            return;
        }
        inFlight = true;

        try {
            for (const [storeName, endpoint] of Object.entries(STORE_ENDPOINTS)) {
                const unsynced = await ShambaDB.getUnsynced(storeName);
                for (const record of unsynced) {
                    const { ok, status, data } = await apiCall(endpoint, record);

                    if (ok) {
                        record.synced = true;
                        record.attempts = 0;
                        // The server is authoritative for version numbers
                        // (it increments on every accepted update). Adopt
                        // it so the NEXT edit sends the correct expected
                        // version instead of falsely conflicting with itself.
                        if (data.record && typeof data.record.version !== 'undefined') {
                            record.version = data.record.version;
                        }
                        await ShambaDB.put(storeName, record);
                        continue;
                    }

                    if (status === 409) {
                        // Real conflict: someone else's update already landed.
                        // Keep both sides so the farmer can choose — never
                        // silently pick one.
                        record.conflict = {
                            serverRecord: data.server_record || null,
                            detectedAt: new Date().toISOString(),
                        };
                        await ShambaDB.put(storeName, record);
                        continue;
                    }

                    // Any other failure (network error, validation bug, etc.)
                    record.attempts = (record.attempts || 0) + 1;
                    if (record.attempts >= MAX_ATTEMPTS) {
                        record.sync_error = true;
                    }
                    await ShambaDB.put(storeName, record);
                }
            }
        } finally {
            inFlight = false;
            window.dispatchEvent(new CustomEvent('shambatrack:synced'));
        }
    }

    /** Count of records genuinely waiting for connectivity (not stuck). */
    async function countPending() {
        let total = 0;
        for (const storeName of Object.keys(STORE_ENDPOINTS)) {
            total += (await ShambaDB.getUnsynced(storeName)).length;
        }
        return total;
    }

    /** Count of records with an unresolved conflict, across all stores. */
    async function countConflicts() {
        let total = 0;
        for (const storeName of Object.keys(STORE_ENDPOINTS)) {
            const all = await ShambaDB.getAll(storeName);
            total += all.filter((r) => r.conflict).length;
        }
        return total;
    }

    /** Count of records that gave up after MAX_ATTEMPTS — needs attention. */
    async function countSyncErrors() {
        let total = 0;
        for (const storeName of Object.keys(STORE_ENDPOINTS)) {
            const all = await ShambaDB.getAll(storeName);
            total += all.filter((r) => r.sync_error).length;
        }
        return total;
    }

    window.addEventListener('online', syncPending);

    // ---- Dashboard-wide status indicator ----
    // Distinguishes three states a farmer should see differently:
    // "still waiting for connectivity" (normal, no action needed),
    // "needs your decision" (a conflict), and "stuck" (a bug, needs
    // developer/support attention) — conflating these would hide real
    // problems behind normal offline behavior.
    async function renderSyncStatus() {
        const el = document.getElementById('sync-status-banner');
        if (!el) return;

        const [pending, conflicts, errors] = await Promise.all([countPending(), countConflicts(), countSyncErrors()]);

        if (pending === 0 && conflicts === 0 && errors === 0) {
            el.classList.add('hidden');
            el.innerHTML = '';
            return;
        }

        const parts = [];
        if (conflicts > 0) parts.push(`⚠️ ${conflicts} zina mgongano / need your input`);
        if (errors > 0) parts.push(`❌ ${errors} zimeshindwa kutumwa / failed to sync`);
        if (pending > 0) parts.push(`🔄 ${pending} zinasubiri mtandao / waiting for connection`);

        el.innerHTML = parts.join(' · ');
        el.classList.remove('hidden');
        el.classList.toggle('sync-status--attention', conflicts > 0 || errors > 0);
    }

    window.addEventListener('shambatrack:synced', renderSyncStatus);
    document.addEventListener('DOMContentLoaded', renderSyncStatus);

    return { syncPending, countPending, countConflicts, countSyncErrors };
})();
