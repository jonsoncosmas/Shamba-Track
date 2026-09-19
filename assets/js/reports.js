/**
 * Shamba Track — Full financial report (Phase 7)
 *
 * Pure client-side computation over IndexedDB data, same approach as
 * insights.js. Two profit figures are shown deliberately, not one:
 *
 * - Gross Operating Profit = revenue - (bird acquisition + feed + labor/
 *   utilities/medication/transport). This is the recurring, per-cycle
 *   number.
 * - Net Profit = Gross Operating Profit - loan interest. Interest is a
 *   real recurring financial cost, so it belongs in "net".
 *
 * Infrastructure/capital costs (coop, land, equipment) are shown
 * separately and NOT folded into either profit figure — they're one-time
 * capital investments, not per-cycle operating costs. Depreciating them
 * properly is a future improvement; showing them as a false hit to this
 * cycle's profit would be more misleading than showing them apart.
 */

(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }
    const EGG_BREEDS = ['layers', 'kienyeji', 'sasso'];
    const BREED_LABELS = { layers: 'Layers', broilers: 'Broilers', kienyeji: 'Kienyeji Bora', sasso: 'Sasso' };

    function round2(n) { return Math.round(n * 100) / 100; }
    function sum(arr, field) { return arr.reduce((acc, r) => acc + (parseFloat(r[field]) || 0), 0); }

    async function renderFullReport() {
        const [batches, feedPurchases, infra, costs, sales, capital, eggs, mortality] = await Promise.all([
            ShambaDB.getAll('batches'),
            ShambaDB.getAll('feed_purchases'),
            ShambaDB.getAll('infrastructure'),
            ShambaDB.getAll('costs'),
            ShambaDB.getAll('sales'),
            ShambaDB.getAll('capital_sources'),
            ShambaDB.getAll('eggs'),
            ShambaDB.getAll('mortality'),
        ]);

        const forBatch = (arr, uuid) => arr.filter((r) => r.batch_client_uuid === uuid);

        // ---- Per-batch rows ----
        const batchRows = batches.map((b) => {
            const acquisition = b.quantity * (b.cost_per_bird || 0);
            const feedCost = sum(forBatch(feedPurchases, b.client_uuid), 'total_cost');
            const otherCosts = sum(forBatch(costs, b.client_uuid), 'amount');
            const operatingCosts = acquisition + feedCost + otherCosts;
            const revenue = sum(forBatch(sales, b.client_uuid), 'total_amount');
            const profit = revenue - operatingCosts;

            let units, unitLabel;
            if (EGG_BREEDS.includes(b.breed)) {
                units = sum(forBatch(eggs, b.client_uuid), 'quantity_whole');
                unitLabel = 'yai/egg';
            } else {
                units = Math.max(0, b.quantity - sum(forBatch(mortality, b.client_uuid), 'quantity'));
                unitLabel = 'kuku/bird';
            }
            const profitPerUnit = units > 0 ? round2(profit / units) : null;

            return { batch: b, acquisition, feedCost, otherCosts, operatingCosts, revenue, profit, units, unitLabel, profitPerUnit };
        });

        // ---- Farm-wide totals (batch rows + anything not linked to a batch) ----
        const unallocatedFeed = sum(feedPurchases.filter((r) => !r.batch_client_uuid), 'total_cost');
        const unallocatedCosts = sum(costs.filter((r) => !r.batch_client_uuid), 'amount');
        const unallocatedRevenue = sum(sales.filter((r) => !r.batch_client_uuid), 'total_amount');

        const totalOperatingCosts = round2(sum(batchRows, 'operatingCosts') + unallocatedFeed + unallocatedCosts);
        const totalRevenue = round2(sum(batchRows, 'revenue') + unallocatedRevenue);
        const grossProfit = round2(totalRevenue - totalOperatingCosts);

        const totalInfraCost = round2(sum(infra, 'amount'));

        const loanInterest = round2(
            capital.filter((c) => c.source_type === 'loan' && c.interest_rate)
                .reduce((acc, c) => acc + c.amount * (c.interest_rate / 100), 0)
        );
        const netProfit = round2(grossProfit - loanInterest);

        renderSummary({ totalRevenue, totalOperatingCosts, grossProfit, loanInterest, netProfit, totalInfraCost });
        renderExpenseChart({ batchRows, unallocatedFeed, unallocatedCosts, totalInfraCost, costs });
        renderBatchTable(batchRows);
        renderCapital(capital, loanInterest);
    }

    function renderSummary(s) {
        $('report-summary').innerHTML = `
            <p>Mapato Jumla / Total Revenue: <strong>${s.totalRevenue}</strong></p>
            <p>Gharama za Uendeshaji / Operating Costs: <strong>${s.totalOperatingCosts}</strong></p>
            <p>Faida Jumla / Gross Operating Profit: <strong>${s.grossProfit}</strong></p>
            <p>Riba ya Mkopo / Loan Interest: <strong>${s.loanInterest}</strong></p>
            <p class="report-net-profit">Faida Halisi / Net Profit: <strong>${s.netProfit}</strong></p>
            <p class="subtext">Gharama za Miundombinu (mtaji) / Infrastructure (capital) costs: ${s.totalInfraCost} — hazijajumuishwa kwenye faida hapo juu / not included in the profit figures above, since these are one-time investments, not per-cycle costs.</p>
        `;
    }

    function renderExpenseChart({ batchRows, unallocatedFeed, unallocatedCosts, totalInfraCost, costs }) {
        const categories = {
            'Ununuzi wa Kuku / Bird Acquisition': sum(batchRows, 'acquisition'),
            'Chakula / Feed': sum(batchRows, 'feedCost') + unallocatedFeed,
            'Nguvu Kazi / Labor': sum(costs.filter((c) => c.category === 'labor'), 'amount'),
            'Huduma / Utilities': sum(costs.filter((c) => c.category === 'utilities'), 'amount'),
            'Dawa / Medication': sum(costs.filter((c) => c.category === 'medication'), 'amount'),
            'Usafiri / Transport': sum(costs.filter((c) => c.category === 'transport'), 'amount'),
            'Miundombinu / Infrastructure': totalInfraCost,
        };

        const maxVal = Math.max(1, ...Object.values(categories));
        const rows = Object.entries(categories)
            .filter(([, val]) => val > 0)
            .map(([label, val]) => {
                const pct = round2((val / maxVal) * 100);
                return `<div class="bar-row">
                    <div class="bar-label">${label}</div>
                    <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div>
                    <div class="bar-value">${round2(val)}</div>
                </div>`;
            }).join('');

        $('report-expense-chart').innerHTML = rows || '<p class="subtext">Bado hakuna gharama zilizorekodiwa. / No expenses logged yet.</p>';
    }

    function renderBatchTable(batchRows) {
        if (!batchRows.length) {
            $('report-batch-table').innerHTML = '<p class="subtext">Bado hakuna kundi. / No batches yet.</p>';
            return;
        }

        const rows = batchRows.map(({ batch, operatingCosts, revenue, profit, unitLabel, profitPerUnit }) => {
            const profitClass = profit >= 0 ? 'profit-positive' : 'profit-negative';
            return `<div class="batch-report-row">
                <strong>${BREED_LABELS[batch.breed] || batch.breed}</strong> (${batch.quantity}, ${batch.date_acquired})<br>
                <span class="subtext">Mapato ${round2(revenue)} − Gharama ${round2(operatingCosts)} =</span>
                <span class="${profitClass}"> Faida ${round2(profit)}</span>
                ${profitPerUnit !== null ? `<span class="subtext"> (${profitPerUnit} kwa kila ${unitLabel})</span>` : ''}
            </div>`;
        }).join('');

        $('report-batch-table').innerHTML = rows;
    }

    const CAPITAL_LABELS = { loan: 'Mkopo/Loan', salary: 'Mshahara/Salary', freelance: 'Freelance', savings: 'Akiba/Savings', other: 'Nyingine/Other' };

    function renderCapital(capital, totalInterest) {
        if (!capital.length) {
            $('report-capital').innerHTML = '<p class="subtext">Bado hakuna mtaji uliorekodiwa. / No capital sources logged yet.</p>';
            return;
        }

        const rows = capital.map((c) => {
            const interestAmount = c.interest_rate ? round2(c.amount * (c.interest_rate / 100)) : null;
            return `<p><strong>${CAPITAL_LABELS[c.source_type] || c.source_type}</strong>: ${c.amount}${
                interestAmount !== null ? ` (riba ${c.interest_rate}% = ${interestAmount})` : ''
            } — ${c.date_received}</p>`;
        }).join('');

        $('report-capital').innerHTML = rows + (totalInterest > 0 ? `<p><strong>Jumla ya Riba / Total Interest: ${totalInterest}</strong></p>` : '');
    }

    window.addEventListener('shambatrack:open-reports', renderFullReport);
    window.addEventListener('shambatrack:synced', () => {
        if (!$('screen-reports')?.classList.contains('hidden')) renderFullReport();
    });
})();
