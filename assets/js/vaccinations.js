/**
 * Shamba Track — Vaccination schedule & reminders (Phase 4)
 *
 * The schedule TEMPLATE below is static reference data bundled in JS —
 * available offline from first load via the service worker, no network
 * round-trip needed. It is a general-purpose starting point based on
 * commonly used poultry vaccination programs; exact timing varies by
 * region, hatchery, and disease pressure, so the UI reminds farmers to
 * confirm specifics with a local vet or extension officer.
 *
 * The moment a batch is saved (see batches.js's 'shambatrack:batch-created'
 * event), the matching template is instantiated into real due-dated
 * records and written to IndexedDB immediately — fully offline capable.
 */

(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }
    const uuid = ShambaDB.uuid;

    // Layers, kienyeji bora, and sasso are kept long-term for egg
    // production, so they get the fuller early-life schedule. Broilers are
    // typically sold by 6-8 weeks, so their schedule stops earlier.
    const LONG_CYCLE_BREEDS = ['layers', 'kienyeji', 'sasso'];

    const VACCINATION_TEMPLATES = {
        long: [
            { age_days: 7,  disease: 'Newcastle (NCD)', vaccine_name: 'NCD I (HB1/Lasota)', notes: 'Njia ya jicho / Eye drop' },
            { age_days: 14, disease: 'Gumboro (IBD)',    vaccine_name: 'Gumboro I', notes: 'Kwenye maji ya kunywa / In drinking water' },
            { age_days: 21, disease: 'Newcastle (NCD)', vaccine_name: 'NCD II booster (Lasota)', notes: '' },
            { age_days: 28, disease: 'Gumboro (IBD)',    vaccine_name: 'Gumboro II booster', notes: '' },
            { age_days: 35, disease: 'Ndui ya Kuku (Fowl Pox)', vaccine_name: 'Fowl Pox (wing web)', notes: '' },
            { age_days: 63, disease: 'Newcastle (NCD)', vaccine_name: 'NCD III booster', notes: '' },
        ],
        short: [
            { age_days: 7,  disease: 'Newcastle (NCD)', vaccine_name: 'NCD I (HB1/Lasota)', notes: 'Njia ya jicho / Eye drop' },
            { age_days: 14, disease: 'Gumboro (IBD)',    vaccine_name: 'Gumboro I', notes: 'Kwenye maji ya kunywa / In drinking water' },
            { age_days: 21, disease: 'Newcastle (NCD)', vaccine_name: 'NCD II booster (Lasota)', notes: '' },
            { age_days: 28, disease: 'Gumboro (IBD)',    vaccine_name: 'Gumboro II booster', notes: '' },
        ],
    };

    function templateFor(breed) {
        return LONG_CYCLE_BREEDS.includes(breed) ? VACCINATION_TEMPLATES.long : VACCINATION_TEMPLATES.short;
    }

    function addDays(dateStr, days) {
        const d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + days);
        return d.toISOString().slice(0, 10);
    }

    // ---- Auto-generate a batch's schedule the moment it's created ----
    window.addEventListener('shambatrack:batch-created', async (e) => {
        const batch = e.detail;
        const template = templateFor(batch.breed);

        for (const item of template) {
            await ShambaDB.put('vaccinations', {
                client_uuid: uuid(),
                batch_client_uuid: batch.client_uuid,
                disease: item.disease,
                vaccine_name: item.vaccine_name,
                age_days: item.age_days,
                due_date: addDays(batch.date_acquired, item.age_days),
                status: 'pending',
                completed_date: null,
                notes: item.notes || null,
                version: 1,
                synced: false,
            });
        }

        ShambaSync.syncPending();
        renderVaccinationAlertBanner();
    });

    // ---- Status classification for reminders ----
    function classify(record) {
        if (record.status === 'done') return 'done';
        const today = new Date().toISOString().slice(0, 10);
        if (record.due_date < today) return 'overdue';
        if (record.due_date === today) return 'today';
        return 'upcoming';
    }

    const STATUS_LABELS = {
        done: 'Imekamilika / Done',
        overdue: 'Imechelewa / Overdue',
        today: 'Leo / Due today',
        upcoming: 'Ijayo / Upcoming',
    };

    // ---- Dashboard alert banner: count of due-today + overdue across all batches ----
    async function renderVaccinationAlertBanner() {
        const banner = $('vaccination-alert-banner');
        if (!banner) return;

        const all = await ShambaDB.getAll('vaccinations');
        const urgent = all.filter((r) => r.status !== 'done' && classify(r) !== 'upcoming');

        if (urgent.length === 0) {
            banner.classList.add('hidden');
            banner.innerHTML = '';
            delete banner.dataset.batchUuid;
            return;
        }

        // Most urgent = earliest due date (the longest overdue, or today's).
        // That's the batch the tap should take the farmer straight to.
        urgent.sort((a, b) => a.due_date.localeCompare(b.due_date));
        banner.dataset.batchUuid = urgent[0].batch_client_uuid;

        const overdueCount = urgent.filter((r) => classify(r) === 'overdue').length;
        const todayCount = urgent.filter((r) => classify(r) === 'today').length;
        const parts = [];
        if (overdueCount) parts.push(`${overdueCount} zimechelewa`);
        if (todayCount) parts.push(`${todayCount} leo`);

        banner.innerHTML = `💉 Una chanjo ${parts.join(', ')} / You have vaccinations due <span class="tap-hint">— Gusa kuona / Tap to view →</span>`;
        banner.classList.remove('hidden');
    }

    // ---- Per-batch checklist screen ----
    function showAppScreen(id) {
        document.querySelectorAll('.screen').forEach((el) => el.classList.add('hidden'));
        $(id).classList.remove('hidden');
    }

    async function openScheduleForBatch(batchUuid) {
        const [batches, records] = await Promise.all([
            ShambaDB.getAll('batches'),
            ShambaDB.getAll('vaccinations'),
        ]);
        const batch = batches.find((b) => b.client_uuid === batchUuid);
        const schedule = records
            .filter((r) => r.batch_client_uuid === batchUuid)
            .sort((a, b) => a.due_date.localeCompare(b.due_date));

        $('vaccination-batch-label').textContent = batch
            ? `${batch.breed} — ${batch.quantity} birds (${batch.date_acquired})`
            : '';

        renderChecklist(schedule);
        showAppScreen('screen-vaccination-schedule');
    }

    function renderChecklist(schedule) {
        const listEl = $('vaccination-checklist');
        if (!schedule.length) {
            listEl.innerHTML = '<li class="empty-row">Bado hakuna ratiba / No schedule yet</li>';
            return;
        }

        listEl.innerHTML = schedule.map((r) => {
            if (r.conflict) return renderConflictRow(r);

            const status = classify(r);
            const pending = r.synced === false ? ' <span class="pending-badge" title="Bado kutumwa">●</span>' : '';
            const syncError = r.sync_error ? ' <span class="sync-error-badge" title="Imeshindwa kutumwa mara nyingi">❌</span>' : '';
            const doneButton = status !== 'done'
                ? `<button type="button" class="btn-tiny mark-done" data-uuid="${r.client_uuid}">✓ Imekamilika</button>`
                : '';
            return `<li class="vaccination-row vaccination-row--${status}">
                <div>
                    <strong>${r.vaccine_name}</strong> — ${r.disease}<br>
                    <span class="subtext">Tarehe / Due: ${r.due_date} · <span class="status-tag status-tag--${status}">${STATUS_LABELS[status]}</span>${pending}${syncError}</span>
                </div>
                ${doneButton}
            </li>`;
        }).join('');
    }

    // A conflict means this device's local change and the server's current
    // state disagree — likely the same vaccination was marked done from
    // two sessions/devices before either had synced. Show BOTH versions
    // and let the farmer pick, rather than guessing which one is "right".
    function renderConflictRow(r) {
        const server = r.conflict.serverRecord || {};
        return `<li class="vaccination-row vaccination-row--conflict">
            <div class="conflict-box">
                <p class="conflict-title">⚠️ Mgongano wa Taarifa / Conflicting update</p>
                <p><strong>${r.vaccine_name}</strong> — ${r.disease}</p>
                <div class="conflict-columns">
                    <div>
                        <p class="conflict-col-label">Kwenye Kifaa Hiki / On this device</p>
                        <p>${STATUS_LABELS[r.status] || r.status}${r.completed_date ? ` (${r.completed_date})` : ''}</p>
                        <button type="button" class="btn-tiny resolve-conflict" data-uuid="${r.client_uuid}" data-choice="mine">Weka Hii / Keep this</button>
                    </div>
                    <div>
                        <p class="conflict-col-label">Kwenye Seva / On the server</p>
                        <p>${STATUS_LABELS[server.status] || server.status || '—'}${server.completed_date ? ` (${server.completed_date})` : ''}</p>
                        <button type="button" class="btn-tiny resolve-conflict" data-uuid="${r.client_uuid}" data-choice="server">Weka Hii / Keep this</button>
                    </div>
                </div>
            </div>
        </li>`;
    }

    // Delegated click: any "💉 Chanjo" button on the dashboard batch list,
    // or the dashboard alert banner itself
    document.addEventListener('click', (e) => {
        const banner = e.target.closest('#vaccination-alert-banner');
        if (banner && banner.dataset.batchUuid) {
            openScheduleForBatch(banner.dataset.batchUuid);
            return;
        }

        const viewBtn = e.target.closest('.view-vaccinations');
        if (viewBtn) {
            openScheduleForBatch(viewBtn.dataset.batchUuid);
            return;
        }

        const doneBtn = e.target.closest('.mark-done');
        if (doneBtn) { markDone(doneBtn.dataset.uuid); return; }

        const resolveBtn = e.target.closest('.resolve-conflict');
        if (resolveBtn) resolveConflict(resolveBtn.dataset.uuid, resolveBtn.dataset.choice);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const banner = e.target.closest('#vaccination-alert-banner');
        if (banner && banner.dataset.batchUuid) {
            e.preventDefault();
            openScheduleForBatch(banner.dataset.batchUuid);
        }
    });

    async function markDone(clientUuid) {
        const all = await ShambaDB.getAll('vaccinations');
        const record = all.find((r) => r.client_uuid === clientUuid);
        if (!record) return;

        record.status = 'done';
        record.completed_date = new Date().toISOString().slice(0, 10);
        record.synced = false; // must re-sync: this is an update to an already-synced record
        await ShambaDB.put('vaccinations', record);

        const batchUuid = record.batch_client_uuid;
        const schedule = (await ShambaDB.getAll('vaccinations'))
            .filter((r) => r.batch_client_uuid === batchUuid)
            .sort((a, b) => a.due_date.localeCompare(b.due_date));
        renderChecklist(schedule);

        ShambaSync.syncPending();
        renderVaccinationAlertBanner();
    }

    async function resolveConflict(clientUuid, choice) {
        const all = await ShambaDB.getAll('vaccinations');
        const record = all.find((r) => r.client_uuid === clientUuid);
        if (!record || !record.conflict) return;

        const server = record.conflict.serverRecord || {};

        if (choice === 'mine') {
            // Keep this device's status/notes, but adopt the server's
            // current version as the new baseline so the next sync
            // attempt's version check succeeds instead of conflicting
            // against itself again.
            record.version = server.version || record.version;
            record.conflict = null;
            record.synced = false;
            record.attempts = 0;
        } else {
            // Adopt the server's version of events entirely.
            record.status = server.status || record.status;
            record.completed_date = server.completed_date || null;
            record.notes = server.notes || record.notes;
            record.version = server.version || record.version;
            record.conflict = null;
            record.synced = true; // already matches the server, nothing to push
            record.attempts = 0;
        }

        await ShambaDB.put('vaccinations', record);

        const schedule = (await ShambaDB.getAll('vaccinations'))
            .filter((r) => r.batch_client_uuid === record.batch_client_uuid)
            .sort((a, b) => a.due_date.localeCompare(b.due_date));
        renderChecklist(schedule);

        ShambaSync.syncPending();
        renderVaccinationAlertBanner();
    }

    window.addEventListener('shambatrack:synced', renderVaccinationAlertBanner);

    document.addEventListener('DOMContentLoaded', () => {
        try {
            renderVaccinationAlertBanner();
        } catch (err) {
            console.error('[ShambaTrack] vaccinations.js init failed:', err);
        }
    });
})();
