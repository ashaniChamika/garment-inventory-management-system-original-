/* =============================================================================
   GIMS — Inventory, Warehouses, Adjustments & Transfers client
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

    const fmtMoney = (v) => G.currency + ' ' + Number(v || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    const fmtNum   = (v) => Number(v || 0).toLocaleString(undefined, {maximumFractionDigits:2});
    const human    = (t) => String(t||'').replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase());

    /* ==========================================================
       STOCK LIST (inventory/stock.php)
       ========================================================== */
    if (document.getElementById('sBody')) initStockList();

    function initStockList() {
        const state = { page: 1, q:'', warehouse_id:'', category_id:'', filter:'' };
        const body = document.getElementById('sBody');
        const countEl = document.getElementById('sCount');
        const pager = document.getElementById('sPager');
        const pagerWrap = document.getElementById('sPagerWrap');
        const pageInfo = document.getElementById('sPageInfo');

        async function load() {
            body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({
                action:'list',
                q: state.q, warehouse_id: state.warehouse_id,
                category_id: state.category_id, filter: state.filter, page: state.page
            });
            const r = await api(`${G.baseUrl}/api/inventory.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            const items = r.data.items || [];
            const pg = r.data.pagination;
            countEl.textContent = `${pg.total} product(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-box"></i><p>No stock records found.</p></div></td></tr>';
                pagerWrap.style.display = 'none';
                return;
            }

            body.innerHTML = items.map(it => `
                <tr>
                    <td>
                        <div class="gims-cell-title">${esc(it.name)}</div>
                        <small class="text-muted">${esc(it.sku)}</small>
                    </td>
                    <td>${esc(it.category_name || '—')}</td>
                    <td class="text-end fw-semibold">${fmtNum(it.total_qty)} <small class="text-muted">${esc(it.unit)}</small></td>
                    <td class="text-end text-muted">${fmtNum(it.reserved_qty)}</td>
                    <td class="text-end text-danger">${fmtNum(it.damaged_qty)}</td>
                    <td class="text-end fw-bold text-success">${fmtNum(it.available)}</td>
                    <td class="text-end">${fmtMoney(it.stock_value)}</td>
                    <td>${it.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="${esc(G.baseUrl)}/products/view.php?id=${it.id}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                            ${window.INVENTORY_CAN_MANAGE ? `<button class="btn btn-sm btn-outline-primary adj-row" data-id="${it.id}" data-name="${esc(it.name)}" title="Adjust"><i class="bi bi-sliders"></i></button>` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.adj-row').forEach(b => b.addEventListener('click', () => {
                const pid = b.dataset.id;
                const sel = document.getElementById('adjProduct');
                sel.value = pid;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustModal')).show();
            }));

            renderPager(pg, pager, pagerWrap, pageInfo, (n) => { state.page = n; load(); });
        }

        let dT; const deb = () => { clearTimeout(dT); dT = setTimeout(() => { state.page=1; load(); }, 350); };
        document.getElementById('sSearch').addEventListener('input', e => { state.q = e.target.value.trim(); deb(); });
        document.getElementById('sWarehouse').addEventListener('change', e => { state.warehouse_id = e.target.value; state.page=1; load(); });
        document.getElementById('sCategory').addEventListener('change', e => { state.category_id = e.target.value; state.page=1; load(); });
        document.getElementById('sFilter').addEventListener('change', e => { state.filter = e.target.value; state.page=1; load(); });
        document.getElementById('sReset').addEventListener('click', () => {
            state.q=''; state.warehouse_id=''; state.category_id=''; state.filter=''; state.page=1;
            document.getElementById('sSearch').value='';
            document.getElementById('sWarehouse').value='';
            document.getElementById('sCategory').value='';
            document.getElementById('sFilter').value='';
            load();
        });

        /* Load KPI summary */
        (async () => {
            const r = await api(`${G.baseUrl}/api/inventory.php?action=summary`);
            if (r.success) {
                document.getElementById('kpiQty').textContent   = fmtNum(r.data.total_qty);
                document.getElementById('kpiValue').textContent = fmtMoney(r.data.total_value);
                document.getElementById('kpiLow').textContent   = r.data.low_stock;
                document.getElementById('kpiOut').textContent   = r.data.out_of_stock;
            }
        })();

        /* Load product list for adjust dropdown */
        (async () => {
            const r = await api(`${G.baseUrl}/api/products.php?action=lookup`);
            const sel = document.getElementById('adjProduct');
            if (r.success && r.data.items) {
                sel.innerHTML = '<option value="">— Select product —</option>' +
                    r.data.items.map(p => `<option value="${p.id}">${esc(p.name)} (${esc(p.sku)})</option>`).join('');
            }
        })();

        /* Submit quick adjustment */
        document.getElementById('adjustForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/inventory.php?action=adjust`, { method:'POST', body: payload });
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('adjustModal')).hide();
                e.target.reset();
                load();
            } else {
                toast(r.message || 'Adjustment failed.', 'danger');
            }
        });

        load();
    }

    /* ==========================================================
       MOVEMENTS (inventory/movements.php)
       ========================================================== */
    if (document.getElementById('mBody')) initMovements();

    function initMovements() {
        const state = { page:1, product_id:'', warehouse_id:'', type:'', date_from:'', date_to:'' };
        const body = document.getElementById('mBody');
        const countEl = document.getElementById('mCount');

        async function load() {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'movements', ...state});
            const r = await api(`${G.baseUrl}/api/inventory.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            const pg = r.data.pagination;
            countEl.textContent = `${pg.total} movement(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-arrow-left-right"></i><p>No movements recorded.</p></div></td></tr>';
                document.getElementById('mPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(m => `
                <tr>
                    <td><div class="gims-cell-title">${new Date(m.created_at).toLocaleString()}</div></td>
                    <td>
                        <div class="gims-cell-title">${esc(m.product_name)}</div>
                        <small class="text-muted">${esc(m.sku)}</small>
                    </td>
                    <td>${esc(m.warehouse_name)}</td>
                    <td><span class="badge badge-soft-secondary">${esc(human(m.movement_type))}</span></td>
                    <td class="text-end ${m.direction_class} fw-semibold">${m.direction_sign}${fmtNum(m.quantity)} <small>${esc(m.unit)}</small></td>
                    <td class="text-end">${fmtNum(m.balance_after)}</td>
                    <td>${m.reference_no ? `<code class="small">${esc(m.reference_no)}</code>` : '—'}</td>
                    <td class="small text-muted">${esc(m.notes || '—').substring(0, 30)}</td>
                </tr>
            `).join('');

            renderPager(pg, document.getElementById('mPager'), document.getElementById('mPagerWrap'),
                document.getElementById('mPageInfo'), (n) => { state.page = n; load(); });
        }

        document.getElementById('mWarehouse').addEventListener('change', e => { state.warehouse_id = e.target.value; state.page=1; load(); });
        document.getElementById('mType').addEventListener('change', e => { state.type = e.target.value; state.page=1; load(); });
        document.getElementById('mFrom').addEventListener('change', e => { state.date_from = e.target.value; state.page=1; load(); });
        document.getElementById('mTo').addEventListener('change', e => { state.date_to = e.target.value; state.page=1; load(); });
        document.getElementById('mReset').addEventListener('click', () => {
            state.product_id=''; state.warehouse_id=''; state.type=''; state.date_from=''; state.date_to=''; state.page=1;
            document.getElementById('mProduct').value='';
            document.getElementById('mWarehouse').value='';
            document.getElementById('mType').value='';
            document.getElementById('mFrom').value='';
            document.getElementById('mTo').value='';
            load();
        });

        /* populate product select */
        (async () => {
            const r = await api(`${G.baseUrl}/api/products.php?action=lookup`);
            const sel = document.getElementById('mProduct');
            if (r.success && r.data.items) {
                sel.innerHTML = '<option value="">All products</option>' +
                    r.data.items.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
                sel.addEventListener('change', e => { state.product_id = e.target.value; state.page=1; load(); });
            }
        })();

        load();
    }

    /* ==========================================================
       ADJUSTMENTS (inventory/adjustments.php)
       ========================================================== */
    if (document.getElementById('aBody')) initAdjustments();

    function initAdjustments() {
        const body = document.getElementById('aBody');
        const countEl = document.getElementById('aCount');

        async function load() {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', status: document.getElementById('aStatus').value});
            const r = await api(`${G.baseUrl}/api/stock_adjustments.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            countEl.textContent = `${items.length} record(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-sliders"></i><p>No adjustments yet.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(a => `
                <tr>
                    <td><span class="gims-cell-title">${esc(a.reference_no)}</span></td>
                    <td>${esc(a.warehouse_name)}</td>
                    <td>${new Date(a.adjustment_date).toLocaleDateString()}</td>
                    <td><span class="badge badge-soft-secondary">${esc(human(a.adjustment_type))}</span></td>
                    <td class="text-end">${a.item_count}</td>
                    <td>${esc(a.reason || '—')}</td>
                    <td>${a.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            ${a.status === 'pending' && window.INVENTORY_CAN_MANAGE ? `
                                <button class="btn btn-sm btn-outline-success appr-adj" data-id="${a.id}"><i class="bi bi-check2"></i></button>
                                <button class="btn btn-sm btn-outline-danger rej-adj" data-id="${a.id}"><i class="bi bi-x"></i></button>
                            ` : ''}
                            <button class="btn btn-sm btn-outline-secondary view-adj" data-id="${a.id}"><i class="bi bi-eye"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.appr-adj').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Approve adjustment and apply to stock?')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/stock_adjustments.php?action=approve`, { method:'POST', body: {id: b.dataset.id} });
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
            body.querySelectorAll('.rej-adj').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Reject this adjustment?')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/stock_adjustments.php?action=reject`, { method:'POST', body: {id: b.dataset.id} });
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
        }

        document.getElementById('aStatus').addEventListener('change', load);
        load();

        /* Modal items management */
        const itemsBody = document.getElementById('adjItems');

        window.openAdjustment = function () {
            itemsBody.innerHTML = '';
            addAdjItemRow();
            document.getElementById('adjForm').reset();
            document.getElementById('adjId').value = '';
        };

        document.getElementById('addAdjItem').addEventListener('click', addAdjItemRow);

        function addAdjItemRow() {
            const tr = document.createElement('tr');
            const options = (window.ADJUSTMENT_PRODUCTS || []).map(p =>
                `<option value="${p.id}">${esc(p.name)}</option>`).join('');
            tr.innerHTML = `
                <td>
                    <select class="form-select form-select-sm adj-product" required>
                        <option value="">— Select —</option>${options}
                    </select>
                </td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm adj-counted" required></td>
                <td class="text-end system-qty">—</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);

            tr.querySelector('.adj-product').addEventListener('change', async (e) => {
                const pid = e.target.value;
                if (!pid) { tr.querySelector('.system-qty').textContent = '—'; return; }
                const wh = document.getElementById('adjWarehouse').value;
                const r = await api(`${G.baseUrl}/api/inventory.php?action=detail&product_id=${pid}`);
                if (r.success && r.data.stock) {
                    const row = r.data.stock.find(s => String(s.warehouse_id) === String(wh));
                    tr.querySelector('.system-qty').textContent = row ? fmtNum(row.quantity) : '0';
                }
            });

            tr.querySelector('.rm-row').addEventListener('click', () => tr.remove());
        }

        document.getElementById('adjForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const pid = tr.querySelector('.adj-product').value;
                const counted = tr.querySelector('.adj-counted').value;
                if (pid && counted !== '') items.push({ product_id: pid, counted_qty: counted });
            });
            if (!items.length) { toast('Add at least one item.', 'warning'); return; }

            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            payload.items = items;

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/stock_adjustments.php?action=save`, { method:'POST', body: payload });
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('adjModal')).hide();
                load();
            } else toast(r.message || 'Save failed.', 'danger');
        });
    }

    /* ==========================================================
       TRANSFERS (inventory/transfers.php)
       ========================================================== */
    if (document.getElementById('tBody')) initTransfers();

    function initTransfers() {
        const body = document.getElementById('tBody');
        const countEl = document.getElementById('tCount');

        async function load() {
            body.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', status: document.getElementById('tStatus').value});
            const r = await api(`${G.baseUrl}/api/stock_transfers.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            countEl.textContent = `${items.length} transfer(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="7"><div class="gims-empty"><i class="bi bi-arrow-left-right"></i><p>No transfers yet.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(t => `
                <tr>
                    <td><span class="gims-cell-title">${esc(t.transfer_no)}</span></td>
                    <td>${esc(t.from_name)}</td>
                    <td>${esc(t.to_name)}</td>
                    <td>${new Date(t.transfer_date).toLocaleDateString()}</td>
                    <td class="text-end">${t.item_count}</td>
                    <td>${t.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            ${t.status === 'pending' ? `
                                <button class="btn btn-sm btn-outline-success comp-trf" data-id="${t.id}" title="Complete"><i class="bi bi-check2"></i></button>
                                <button class="btn btn-sm btn-outline-danger canc-trf" data-id="${t.id}" title="Cancel"><i class="bi bi-x"></i></button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.comp-trf').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Complete this transfer and move stock now?')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/stock_transfers.php?action=complete`, { method:'POST', body: {id: b.dataset.id} });
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
            body.querySelectorAll('.canc-trf').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Cancel this transfer?')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/stock_transfers.php?action=cancel`, { method:'POST', body: {id: b.dataset.id} });
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
        }

        document.getElementById('tStatus').addEventListener('change', load);
        load();

        /* Modal items */
        const itemsBody = document.getElementById('trfItems');

        window.openTransfer = function () {
            itemsBody.innerHTML = '';
            addTrfRow();
            document.getElementById('trfForm').reset();
        };

        document.getElementById('addTrfItem').addEventListener('click', addTrfRow);

        function addTrfRow() {
            const tr = document.createElement('tr');
            const options = (window.TRANSFER_PRODUCTS || []).map(p =>
                `<option value="${p.id}">${esc(p.name)}</option>`).join('');
            tr.innerHTML = `
                <td><select class="form-select form-select-sm trf-product" required>
                    <option value="">— Select —</option>${options}
                </select></td>
                <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm trf-qty" required></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);
            tr.querySelector('.rm-row').addEventListener('click', () => tr.remove());
        }

        document.getElementById('trfForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const pid = tr.querySelector('.trf-product').value;
                const q = tr.querySelector('.trf-qty').value;
                if (pid && q) items.push({ product_id: pid, quantity: q });
            });
            if (!items.length) { toast('Add at least one item.', 'warning'); return; }

            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            payload.items = items;

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/stock_transfers.php?action=save`, { method:'POST', body: payload });
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('trfModal')).hide();
                load();
            } else toast(r.message || 'Save failed.', 'danger');
        });
    }

    /* ==========================================================
       WAREHOUSES (warehouses/index.php)
       ========================================================== */
    if (document.getElementById('wBody')) initWarehouses();

    function initWarehouses() {
        const body = document.getElementById('wBody');
        const countEl = document.getElementById('wCount');

        async function load() {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', q: document.getElementById('wSearch').value});
            const r = await api(`${G.baseUrl}/api/warehouses.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            countEl.textContent = `${items.length} warehouse(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-building"></i><p>No warehouses.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(w => `
                <tr>
                    <td>
                        <div class="gims-cell-title">
                            ${esc(w.name)}
                            ${w.is_default == 1 ? '<span class="badge badge-soft-primary ms-1" style="font-size:9.5px">DEFAULT</span>' : ''}
                        </div>
                        <small class="text-muted">${esc(w.city || '')}${w.city && w.country ? ', ' : ''}${esc(w.country || '')}</small>
                    </td>
                    <td><code>${esc(w.code)}</code></td>
                    <td>${esc(w.manager_name || '—')}</td>
                    <td>
                        <div class="small">${esc(w.phone || '—')}</div>
                        <small class="text-muted">${esc(w.email || '')}</small>
                    </td>
                    <td class="text-end">${w.product_count}</td>
                    <td class="text-end">${fmtMoney(w.stock_value)}</td>
                    <td>${w.status_badge}</td>
                    <td class="text-end">
                        ${window.WAREHOUSE_CAN_MANAGE ? `
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-secondary edit-wh" data-json='${esc(JSON.stringify(w))}'><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger del-wh" data-id="${w.id}" data-name="${esc(w.name)}"><i class="bi bi-trash"></i></button>
                            </div>
                        ` : '—'}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.edit-wh').forEach(b => b.addEventListener('click', () => {
                const w = JSON.parse(b.dataset.json);
                openWarehouse(w);
            }));
            body.querySelectorAll('.del-wh').forEach(b => b.addEventListener('click', async () => {
                if (!confirm(`Delete warehouse "${b.dataset.name}"?`)) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/warehouses.php?action=delete`, { method:'POST', body: {id: b.dataset.id} });
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
        }

        let dT; document.getElementById('wSearch').addEventListener('input', () => {
            clearTimeout(dT); dT = setTimeout(load, 300);
        });

        window.openWarehouse = function (w) {
            document.getElementById('whModalTitle').textContent = w ? 'Edit Warehouse' : 'Add Warehouse';
            document.getElementById('whId').value      = w ? w.id : '';
            document.getElementById('whName').value    = w ? w.name : '';
            document.getElementById('whCode').value    = w ? w.code : '';
            document.getElementById('whAddress').value = w ? (w.address||'') : '';
            document.getElementById('whCity').value    = w ? (w.city||'') : '';
            document.getElementById('whCountry').value = w ? (w.country||'Sri Lanka') : 'Sri Lanka';
            document.getElementById('whManager').value = w ? (w.manager_id||'') : '';
            document.getElementById('whPhone').value   = w ? (w.phone||'') : '';
            document.getElementById('whEmail').value   = w ? (w.email||'') : '';
            document.getElementById('whStatus').value  = w ? w.status : 'active';
            document.getElementById('whDefault').checked = w ? w.is_default == 1 : false;
        };

        document.getElementById('whForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            payload.is_default = e.target.querySelector('#whDefault').checked ? 1 : 0;

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/warehouses.php?action=save`, { method:'POST', body: payload });
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('whModal')).hide();
                load();
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });

        load();
    }

    /* ==========================================================
       LOCATIONS (warehouses/locations.php)
       ========================================================== */
    if (document.getElementById('locBody')) initLocations();

    function initLocations() {
        const body = document.getElementById('locBody');
        const countEl = document.getElementById('locCount');
        const whFilter = document.getElementById('locWhFilter');

        async function load() {
            body.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';

            const whId = whFilter.value;
            const qs = new URLSearchParams({action:'locations', warehouse_id: whId || 0});
            const r = await api(`${G.baseUrl}/api/warehouses.php?${qs}`);
            // If no warehouse selected, we want locations across warehouses.
            // Use query per warehouse if blank.
            if (!r.success) { body.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            let items = r.data.items || [];
            // if blank filter, load all warehouses' locations
            if (!whId && window.LOCATIONS_WAREHOUSES) {
                const all = [];
                for (const w of window.LOCATIONS_WAREHOUSES) {
                    const rr = await api(`${G.baseUrl}/api/warehouses.php?action=locations&warehouse_id=${w.id}`);
                    if (rr.success && rr.data.items) {
                        rr.data.items.forEach(it => it._whName = w.name);
                        all.push(...rr.data.items);
                    }
                }
                items = all;
            }

            countEl.textContent = `${items.length} location(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="7"><div class="gims-empty"><i class="bi bi-geo"></i><p>No locations yet.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(l => `
                <tr>
                    <td><code>${esc(l.code)}</code></td>
                    <td>${esc(l._whName || '—')}</td>
                    <td>${esc(l.rack || '—')}</td>
                    <td>${esc(l.bin || '—')}</td>
                    <td>${esc(l.description || '—')}</td>
                    <td>${l.status === 'active' ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'}</td>
                    <td class="text-end">
                        ${window.WAREHOUSE_CAN_MANAGE ? `
                            <button class="btn btn-sm btn-outline-danger del-loc" data-id="${l.id}"><i class="bi bi-trash"></i></button>
                        ` : '—'}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.del-loc').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Delete this location?')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/warehouses.php?action=delete_location`, { method:'POST', body: {id: b.dataset.id} });
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
        }

        window.openLocation = function () {
            document.getElementById('locForm').reset();
            document.getElementById('locId').value = '';
        };

        document.getElementById('locForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/warehouses.php?action=save_location`, { method:'POST', body: payload });
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('locModal')).hide();
                load();
            } else toast(r.message, 'danger');
        });

        whFilter.addEventListener('change', load);
        load();
    }

    /* ==========================================================
       Shared pagination renderer
       ========================================================== */
    function renderPager(pg, pagerEl, wrapEl, infoEl, onChange) {
        if (!pagerEl) return;
        if (pg.pages <= 1) { wrapEl.style.display = 'none'; return; }
        wrapEl.style.display = 'flex';
        infoEl.textContent = `Showing ${pg.from}–${pg.to} of ${pg.total}`;

        const html = [];
        html.push(`<li class="page-item ${pg.page===1?'disabled':''}"><a class="page-link" href="#" data-p="${pg.page-1}">&laquo;</a></li>`);
        const start = Math.max(1, pg.page - 2);
        const end   = Math.min(pg.pages, pg.page + 2);
        if (start > 1) html.push(`<li class="page-item"><a class="page-link" href="#" data-p="1">1</a></li>`);
        if (start > 2) html.push(`<li class="page-item disabled"><span class="page-link">…</span></li>`);
        for (let i = start; i <= end; i++) {
            html.push(`<li class="page-item ${i===pg.page?'active':''}"><a class="page-link" href="#" data-p="${i}">${i}</a></li>`);
        }
        if (end < pg.pages - 1) html.push(`<li class="page-item disabled"><span class="page-link">…</span></li>`);
        if (end < pg.pages) html.push(`<li class="page-item"><a class="page-link" href="#" data-p="${pg.pages}">${pg.pages}</a></li>`);
        html.push(`<li class="page-item ${pg.page===pg.pages?'disabled':''}"><a class="page-link" href="#" data-p="${pg.page+1}">&raquo;</a></li>`);

        pagerEl.innerHTML = html.join('');
        pagerEl.querySelectorAll('a[data-p]').forEach(a => a.addEventListener('click', (e) => {
            e.preventDefault();
            const n = parseInt(a.dataset.p, 10);
            if (n >= 1 && n <= pg.pages && n !== pg.page) onChange(n);
        }));
    }
})();