/**
 * Shamba Track — Daily operational logging (Phase 3)
 * Feed purchases/consumption, eggs, mortality, and other costs (labor,
 * utilities, medication, transport). Same offline-first pattern as
 * batches.js: write to IndexedDB immediately, sync opportunistically.
 */

(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }
    const uuid = ShambaDB.uuid;

    const BREED_LABELS = {
        layers: 'Layers', broilers: 'Broilers', kienyeji: 'Kienyeji Bora', sasso: 'Sasso',
    };

    const COST_LABEL_PROMPTS = {
        labor: 'Jina la Mfanyakazi / Worker name',
        utilities: 'Aina ya Huduma / Utility type (e.g. Maji, Umeme)',
        medication: 'Jina la Dawa / Medicine name (e.g. Amporium, Fluban)',
        transport: 'Maelezo ya Usafiri / Transport description',
    };

    function setError(id, msg) {
        const el = $(id);
        if (el) el.textContent = msg || '';
    }

    function showAppScreen(id) {
        document.querySelectorAll('.screen').forEach((el) => el.classList.add('hidden'));
        $(id).classList.remove('hidden');
    }

    function todayISO() {
        return new Date().toISOString().slice(0, 10);
    }

    // ---- Batch selectors: populate every <select class="batch-select"> from IndexedDB ----
    async function populateBatchSelectors() {
        const batches = (await ShambaDB.getAll('batches')).sort((a, b) => b.date_acquired.localeCompare(a.date_acquired));
        document.querySelectorAll('select.batch-select').forEach((select) => {
            const placeholder = select.querySelector('option[value=""]');
            select.innerHTML = '';
            if (placeholder) select.appendChild(placeholder);
            batches.forEach((b) => {
                const opt = document.createElement('option');
                opt.value = b.client_uuid;
                opt.textContent = `${BREED_LABELS[b.breed] || b.breed} — ${b.quantity} (${b.date_acquired})`;
                select.appendChild(opt);
            });
        });
    }

    // ---- Navigation ----
    function bindNav() {
        $('btn-open-daily-log')?.addEventListener('click', () => {
            populateBatchSelectors();
            renderRecentActivity();
            showAppScreen('screen-daily-log');
        });

        document.querySelectorAll('[data-open-log]').forEach((tile) => {
            tile.addEventListener('click', () => {
                const target = tile.dataset.openLog;
                resetLogForm(target);
                showAppScreen('screen-log-' + target);
            });
        });

        document.querySelectorAll('[data-back-to-daily-log]').forEach((btn) => {
            btn.addEventListener('click', () => showAppScreen('screen-daily-log'));
        });
    }

    function resetLogForm(target) {
        const formMap = {
            'feed-purchase': 'form-log-feed-purchase',
            'feed-consumption': 'form-log-feed-consumption',
            'eggs': 'form-log-eggs',
            'mortality': 'form-log-mortality',
            'cost': 'form-log-cost',
            'sale': 'form-log-sale',
        };
        const form = $(formMap[target]);
        if (form) form.reset();

        form?.querySelectorAll('.mini-option, .category-option').forEach((el) => el.classList.remove('is-selected'));
        setError('error-log-' + target, '');

        // Sensible defaults
        const dateField = form?.querySelector('input[type="date"]');
        if (dateField) dateField.value = todayISO();

        if (target === 'feed-purchase') {
            $('input-feedpurchase-source').value = 'bought';
            form.querySelector('.mini-option[data-value="bought"]')?.classList.add('is-selected');
            $('input-feedpurchase-feedtype').value = '';
        }
        if (target === 'mortality') $('input-mortality-cause').value = '';
        if (target === 'cost') {
            $('input-cost-category').value = '';
            $('labor-subtype-field').classList.add('hidden');
            $('label-cost-label').textContent = 'Maelezo Mafupi / Short label';
        }
        if (target === 'sale') {
            $('input-sale-type').value = '';
            $('sale-weight-field').classList.add('hidden');
            $('label-sale-quantity').textContent = 'Idadi / Quantity';
        }
    }

    // ---- Generic mini tap-option binder (source / feed_type / cause) ----
    function bindMiniOptions() {
        document.querySelectorAll('.mini-option').forEach((btn) => {
            btn.addEventListener('click', () => {
                const field = btn.dataset.field;
                const value = btn.dataset.value;
                const group = btn.closest('.tap-grid');
                group.querySelectorAll(`.mini-option[data-field="${field}"]`).forEach((el) => el.classList.remove('is-selected'));
                btn.classList.add('is-selected');

                if (field === 'source') $('input-feedpurchase-source').value = value;
                if (field === 'feed_type') $('input-feedpurchase-feedtype').value = value;
                if (field === 'cause') $('input-mortality-cause').value = value;
            });
        });
    }

    // ---- Cost category picker (separate instance from infrastructure's) ----
    function bindCostCategoryPicker() {
        const group = $('cost-category-picker');
        if (!group) return;
        group.addEventListener('click', (e) => {
            const card = e.target.closest('.category-option');
            if (!card) return;
            group.querySelectorAll('.category-option').forEach((el) => el.classList.remove('is-selected'));
            card.classList.add('is-selected');
            const category = card.dataset.category;
            $('input-cost-category').value = category;
            $('labor-subtype-field').classList.toggle('hidden', category !== 'labor');
            $('label-cost-label').textContent = COST_LABEL_PROMPTS[category] || 'Maelezo Mafupi / Short label';
        });
    }

    // ---- Sale-type picker ----
    const SALE_QUANTITY_LABELS = {
        eggs: 'Idadi ya Mayai / Number of eggs',
        birds: 'Idadi ya Kuku / Number of birds',
        manure: 'Kiasi (kg) / Quantity (kg)',
        other: 'Idadi / Quantity',
    };

    function bindSaleTypePicker() {
        const group = $('sale-type-picker');
        if (!group) return;
        group.addEventListener('click', (e) => {
            const card = e.target.closest('.category-option');
            if (!card) return;
            group.querySelectorAll('.category-option').forEach((el) => el.classList.remove('is-selected'));
            card.classList.add('is-selected');
            const saleType = card.dataset.saleType;
            $('input-sale-type').value = saleType;
            $('label-sale-quantity').textContent = SALE_QUANTITY_LABELS[saleType] || 'Idadi / Quantity';
            $('sale-weight-field').classList.toggle('hidden', saleType !== 'birds');
        });
    }

    // ---- Sale total auto-calculation (quantity x unit price), still editable ----
    function bindSaleTotalCalc() {
        const recalc = () => {
            const qty = parseFloat($('input-sale-quantity').value || '0');
            const price = parseFloat($('input-sale-unitprice').value || '0');
            if (qty > 0 && price > 0) {
                $('input-sale-total').value = (qty * price).toFixed(2);
            }
        };
        $('input-sale-quantity').addEventListener('input', recalc);
        $('input-sale-unitprice').addEventListener('input', recalc);
    }

    // ---- Forms ----
    function bindFeedPurchaseForm() {
        $('form-log-feed-purchase').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-log-feed-purchase', '');

            const source = $('input-feedpurchase-source').value;
            const feedType = $('input-feedpurchase-feedtype').value;
            const batchUuid = $('input-feedpurchase-batch').value || null;
            const quantityKg = parseFloat($('input-feedpurchase-qty').value || '0');
            const totalCost = parseFloat($('input-feedpurchase-cost').value || '0');
            const date = $('input-feedpurchase-date').value;
            const notes = $('input-feedpurchase-notes').value.trim();

            if (!feedType) { setError('error-log-feed-purchase', 'Chagua aina ya chakula. / Select a feed type.'); return; }
            if (!quantityKg || quantityKg <= 0) { setError('error-log-feed-purchase', 'Weka kiasi sahihi. / Enter a valid quantity.'); return; }
            if (!date) { setError('error-log-feed-purchase', 'Weka tarehe. / Enter a date.'); return; }

            await ShambaDB.put('feed_purchases', {
                client_uuid: uuid(), batch_client_uuid: batchUuid, source, feed_type: feedType,
                quantity_kg: quantityKg, total_cost: totalCost, date_purchased: date,
                notes: notes || null, synced: false,
            });
            showAppScreen('screen-daily-log');
            renderRecentActivity();
            ShambaSync.syncPending();
        });
    }

    function bindFeedConsumptionForm() {
        $('form-log-feed-consumption').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-log-feed-consumption', '');

            const batchUuid = $('input-feedcons-batch').value;
            const quantityKg = parseFloat($('input-feedcons-qty').value || '0');
            const date = $('input-feedcons-date').value;
            const notes = $('input-feedcons-notes').value.trim();

            if (!batchUuid) { setError('error-log-feed-consumption', 'Chagua kundi. / Select a batch.'); return; }
            if (!quantityKg || quantityKg <= 0) { setError('error-log-feed-consumption', 'Weka kiasi sahihi. / Enter a valid quantity.'); return; }
            if (!date) { setError('error-log-feed-consumption', 'Weka tarehe. / Enter a date.'); return; }

            await ShambaDB.put('feed_consumption', {
                client_uuid: uuid(), batch_client_uuid: batchUuid, quantity_kg: quantityKg,
                date_consumed: date, notes: notes || null, synced: false,
            });
            showAppScreen('screen-daily-log');
            renderRecentActivity();
            ShambaSync.syncPending();
        });
    }

    function bindEggsForm() {
        $('form-log-eggs').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-log-eggs', '');

            const batchUuid = $('input-eggs-batch').value;
            const whole = parseInt($('input-eggs-whole').value || '0', 10);
            const broken = parseInt($('input-eggs-broken').value || '0', 10);
            const date = $('input-eggs-date').value;
            const notes = $('input-eggs-notes').value.trim();

            if (!batchUuid) { setError('error-log-eggs', 'Chagua kundi. / Select a batch.'); return; }
            if (whole === 0 && broken === 0) { setError('error-log-eggs', 'Weka angalau idadi moja. / Enter at least one quantity.'); return; }
            if (!date) { setError('error-log-eggs', 'Weka tarehe. / Enter a date.'); return; }

            await ShambaDB.put('eggs', {
                client_uuid: uuid(), batch_client_uuid: batchUuid, date_collected: date,
                quantity_whole: whole, quantity_broken: broken, notes: notes || null, synced: false,
            });
            showAppScreen('screen-daily-log');
            renderRecentActivity();
            ShambaSync.syncPending();
        });
    }

    function bindMortalityForm() {
        $('form-log-mortality').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-log-mortality', '');

            const batchUuid = $('input-mortality-batch').value;
            const quantity = parseInt($('input-mortality-qty').value || '0', 10);
            const cause = $('input-mortality-cause').value || null;
            const date = $('input-mortality-date').value;
            const notes = $('input-mortality-notes').value.trim();

            if (!batchUuid) { setError('error-log-mortality', 'Chagua kundi. / Select a batch.'); return; }
            if (!quantity || quantity <= 0) { setError('error-log-mortality', 'Weka idadi sahihi. / Enter a valid quantity.'); return; }
            if (!date) { setError('error-log-mortality', 'Weka tarehe. / Enter a date.'); return; }

            await ShambaDB.put('mortality', {
                client_uuid: uuid(), batch_client_uuid: batchUuid, date_occurred: date,
                quantity, cause, notes: notes || null, synced: false,
            });
            showAppScreen('screen-daily-log');
            renderRecentActivity();
            ShambaSync.syncPending();
        });
    }

    function bindCostForm() {
        $('form-log-cost').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-log-cost', '');

            const category = $('input-cost-category').value;
            const subType = $('input-cost-subtype').value || null;
            const label = $('input-cost-label').value.trim();
            const batchUuid = $('input-cost-batch').value || null;
            const amount = parseFloat($('input-cost-amount').value || '0');
            const date = $('input-cost-date').value;
            const notes = $('input-cost-notes').value.trim();

            if (!category) { setError('error-log-cost', 'Chagua aina. / Select a category.'); return; }
            if (category === 'labor' && !subType) { setError('error-log-cost', 'Chagua posho au mshahara. / Specify allowance or salary.'); return; }
            if (!label) { setError('error-log-cost', 'Weka maelezo mafupi. / Enter a short label.'); return; }
            if (!date) { setError('error-log-cost', 'Weka tarehe. / Enter a date.'); return; }

            await ShambaDB.put('costs', {
                client_uuid: uuid(), category, sub_type: category === 'labor' ? subType : null,
                label, batch_client_uuid: batchUuid, amount, date_incurred: date, notes: notes || null, synced: false,
            });
            showAppScreen('screen-daily-log');
            renderRecentActivity();
            ShambaSync.syncPending();
        });
    }

    function bindSaleForm() {
        $('form-log-sale').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-log-sale', '');

            const saleType = $('input-sale-type').value;
            const batchUuid = $('input-sale-batch').value || null;
            const quantity = parseFloat($('input-sale-quantity').value || '0');
            const weightKg = $('input-sale-weight').value ? parseFloat($('input-sale-weight').value) : null;
            const unitPrice = parseFloat($('input-sale-unitprice').value || '0');
            const totalAmount = parseFloat($('input-sale-total').value || '0');
            const buyer = $('input-sale-buyer').value.trim();
            const date = $('input-sale-date').value;
            const notes = $('input-sale-notes').value.trim();

            if (!saleType) { setError('error-log-sale', 'Chagua kilichouzwa. / Select what was sold.'); return; }
            if (!quantity || quantity <= 0) { setError('error-log-sale', 'Weka idadi sahihi. / Enter a valid quantity.'); return; }
            if (!totalAmount || totalAmount <= 0) { setError('error-log-sale', 'Weka jumla sahihi. / Enter a valid total amount.'); return; }
            if (!date) { setError('error-log-sale', 'Weka tarehe. / Enter a date.'); return; }

            await ShambaDB.put('sales', {
                client_uuid: uuid(), batch_client_uuid: batchUuid, sale_type: saleType,
                quantity, unit: saleType === 'birds' ? 'birds' : (saleType === 'eggs' ? 'eggs' : null),
                weight_kg: saleType === 'birds' ? weightKg : null, unit_price: unitPrice, total_amount: totalAmount,
                buyer: buyer || null, date_sold: date, notes: notes || null, synced: false,
            });
            showAppScreen('screen-daily-log');
            renderRecentActivity();
            ShambaSync.syncPending();
        });
    }

    // ---- Recent activity list (combined view across all 5 log types) ----
    const CATEGORY_ICONS = { labor: '👷', utilities: '💡', medication: '💊', transport: '🚚' };
    const SALE_ICONS = { eggs: '🥚', birds: '🐔', manure: '💩', other: '📦' };

    async function renderRecentActivity() {
        const listEl = $('recent-activity-list');
        if (!listEl) return;

        const [feedPurchases, feedConsumption, eggs, mortality, costs, sales] = await Promise.all([
            ShambaDB.getAll('feed_purchases'),
            ShambaDB.getAll('feed_consumption'),
            ShambaDB.getAll('eggs'),
            ShambaDB.getAll('mortality'),
            ShambaDB.getAll('costs'),
            ShambaDB.getAll('sales'),
        ]);

        const rows = [
            ...feedPurchases.map((r) => ({ date: r.date_purchased, synced: r.synced, html: `🌾 Chakula: ${r.quantity_kg}kg — ${r.total_cost}` })),
            ...feedConsumption.map((r) => ({ date: r.date_consumed, synced: r.synced, html: `🍽️ Chakula kimetumika: ${r.quantity_kg}kg` })),
            ...eggs.map((r) => ({ date: r.date_collected, synced: r.synced, html: `🥚 Mayai: ${r.quantity_whole} mazima, ${r.quantity_broken} yamevunjika` })),
            ...mortality.map((r) => ({ date: r.date_occurred, synced: r.synced, html: `⚠️ Vifo: ${r.quantity}${r.cause ? ' — ' + r.cause : ''}` })),
            ...costs.map((r) => ({ date: r.date_incurred, synced: r.synced, html: `${CATEGORY_ICONS[r.category] || '💵'} ${r.label}: ${r.amount}` })),
            ...sales.map((r) => ({ date: r.date_sold, synced: r.synced, html: `${SALE_ICONS[r.sale_type] || '💰'} Mauzo: ${r.quantity} ${r.sale_type} — ${r.total_amount}` })),
        ].sort((a, b) => b.date.localeCompare(a.date)).slice(0, 15);

        listEl.innerHTML = rows.length
            ? rows.map((r) => `<li>${r.html} <span class="log-date">${r.date}</span>${r.synced ? '' : ' <span class="pending-badge" title="Bado kutumwa">●</span>'}</li>`).join('')
            : '<li class="empty-row">Bado hakuna rekodi / No entries yet</li>';
    }

    window.addEventListener('shambatrack:synced', () => {
        if (!$('screen-daily-log').classList.contains('hidden')) renderRecentActivity();
    });

    document.addEventListener('DOMContentLoaded', () => {
        try {
            bindNav();
            bindMiniOptions();
            bindCostCategoryPicker();
            bindSaleTypePicker();
            bindSaleTotalCalc();
            bindFeedPurchaseForm();
            bindFeedConsumptionForm();
            bindEggsForm();
            bindMortalityForm();
            bindCostForm();
            bindSaleForm();
        } catch (err) {
            console.error('[ShambaTrack] logs.js init failed:', err);
        }
    });
})();
