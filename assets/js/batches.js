/**
 * Shamba Track — Batch & infrastructure logging (Phase 2)
 *
 * Core offline behavior: every save writes to IndexedDB FIRST — the user
 * sees it immediately regardless of connection. A sync pass then tries to
 * push unsynced records to the server, retried whenever the app comes
 * back online. This file assumes db.js and auth.js are already loaded.
 */

(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }
    const uuid = ShambaDB.uuid;

    const BREED_LABELS = {
        layers: 'Layers / Mayai',
        broilers: 'Broilers / Nyama',
        kienyeji: 'Kienyeji Bora',
        sasso: 'Sasso',
    };

    const CATEGORY_LABELS = {
        coop: 'Banda / Coop',
        land: 'Shamba / Land',
        equipment: 'Vifaa / Equipment',
    };

    // ---- React to sync completing (triggered from sync.js) ----
    window.addEventListener('shambatrack:synced', renderDashboardLists);

    // ---- Dashboard rendering ----
    async function renderDashboardLists() {
        const batchesEl = $('dashboard-batches-list');
        const infraEl = $('dashboard-infra-list');
        const capitalEl = $('dashboard-capital-list');
        if (!batchesEl || !infraEl) return;

        const batches = (await ShambaDB.getAll('batches')).sort((a, b) => b.date_acquired.localeCompare(a.date_acquired));
        const infra = (await ShambaDB.getAll('infrastructure')).sort((a, b) => b.date_incurred.localeCompare(a.date_incurred));
        const capital = (await ShambaDB.getAll('capital_sources')).sort((a, b) => b.date_received.localeCompare(a.date_received));

        batchesEl.innerHTML = batches.length
            ? batches.map(renderBatchRow).join('')
            : '<li class="empty-row">Bado hakuna kundi / No batches yet</li>';

        infraEl.innerHTML = infra.length
            ? infra.map(renderInfraRow).join('')
            : '<li class="empty-row">Bado hakuna gharama / No costs logged yet</li>';

        if (capitalEl) {
            capitalEl.innerHTML = capital.length
                ? capital.map(renderCapitalRow).join('')
                : '<li class="empty-row">Bado hakuna mtaji uliorekodiwa / No capital logged yet</li>';
        }
    }

    function renderBatchRow(b) {
        const pending = b.synced ? '' : ' <span class="pending-badge" title="Bado kutumwa / Not synced yet">●</span>';
        return `<li class="batch-row">
            <span><strong>${BREED_LABELS[b.breed] || b.breed}</strong> — ${b.quantity} birds · ${b.date_acquired}${pending}</span>
            <span class="batch-row__actions">
                <button type="button" class="btn-tiny view-vaccinations" data-batch-uuid="${b.client_uuid}">💉 Chanjo</button>
                <button type="button" class="btn-tiny view-insights" data-batch-uuid="${b.client_uuid}">📊 Takwimu</button>
            </span>
        </li>`;
    }

    function renderInfraRow(i) {
        const pending = i.synced ? '' : ' <span class="pending-badge" title="Bado kutumwa / Not synced yet">●</span>';
        const landNote = i.land_status ? ` (${i.land_status})` : '';
        return `<li><strong>${i.item_name}</strong>${landNote} — ${CATEGORY_LABELS[i.category] || i.category} · ${i.date_incurred}${pending}</li>`;
    }

    const CAPITAL_LABELS = { loan: 'Mkopo / Loan', salary: 'Mshahara / Salary', freelance: 'Freelance', savings: 'Akiba / Savings', other: 'Nyingine / Other' };

    function renderCapitalRow(c) {
        const pending = c.synced ? '' : ' <span class="pending-badge" title="Bado kutumwa / Not synced yet">●</span>';
        const interestNote = c.interest_rate ? ` (riba ${c.interest_rate}%)` : '';
        return `<li><strong>${CAPITAL_LABELS[c.source_type] || c.source_type}</strong>${interestNote} — ${c.amount} · ${c.date_received}${pending}</li>`;
    }

    // ---- Screen navigation helpers (reuses .screen/.hidden convention from auth.js) ----
    function showAppScreen(id) {
        document.querySelectorAll('.screen').forEach((el) => el.classList.add('hidden'));
        $(id).classList.remove('hidden');
    }

    function bindNav() {
        $('btn-add-batch')?.addEventListener('click', () => {
            $('form-add-batch').reset();
            document.querySelectorAll('.breed-option').forEach((el) => el.classList.remove('is-selected'));
            $('input-batch-breed').value = '';
            setError('error-add-batch', '');
            showAppScreen('screen-add-batch');
        });

        $('btn-add-infrastructure')?.addEventListener('click', () => {
            $('form-add-infrastructure').reset();
            document.querySelectorAll('.category-option').forEach((el) => el.classList.remove('is-selected'));
            $('input-infra-category').value = '';
            $('land-status-field').classList.add('hidden');
            setError('error-add-infrastructure', '');
            showAppScreen('screen-add-infrastructure');
        });

        $('btn-add-capital')?.addEventListener('click', () => {
            $('form-add-capital').reset();
            $('capital-source-picker').querySelectorAll('.category-option').forEach((el) => el.classList.remove('is-selected'));
            $('input-capital-type').value = '';
            $('capital-interest-field').classList.add('hidden');
            setError('error-add-capital', '');
            showAppScreen('screen-add-capital');
        });

        $('btn-open-reports')?.addEventListener('click', () => {
            showAppScreen('screen-reports');
            window.dispatchEvent(new CustomEvent('shambatrack:open-reports'));
        });

        $('capital-source-picker')?.addEventListener('click', (e) => {
            const card = e.target.closest('.category-option');
            if (!card) return;
            $('capital-source-picker').querySelectorAll('.category-option').forEach((el) => el.classList.remove('is-selected'));
            card.classList.add('is-selected');
            const sourceType = card.dataset.sourceType;
            $('input-capital-type').value = sourceType;
            $('capital-interest-field').classList.toggle('hidden', sourceType !== 'loan');
        });

        $('form-add-capital')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-add-capital', '');

            const sourceType = $('input-capital-type').value;
            const amount = parseFloat($('input-capital-amount').value || '0');
            const interestRate = $('input-capital-interest').value ? parseFloat($('input-capital-interest').value) : null;
            const date = $('input-capital-date').value;
            const notes = $('input-capital-notes').value.trim();

            if (!sourceType) { setError('error-add-capital', 'Chagua chanzo. / Select a source.'); return; }
            if (!amount || amount <= 0) { setError('error-add-capital', 'Weka kiasi sahihi. / Enter a valid amount.'); return; }
            if (!date) { setError('error-add-capital', 'Weka tarehe. / Enter a date.'); return; }

            await ShambaDB.put('capital_sources', {
                client_uuid: uuid(), source_type: sourceType, amount,
                interest_rate: sourceType === 'loan' ? interestRate : null,
                date_received: date, notes: notes || null, synced: false,
            });
            showAppScreen('screen-dashboard');
            renderDashboardLists();
            ShambaSync.syncPending();
        });

        document.querySelectorAll('[data-back-to-dashboard]').forEach((btn) => {
            btn.addEventListener('click', () => showAppScreen('screen-dashboard'));
        });
    }

    function setError(id, msg) {
        const el = $(id);
        if (el) el.textContent = msg || '';
    }

    // ---- Breed picker (tap cards, not a dropdown — low-typing UX) ----
    function bindBreedPicker() {
        const group = $('breed-picker');
        if (!group) return;
        group.addEventListener('click', (e) => {
            const card = e.target.closest('.breed-option');
            if (!card) return;
            group.querySelectorAll('.breed-option').forEach((el) => el.classList.remove('is-selected'));
            card.classList.add('is-selected');
            $('input-batch-breed').value = card.dataset.breed;
        });
    }

    // ---- Category picker (coop / land / equipment) ----
    function bindCategoryPicker() {
        const group = $('category-picker');
        if (!group) return;
        group.addEventListener('click', (e) => {
            const card = e.target.closest('.category-option');
            if (!card) return;
            group.querySelectorAll('.category-option').forEach((el) => el.classList.remove('is-selected'));
            card.classList.add('is-selected');
            $('input-infra-category').value = card.dataset.category;
            $('land-status-field').classList.toggle('hidden', card.dataset.category !== 'land');
        });
    }

    // ---- Form: add batch ----
    function bindBatchForm() {
        const form = $('form-add-batch');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-add-batch', '');

            const breed = $('input-batch-breed').value;
            const quantity = parseInt($('input-batch-quantity').value, 10);
            const dateAcquired = $('input-batch-date').value;
            const costPerBird = parseFloat($('input-batch-cost').value || '0');
            const source = $('input-batch-source').value.trim();
            const notes = $('input-batch-notes').value.trim();

            if (!breed) { setError('error-add-batch', 'Chagua aina ya kuku. / Select a breed.'); return; }
            if (!quantity || quantity < 1) { setError('error-add-batch', 'Weka idadi sahihi. / Enter a valid quantity.'); return; }
            if (!dateAcquired) { setError('error-add-batch', 'Weka tarehe. / Enter a date.'); return; }

            const record = {
                client_uuid: uuid(),
                breed, quantity,
                date_acquired: dateAcquired,
                cost_per_bird: costPerBird,
                source: source || null,
                notes: notes || null,
                synced: false,
            };

            // Write locally FIRST — this is what makes it work offline.
            await ShambaDB.put('batches', record);

            // Let anything interested (currently: vaccinations.js) react to
            // a new batch without batches.js needing to know it exists.
            window.dispatchEvent(new CustomEvent('shambatrack:batch-created', { detail: record }));

            showAppScreen('screen-dashboard');
            renderDashboardLists();

            // Then try to sync immediately if online (no-op, safely retried later, if not).
            ShambaSync.syncPending();
        });
    }

    // ---- Form: add infrastructure/equipment cost ----
    function bindInfrastructureForm() {
        const form = $('form-add-infrastructure');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-add-infrastructure', '');

            const category = $('input-infra-category').value;
            const itemName = $('input-infra-name').value.trim();
            const landStatus = $('input-infra-land-status').value || null;
            const amount = parseFloat($('input-infra-amount').value || '0');
            const dateIncurred = $('input-infra-date').value;
            const notes = $('input-infra-notes').value.trim();

            if (!category) { setError('error-add-infrastructure', 'Chagua aina. / Select a category.'); return; }
            if (!itemName) { setError('error-add-infrastructure', 'Weka jina la kipengele. / Enter an item name.'); return; }
            if (category === 'land' && !landStatus) { setError('error-add-infrastructure', 'Chagua umenunua au kupanga. / Specify bought or rented.'); return; }
            if (!dateIncurred) { setError('error-add-infrastructure', 'Weka tarehe. / Enter a date.'); return; }

            const record = {
                client_uuid: uuid(),
                category,
                item_name: itemName,
                land_status: category === 'land' ? landStatus : null,
                amount,
                date_incurred: dateIncurred,
                notes: notes || null,
                synced: false,
            };

            await ShambaDB.put('infrastructure', record);
            showAppScreen('screen-dashboard');
            renderDashboardLists();
            ShambaSync.syncPending();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        try {
            bindNav();
            bindBreedPicker();
            bindCategoryPicker();
            bindBatchForm();
            bindInfrastructureForm();
            renderDashboardLists();
            if (navigator.onLine) ShambaSync.syncPending();
        } catch (err) {
            console.error('[ShambaTrack] batches.js init failed:', err);
        }
    });
})();
