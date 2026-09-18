/**
 * Shamba Track — Smart calculations engine (Phase 6)
 *
 * Everything here is plain arithmetic over data already in IndexedDB —
 * no server round-trip, no AI, works fully offline. Each function is
 * written to be hand-verifiable: the comment above it states the exact
 * formula so a farmer (or developer) can check a number by hand.
 */

(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }

    const BREED_LABELS = { layers: 'Layers', broilers: 'Broilers', kienyeji: 'Kienyeji Bora', sasso: 'Sasso' };
    const EGG_BREEDS = ['layers', 'kienyeji', 'sasso'];

    // Typical feed-per-dozen-eggs benchmarks (kg of feed to produce 12 eggs).
    // Commercial layers convert feed to eggs more efficiently than dual
    // purpose birds. These are general reference points, not guarantees —
    // actual results vary by strain, climate, and management.
    const EGG_FCR_BENCHMARK_KG_PER_DOZEN = {
        layers: 1.6,
        kienyeji: 2.4,
        sasso: 2.2,
    };

    function round2(n) { return Math.round(n * 100) / 100; }

    function daysBetween(dateA, dateB) {
        const a = new Date(dateA + 'T00:00:00');
        const b = new Date(dateB + 'T00:00:00');
        return Math.round((b - a) / 86400000);
    }

    function showAppScreen(id) {
        document.querySelectorAll('.screen').forEach((el) => el.classList.add('hidden'));
        $(id).classList.remove('hidden');
    }

    // ---- Main entry point: gather everything needed for one batch ---
    async function openInsightsForBatch(batchUuid) {
        const [batches, feedPurchases, feedConsumption, eggs, mortality, costs, sales] = await Promise.all([
            ShambaDB.getAll('batches'),
            ShambaDB.getAll('feed_purchases'),
            ShambaDB.getAll('feed_consumption'),
            ShambaDB.getAll('eggs'),
            ShambaDB.getAll('mortality'),
            ShambaDB.getAll('costs'),
            ShambaDB.getAll('sales'),
        ]);

        const batch = batches.find((b) => b.client_uuid === batchUuid);
        if (!batch) return;

        const forBatch = (arr, key) => arr.filter((r) => r[key] === batchUuid);
        const data = {
            batch,
            feedPurchases: forBatch(feedPurchases, 'batch_client_uuid'),
            feedConsumption: forBatch(feedConsumption, 'batch_client_uuid'),
            eggs: forBatch(eggs, 'batch_client_uuid'),
            mortality: forBatch(mortality, 'batch_client_uuid'),
            costs: forBatch(costs, 'batch_client_uuid'),
            sales: forBatch(sales, 'batch_client_uuid'),
        };

        $('insights-batch-label').textContent = `${BREED_LABELS[batch.breed] || batch.breed} — ${batch.quantity} birds (${batch.date_acquired})`;

        renderAnomalies(data);
        renderFcr(data);
        renderFeedDaysRemaining(data);
        renderBreakeven(data);
        renderBuyVsMake(feedPurchases); // farm-wide, not batch-scoped

        showAppScreen('screen-batch-insights');
    }

    // ---- 1. Feed Conversion Ratio ----
    // Egg breeds: FCR = total feed consumed (kg) / (total whole eggs / 12)
    //   -> "kg of feed per dozen eggs". Lower is more efficient.
    // Broilers: no weight-log feature yet, so true feed:weight-gain FCR
    //   isn't computable honestly. Instead we show feed cost per surviving
    //   bird so far, which IS fully computable and still useful.
    function renderFcr(data) {
        const el = $('insights-fcr');
        const totalFeedKg = sum(data.feedConsumption, 'quantity_kg');

        if (EGG_BREEDS.includes(data.batch.breed)) {
            const totalEggs = sum(data.eggs, 'quantity_whole');
            if (totalFeedKg === 0 || totalEggs === 0) {
                el.innerHTML = `<p class="subtext">Bado hakuna data ya kutosha (chakula: ${round2(totalFeedKg)}kg, mayai: ${totalEggs}). / Not enough data yet.</p>`;
                return;
            }
            const dozens = totalEggs / 12;
            const fcr = round2(totalFeedKg / dozens);
            const benchmark = EGG_FCR_BENCHMARK_KG_PER_DOZEN[data.batch.breed];
            const diffPct = round2(((fcr - benchmark) / benchmark) * 100);
            const verdict = fcr <= benchmark
                ? `✅ Bora kuliko wastani / Better than typical (${Math.abs(diffPct)}% chini/lower)`
                : `⚠️ Zaidi ya wastani / Above typical (${diffPct}% juu/higher)`;

            el.innerHTML = `
                <p><strong>${fcr} kg</strong> ya chakula kwa dazani ya mayai / feed per dozen eggs</p>
                <p class="subtext">Chakula jumla: ${round2(totalFeedKg)}kg ÷ (mayai ${totalEggs} ÷ 12 = ${round2(dozens)} dazani)</p>
                <p class="subtext">Kiwango cha kawaida / Typical benchmark: ~${benchmark} kg/dozen</p>
                <p>${verdict}</p>
            `;
        } else {
            const survivingBirds = Math.max(0, data.batch.quantity - sum(data.mortality, 'quantity'));
            const totalFeedCost = sum(data.feedPurchases, 'total_cost');
            if (survivingBirds === 0 || totalFeedCost === 0) {
                el.innerHTML = `<p class="subtext">Bado hakuna data ya kutosha. / Not enough data yet.</p>`;
                return;
            }
            const costPerBird = round2(totalFeedCost / survivingBirds);
            el.innerHTML = `
                <p><strong>${costPerBird}</strong> gharama ya chakula kwa kila kuku aliyepo / feed cost per surviving bird so far</p>
                <p class="subtext">Gharama jumla ya chakula ${round2(totalFeedCost)} ÷ kuku waliopo ${survivingBirds}</p>
                <p class="subtext">FCR halisi (uzito) inahitaji kurekodi uzito wa kuku — haipo bado. / True weight-based FCR needs weight logging, not tracked yet.</p>
            `;
        }
    }

    // ---- 2. Feed days remaining ----
    // feed_in_stock = total bought (kg) - total consumed (kg)
    // avg_daily_use = total consumed (kg) / days between first and last
    //                 consumption log (inclusive), minimum 1 day
    // days_remaining = feed_in_stock / avg_daily_use
    function renderFeedDaysRemaining(data) {
        const el = $('insights-feed-days');
        const totalBought = sum(data.feedPurchases, 'quantity_kg');
        const totalConsumed = sum(data.feedConsumption, 'quantity_kg');
        const inStock = round2(totalBought - totalConsumed);

        if (data.feedConsumption.length < 1) {
            el.innerHTML = `<p class="subtext">Bado hakuna rekodi ya matumizi ya chakula. / No feed consumption logged yet.</p>
                <p>Chakula kilichonunuliwa / Feed bought so far: <strong>${round2(totalBought)}kg</strong></p>`;
            return;
        }

        const dates = data.feedConsumption.map((r) => r.date_consumed).sort();
        const spanDays = Math.max(1, daysBetween(dates[0], dates[dates.length - 1]) + 1);
        const avgDaily = totalConsumed / spanDays;

        if (avgDaily <= 0) {
            el.innerHTML = `<p class="subtext">Haiwezekani kukokotoa — matumizi ya wastani ni sifuri. / Can't calculate — average usage is zero.</p>`;
            return;
        }

        const daysRemaining = Math.max(0, Math.round(inStock / avgDaily));
        const lowStockWarning = daysRemaining <= 3
            ? `<p class="anomaly-flag">⚠️ Chakula kinaisha hivi karibuni / Feed running out soon</p>`
            : '';

        el.innerHTML = `
            <p><strong>${daysRemaining} siku</strong> zilizobaki / days remaining at current usage</p>
            <p class="subtext">Kilichopo ${inStock}kg ÷ wastani wa matumizi ${round2(avgDaily)}kg/siku (kutoka rekodi ${spanDays} siku)</p>
            ${lowStockWarning}
        `;
    }

    // ---- 3. Anomaly flags ----
    // For each of mortality and feed consumption: compare the LATEST entry
    // to the average of all PRIOR entries. Flag if latest > 2x that average
    // (and the latest value itself is non-trivial, to avoid flagging normal
    // small fluctuations when numbers are tiny).
    function renderAnomalies(data) {
        const el = $('insights-anomaly-list');
        const flags = [];

        const mortalitySorted = [...data.mortality].sort((a, b) => a.date_occurred.localeCompare(b.date_occurred));
        if (mortalitySorted.length >= 2) {
            const latest = mortalitySorted[mortalitySorted.length - 1];
            const prior = mortalitySorted.slice(0, -1);
            const priorAvg = sum(prior, 'quantity') / prior.length;
            if (latest.quantity >= 2 && priorAvg > 0 && latest.quantity > priorAvg * 2) {
                flags.push(`⚠️ Vifo vya ${latest.date_occurred} (${latest.quantity}) ni zaidi ya mara mbili ya wastani (${round2(priorAvg)}). / Mortality on ${latest.date_occurred} is more than double the average.`);
            }
        }

        const feedSorted = [...data.feedConsumption].sort((a, b) => a.date_consumed.localeCompare(b.date_consumed));
        if (feedSorted.length >= 2) {
            const latest = feedSorted[feedSorted.length - 1];
            const prior = feedSorted.slice(0, -1);
            const priorAvg = sum(prior, 'quantity_kg') / prior.length;
            if (priorAvg > 0 && latest.quantity_kg > priorAvg * 2) {
                flags.push(`⚠️ Matumizi ya chakula ${latest.date_consumed} (${latest.quantity_kg}kg) ni zaidi ya mara mbili ya wastani. / Feed use spiked well above average.`);
            } else if (priorAvg > 0 && latest.quantity_kg < priorAvg * 0.5) {
                flags.push(`⚠️ Matumizi ya chakula ${latest.date_consumed} yamepungua sana — kuku wanaweza kuwa wagonjwa. / Feed use dropped sharply — birds may be off their feed.`);
            }
        }

        el.innerHTML = flags.length ? flags.map((f) => `<div class="anomaly-flag">${f}</div>`).join('') : '';
    }

    // ---- 4. Break-even price per unit ----
    // batch_costs = (quantity x cost_per_bird) + feed purchases + other
    //               batch-linked costs (labor/utilities/medication/transport)
    // egg breeds: break_even_per_egg = batch_costs / total whole eggs
    // broilers:   break_even_per_bird = batch_costs / birds remaining
    function renderBreakeven(data) {
        const el = $('insights-breakeven');
        const acquisitionCost = data.batch.quantity * (data.batch.cost_per_bird || 0);
        const feedCost = sum(data.feedPurchases, 'total_cost');
        const otherCosts = sum(data.costs, 'amount');
        const totalCosts = round2(acquisitionCost + feedCost + otherCosts);

        let units, unitLabel;
        if (EGG_BREEDS.includes(data.batch.breed)) {
            units = sum(data.eggs, 'quantity_whole');
            unitLabel = 'yai / egg';
        } else {
            units = Math.max(0, data.batch.quantity - sum(data.mortality, 'quantity'));
            unitLabel = 'kuku / bird';
        }

        if (units === 0) {
            el.innerHTML = `<p class="subtext">Gharama jumla / Total costs so far: <strong>${totalCosts}</strong>. Bado hakuna mazao ya kuuza. / No output to sell yet.</p>`;
            return;
        }

        const breakeven = round2(totalCosts / units);
        let comparisonNote = '';
        if (data.sales.length > 0) {
            const avgSalePrice = round2(sum(data.sales, 'total_amount') / sum(data.sales, 'quantity'));
            comparisonNote = avgSalePrice >= breakeven
                ? `<p>✅ Wastani wa bei yako ya mauzo (${avgSalePrice}) uko juu ya kiwango cha kuvunja sawa. / Your average sale price is above break-even.</p>`
                : `<p class="anomaly-flag">⚠️ Wastani wa bei yako ya mauzo (${avgSalePrice}) uko chini ya kiwango cha kuvunja sawa. / Your average sale price is below break-even.</p>`;
        }

        el.innerHTML = `
            <p>Gharama jumla / Total costs: <strong>${totalCosts}</strong> (ununuzi ${round2(acquisitionCost)} + chakula ${round2(feedCost)} + nyingine ${round2(otherCosts)})</p>
            <p><strong>${breakeven}</strong> kwa kila ${unitLabel} / per ${unitLabel} needed to break even</p>
            ${comparisonNote}
        `;
    }

    // ---- 5. Buy vs make feed comparison (farm-wide, not batch-scoped) ----
    // For each feed_type present under both sources, compare
    // avg cost/kg = total_cost / total_kg for 'bought' vs 'home_made'.
    function renderBuyVsMake(allFeedPurchases) {
        const el = $('insights-buy-vs-make');
        const byTypeAndSource = {};

        allFeedPurchases.forEach((p) => {
            const key = p.feed_type;
            byTypeAndSource[key] = byTypeAndSource[key] || { bought: { cost: 0, kg: 0 }, home_made: { cost: 0, kg: 0 } };
            byTypeAndSource[key][p.source].cost += p.total_cost;
            byTypeAndSource[key][p.source].kg += p.quantity_kg;
        });

        const rows = [];
        for (const [feedType, sources] of Object.entries(byTypeAndSource)) {
            const boughtPerKg = sources.bought.kg > 0 ? sources.bought.cost / sources.bought.kg : null;
            const madePerKg = sources.home_made.kg > 0 ? sources.home_made.cost / sources.home_made.kg : null;

            if (boughtPerKg !== null && madePerKg !== null) {
                const cheaper = boughtPerKg <= madePerKg ? 'Kununua / Buying' : 'Kutengeneza / Home-made';
                rows.push(`<p><strong>${feedType}</strong>: kununua ${round2(boughtPerKg)}/kg, kutengeneza ${round2(madePerKg)}/kg — <strong>${cheaper}</strong> ni nafuu zaidi / is cheaper</p>`);
            }
        }

        el.innerHTML = rows.length
            ? rows.join('')
            : '<p class="subtext">Jaribu njia zote mbili (kununua na kutengeneza) kupata ulinganisho. / Try both sourcing methods to see a comparison.</p>';
    }

    function sum(arr, field) {
        return arr.reduce((acc, r) => acc + (parseFloat(r[field]) || 0), 0);
    }

    // Delegated click for the "📊 Takwimu" button on each batch row
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.view-insights');
        if (btn) openInsightsForBatch(btn.dataset.batchUuid);
    });
})();
