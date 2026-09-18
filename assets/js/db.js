/**
 * Shamba Track — IndexedDB wrapper
 *
 * This is the offline data layer: every write goes here FIRST, synchronously
 * from the user's perspective (instant UI feedback), regardless of network
 * state. A background sync process (in batches.js) later pushes any
 * unsynced records to the server once a connection is available.
 *
 * Object stores: 'batches', 'infrastructure' — both keyed by client_uuid
 * (generated on-device), each record carries a `synced` boolean.
 */

const ShambaDB = (function () {
    'use strict';

    const DB_NAME = 'shambatrack';
    const DB_VERSION = 4;
    const STORES = ['batches', 'infrastructure', 'feed_purchases', 'feed_consumption', 'eggs', 'mortality', 'costs', 'vaccinations', 'sales'];

    let dbPromise = null;

    function open() {
        if (dbPromise) return dbPromise;

        dbPromise = new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);

            req.onupgradeneeded = () => {
                const db = req.result;
                STORES.forEach((storeName) => {
                    if (!db.objectStoreNames.contains(storeName)) {
                        const store = db.createObjectStore(storeName, { keyPath: 'client_uuid' });
                        store.createIndex('synced', 'synced', { unique: false });
                    }
                });
            };

            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });

        return dbPromise;
    }

    async function put(storeName, record) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            tx.objectStore(storeName).put(record);
            tx.oncomplete = () => resolve(record);
            tx.onerror = () => reject(tx.error);
        });
    }

    async function getAll(storeName) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const req = tx.objectStore(storeName).getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async function getUnsynced(storeName) {
        const all = await getAll(storeName);
        return all.filter((r) => r.synced === false);
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        // Fallback for older browsers/webviews without crypto.randomUUID
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            const v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    return { put, getAll, getUnsynced, uuid };
})();
