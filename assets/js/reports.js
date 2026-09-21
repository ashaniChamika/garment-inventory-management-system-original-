/* =============================================================================
   GIMS — Reports client
   ============================================================================= */

(function () {
    'use strict';

    const G = window.GIMS || {};
    const api = (u, o) => window.GIMS_APP.api(u, o);
    const toast = (m, t) => window.GIMS_APP.toast(m, t || 'info');
    const showLoader = (s) => window.GIMS_APP.showLoader(s);
    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');

    const fmtMoney = (v) => G.currency + ' ' + Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
    const fmtNum = (v) => Number(v||0).toLocaleString(undefined,{maximumFractionDigits:2});
    const human = (t) => String(t||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());

    /* ---------- CSV export helper ---------- */
    function downloadCsv(filename, rows) {
        if (!rows.length) { toast('No data to export.', 'warning'); return; }
        const headers = Object.keys(rows[0]);
        const lines = [headers.join(',')];
        rows.forEach(r => {
            lines.push(headers.map(h => {
                let v = r[h] == null ? '' : String(r[h]);
                if (v.includes(',') || v.includes('"') || v.includes('\n')) {
                    v = '"' + v.replace(/"/g, '""') + '"';
                }
                return v;
            }).join(','));
        });
        const blob = new Blob(['\ufeff' + lines.join('\n')], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click();
        setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 500);
    }

    let lastData = {};

    /* ==================================================================
       INVENTORY REPORT
       ================================================================== */
    if (document.getElementById('runInventory')) {
        const body = document.getElementById('invBody');

        async function run() {
            showLoader(true);
            const qs = new URLSearchParams({
                action:'inventory',
                category_id: document.getElementById('rptCategory').value,
                warehouse_id: document.getElementById('rptWarehouse').value,
                filter: document.getElementById('rptFilter').value
            });
            const r = await api(`${G.baseUrl}/api/reports.php?${qs}`);
            showLoader(false);
            if (!r.success) { toast(r.message, 'danger'); return; }
            lastData.inventory = r.data.items;

            document.getElementById('invKpiCount').textContent = r.data.items.length;
            document.getElementById('invKpiQty').textContent = fmtNum(r.data.totals.qty);
            document.getElementById('invKpiCost').textContent = fmtMoney(r.data.totals.cost);
            document.getElementById('invKpiRetail').textContent = fmtMoney(r.data.totals.retail);

            if (!r.data.items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-box"></i><p>No items match filters.</p></div></td></tr>';
                return;
            }
            body.innerHTML = r.data.items.map(it => {
                const qty = parseFloat(it.total_qty);
                const rl = parseFloat(it.reorder_level);
                const st = qty <= 0 ? '<span class="badge badge-soft-danger">Out</span>'
                        : (qty <= rl ? '<span class="badge badge-soft-warning">Low</span>' : '<span class="badge badge-soft-success">In Stock</span>');
                return `<tr>
                    <td><code>${esc(it.sku)}</code></td>
                    <td><div class="gims-cell-title">${esc(it.name)}</div></td>
                    <td>${esc(it.category_name || '—')}</td>
                    <td class="text-end fw-semibold">${fmtNum(qty)} <small class="text-muted">${esc(it.unit)}</small></td>
                    <td class="text-end text-muted">${fmtNum(rl)}</td>
                    <td class="text-end">${fmtMoney(it.cost_price)}</td>
                    <td class="text-end fw-semibold">${fmtMoney(it.stock_value)}</td>
                    <td>${st}</td>
                </tr>`;
            }).join('');
        }

        document.getElementById('runInventory').addEventListener('click', run);
        document.getElementById('exportCsvInv').addEventListener('click', () => downloadCsv('inventory_report.csv', lastData.inventory || []));
    }

    /* ==================================================================
       SALES REPORT
       ================================================================== */
    if (document.getElementById('runSales')) {
        const body = document.getElementById('slBody');

        async function run() {
            showLoader(true);
            const qs = new URLSearchParams({
                action:'sales',
                date_from: document.getElementById('slFrom').value,
                date_to: document.getElementById('slTo').value,
                customer_id: document.getElementById('slCustomer').value
            });
            const r = await api(`${G.baseUrl}/api/reports.php?${qs}`);
            showLoader(false);
            if (!r.success) { toast(r.message, 'danger'); return; }
            lastData.sales = r.data.items;

            document.getElementById('slKpiCount').textContent = r.data.totals.invoices;
            document.getElementById('slKpiTotal').textContent = fmtMoney(r.data.totals.total);
            document.getElementById('slKpiPaid').textContent = fmtMoney(r.data.totals.paid);
            document.getElementById('slKpiBal').textContent = fmtMoney(r.data.totals.balance);

            if (!r.data.items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-receipt"></i><p>No invoices in range.</p></div></td></tr>';
                return;
            }
            body.innerHTML = r.data.items.map(i => `
                <tr>
                    <td><span class="fw-semibold">${esc(i.invoice_no)}</span></td>
                    <td>${new Date(i.invoice_date).toLocaleDateString()}</td>
                    <td>${esc(i.customer_name)}${i.company ? `<br><small class="text-muted">${esc(i.company)}</small>` : ''}</td>
                    <td>${esc(i.order_no || '—')}</td>
                    <td class="text-end">${fmtMoney(i.total)}</td>
                    <td class="text-end text-success">${fmtMoney(i.paid_amount)}</td>
                    <td class="text-end text-danger">${fmtMoney(i.total - i.paid_amount)}</td>
                    <td>${human(i.status)}</td>
                </tr>
            `).join('');
        }

        document.getElementById('runSales').addEventListener('click', run);
        document.getElementById('exportCsvSales').addEventListener('click', () => downloadCsv('sales_report.csv', lastData.sales || []));
    }

    /* ==================================================================
       PURCHASE REPORT
       ================================================================== */
    if (document.getElementById('runPurchases')) {
        const body = document.getElementById('puBody');

        async function run() {
            showLoader(true);
            const qs = new URLSearchParams({
                action:'purchases',
                date_from: document.getElementById('puFrom').value,
                date_to: document.getElementById('puTo').value,
                supplier_id: document.getElementById('puSupplier').value
            });
            const r = await api(`${G.baseUrl}/api/reports.php?${qs}`);
            showLoader(false);
            if (!r.success) { toast(r.message, 'danger'); return; }
            lastData.purchases = r.data.items;

            document.getElementById('puKpiCount').textContent = r.data.totals.orders;
            document.getElementById('puKpiTotal').textContent = fmtMoney(r.data.totals.total);
            document.getElementById('puKpiPaid').textContent = fmtMoney(r.data.totals.paid);
            document.getElementById('puKpiBal').textContent = fmtMoney(r.data.totals.balance);

            if (!r.data.items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-cart"></i><p>No POs in range.</p></div></td></tr>';
                return;
            }
            body.innerHTML = r.data.items.map(p => `
                <tr>
                    <td><span class="fw-semibold">${esc(p.po_number)}</span></td>
                    <td>${new Date(p.order_date).toLocaleDateString()}</td>
                    <td>${esc(p.company_name)}</td>
                    <td>${esc(p.warehouse_name)}</td>
                    <td class="text-end">${p.item_count}</td>
                    <td class="text-end">${fmtMoney(p.total)}</td>
                    <td class="text-end text-danger">${fmtMoney(p.total - p.paid_amount)}</td>
                    <td>${human(p.status)}</td>
                </tr>
            `).join('');
        }

        document.getElementById('runPurchases').addEventListener('click', run);
        document.getElementById('exportCsvPurchases').addEventListener('click', () => downloadCsv('purchase_report.csv', lastData.purchases || []));
    }

    /* ==================================================================
       PRODUCTION REPORT
       ================================================================== */
    if (document.getElementById('runProduction')) {
        const body = document.getElementById('prBody');

        async function run() {
            showLoader(true);
            const qs = new URLSearchParams({
                action:'production',
                date_from: document.getElementById('prFrom').value,
                date_to: document.getElementById('prTo').value
            });
            const r = await api(`${G.baseUrl}/api/reports.php?${qs}`);
            showLoader(false);
            if (!r.success) { toast(r.message, 'danger'); return; }
            lastData.production = r.data.items;

            document.getElementById('prKpiOrders').textContent = r.data.totals.orders;
            document.getElementById('prKpiOrdered').textContent = fmtNum(r.data.totals.ordered);
            document.getElementById('prKpiProduced').textContent = fmtNum(r.data.totals.produced);
            document.getElementById('prKpiFailed').textContent = fmtNum(r.data.totals.failed);

            if (!r.data.items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-gear"></i><p>No production orders in range.</p></div></td></tr>';
                return;
            }
            body.innerHTML = r.data.items.map(p => `
                <tr>
                    <td><span class="fw-semibold">${esc(p.order_no)}</span></td>
                    <td>
                        <div class="gims-cell-title">${esc(p.product_name)}</div>
                        <small class="text-muted">${esc(p.sku)}</small>
                    </td>
                    <td>${p.start_date ? new Date(p.start_date).toLocaleDateString() : '—'}</td>
                    <td>${p.expected_date ? new Date(p.expected_date).toLocaleDateString() : '—'}</td>
                    <td class="text-end">${fmtNum(p.quantity)}</td>
                    <td class="text-end fw-semibold">${fmtNum(p.produced_qty)}</td>
                    <td class="text-end text-success">${fmtNum(p.passed)}</td>
                    <td class="text-end text-danger">${fmtNum(p.failed)}</td>
                    <td>${human(p.status)}</td>
                </tr>
            `).join('');
        }

        document.getElementById('runProduction').addEventListener('click', run);
        document.getElementById('exportCsvProduction').addEventListener('click', () => downloadCsv('production_report.csv', lastData.production || []));
    }

    /* ==================================================================
       STOCK MOVEMENT REPORT
       ================================================================== */
    if (document.getElementById('runMovement')) {
        const body = document.getElementById('mvBody');

        async function run() {
            showLoader(true);
            const qs = new URLSearchParams({
                action:'stock_movement',
                date_from: document.getElementById('mvFrom').value,
                date_to: document.getElementById('mvTo').value,
                warehouse_id: document.getElementById('mvWarehouse').value,
                type: document.getElementById('mvType').value
            });
            const r = await api(`${G.baseUrl}/api/reports.php?${qs}`);
            showLoader(false);
            if (!r.success) { toast(r.message, 'danger'); return; }
            lastData.movement = r.data.items;

            document.getElementById('mvKpiIn').textContent = fmtNum(r.data.totals.in);
            document.getElementById('mvKpiOut').textContent = fmtNum(r.data.totals.out);
            document.getElementById('mvKpiNet').textContent = fmtNum(r.data.totals.net);
            document.getElementById('mvKpiCount').textContent = r.data.items.length;

            if (!r.data.items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-arrow-left-right"></i><p>No movements in range.</p></div></td></tr>';
                return;
            }
            body.innerHTML = r.data.items.map(m => `
                <tr>
                    <td>${new Date(m.created_at).toLocaleString()}</td>
                    <td>
                        <div class="gims-cell-title">${esc(m.product_name)}</div>
                        <small class="text-muted">${esc(m.sku)}</small>
                    </td>
                    <td>${esc(m.warehouse_name)}</td>
                    <td><span class="badge badge-soft-secondary">${human(m.movement_type)}</span></td>
                    <td>${m.reference_no ? `<code class="small">${esc(m.reference_no)}</code>` : '—'}</td>
                    <td class="text-end fw-semibold ${m.direction==='in'?'text-success':'text-danger'}">
                        ${m.direction==='in'?'+':'−'}${fmtNum(m.quantity)} <small>${esc(m.unit)}</small>
                    </td>
                    <td class="text-end">${fmtNum(m.balance_after)}</td>
                    <td class="small text-muted">${esc(m.user_name || '—')}</td>
                </tr>
            `).join('');
        }

        document.getElementById('runMovement').addEventListener('click', run);
        document.getElementById('exportCsvMovement').addEventListener('click', () => downloadCsv('stock_movement_report.csv', lastData.movement || []));
    }

    /* ==================================================================
       PROFIT REPORT
       ================================================================== */
    if (document.getElementById('runProfit')) {
        const body = document.getElementById('pfBody');

        async function run() {
            showLoader(true);
            const qs = new URLSearchParams({
                action:'profit',
                date_from: document.getElementById('pfFrom').value,
                date_to: document.getElementById('pfTo').value
            });
            const r = await api(`${G.baseUrl}/api/reports.php?${qs}`);
            showLoader(false);
            if (!r.success) { toast(r.message, 'danger'); return; }
            lastData.profit = r.data.items;

            document.getElementById('pfKpiRev').textContent = fmtMoney(r.data.totals.revenue);
            document.getElementById('pfKpiCost').textContent = fmtMoney(r.data.totals.cost);
            document.getElementById('pfKpiProfit').textContent = fmtMoney(r.data.totals.profit);
            document.getElementById('pfKpiMargin').textContent = r.data.totals.margin + '%';

            if (!r.data.items.length) {
                body.innerHTML = '<tr><td colspan="7"><div class="gims-empty"><i class="bi bi-graph-up"></i><p>No sales in range.</p></div></td></tr>';
                return;
            }
            body.innerHTML = r.data.items.map(p => {
                const margin = parseFloat(p.revenue) > 0 ? ((parseFloat(p.profit) / parseFloat(p.revenue)) * 100).toFixed(1) : 0;
                return `<tr>
                    <td><div class="gims-cell-title">${esc(p.product_name)}</div></td>
                    <td><code>${esc(p.sku)}</code></td>
                    <td class="text-end">${fmtNum(p.qty_sold)} <small class="text-muted">${esc(p.unit)}</small></td>
                    <td class="text-end">${fmtMoney(p.revenue)}</td>
                    <td class="text-end text-muted">${fmtMoney(p.cost)}</td>
                    <td class="text-end fw-bold ${parseFloat(p.profit)>=0?'text-success':'text-danger'}">${fmtMoney(p.profit)}</td>
                    <td class="text-end">${margin}%</td>
                </tr>`;
            }).join('');
        }

        document.getElementById('runProfit').addEventListener('click', run);
        document.getElementById('exportCsvProfit').addEventListener('click', () => downloadCsv('profit_report.csv', lastData.profit || []));
    }
})();