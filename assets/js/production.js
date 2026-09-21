/* =============================================================================
   GIMS — Production & BOM client
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
    const fmtNum   = (v) => Number(v || 0).toLocaleString(undefined, {maximumFractionDigits:4});

    /* ==================================================================
       BOM LIST
       ================================================================== */
    if (document.getElementById('bomBody')) initBomList();

    function initBomList() {
        const state = { q:'', status:'' };
        const body = document.getElementById('bomBody');
        const countEl = document.getElementById('bomCount');

        async function load() {
            body.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/bom.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            countEl.textContent = `${items.length} BOM(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="7"><div class="gims-empty"><i class="bi bi-diagram-3"></i><p>No BOMs yet.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(b => `
                <tr>
                    <td><a href="${G.baseUrl}/bom/view.php?id=${b.id}" class="gims-cell-title text-decoration-none">${esc(b.name)}</a></td>
                    <td>
                        <div>${esc(b.product_name)}</div>
                        <small class="text-muted">${esc(b.sku)}</small>
                    </td>
                    <td><span class="badge badge-soft-secondary">v${esc(b.version)}</span></td>
                    <td class="text-end">${b.item_count}</td>
                    <td>${esc(b.created_by_name || '—')}</td>
                    <td>${b.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="${G.baseUrl}/bom/view.php?id=${b.id}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                            <button class="btn btn-sm btn-outline-secondary edit-bom" data-id="${b.id}" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger del-bom" data-id="${b.id}" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.edit-bom').forEach(b => b.addEventListener('click', async () => {
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/bom.php?action=get&id=${b.dataset.id}`);
                showLoader(false);
                if (r.success) openBom(r.data);
                else toast(r.message, 'danger');
            }));

            body.querySelectorAll('.del-bom').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Delete this BOM?')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/bom.php?action=delete`, {method:'POST', body:{id:b.dataset.id}});
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
        }

        let dT;
        document.getElementById('bomSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); load(); }, 320);
        });
        document.getElementById('bomStatus').addEventListener('change', e => { state.status = e.target.value; load(); });
        document.getElementById('bomReset').addEventListener('click', () => {
            state.q=''; state.status='';
            document.getElementById('bomSearch').value = '';
            document.getElementById('bomStatus').value = '';
            load();
        });

        /* BOM Modal */
        const itemsBody = document.getElementById('bomItems');
        const products  = window.BOM_PRODUCTS || [];
        const materials = window.BOM_MATERIALS || [];

        window.openBom = function (data) {
            data = data || null;
            document.getElementById('bomModalTitle').textContent = data ? 'Edit BOM' : 'New BOM';
            document.getElementById('bomId').value      = data ? data.id : '';
            document.getElementById('bomProduct').value = data ? data.product_id : '';
            document.getElementById('bomName').value    = data ? data.name : '';
            document.getElementById('bomVersion').value = data ? data.version : '1.0';
            document.getElementById('bomStatusInput').value = data ? data.status : 'active';
            document.getElementById('bomNotes').value   = data ? (data.notes || '') : '';

            itemsBody.innerHTML = '';
            if (data && data.items && data.items.length) {
                data.items.forEach(it => addBomRow(it));
            } else {
                addBomRow();
            }
        };

        function addBomRow(it) {
            it = it || { material_id:'', quantity:1, unit:'pcs', wastage_pct:0, notes:'' };
            const tr = document.createElement('tr');
            const opts = materials.map(m =>
                `<option value="${m.id}" data-unit="${m.unit}" ${String(m.id)===String(it.material_id)?'selected':''}>${esc(m.name)} (${esc(m.sku)})</option>`
            ).join('');
            tr.innerHTML = `
                <td><select class="form-select form-select-sm bom-material" required>
                    <option value="">— Select —</option>${opts}
                </select></td>
                <td><input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm bom-qty" value="${it.quantity}" required></td>
                <td><input type="text" class="form-control form-control-sm bom-unit" value="${esc(it.unit)}" maxlength="20"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm bom-waste" value="${it.wastage_pct}"></td>
                <td><input type="text" class="form-control form-control-sm bom-notes" value="${esc(it.notes || '')}" maxlength="200"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);

            tr.querySelector('.bom-material').addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                if (opt && opt.dataset.unit) tr.querySelector('.bom-unit').value = opt.dataset.unit;
            });
            tr.querySelector('.rm-row').addEventListener('click', () => tr.remove());
        }

        document.getElementById('addBomItem').addEventListener('click', () => addBomRow());

        document.getElementById('bomForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            payload.items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const mid = tr.querySelector('.bom-material').value;
                const qty = tr.querySelector('.bom-qty').value;
                if (!mid || !qty) return;
                payload.items.push({
                    material_id: mid, quantity: qty,
                    unit: tr.querySelector('.bom-unit').value,
                    wastage_pct: tr.querySelector('.bom-waste').value,
                    notes: tr.querySelector('.bom-notes').value
                });
            });
            if (!payload.items.length) { toast('Add at least one material.', 'warning'); return; }
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/bom.php?action=save`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('bomModal')).hide();
                load();
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });

        load();
    }

    /* ==================================================================
       BOM VIEW — calculator
       ================================================================== */
    if (document.getElementById('calcBtn')) {
        document.getElementById('calcBtn').addEventListener('click', async () => {
            const qty = document.getElementById('calcQty').value;
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/bom.php?action=calculate&bom_id=${window.BOM_ID}&quantity=${qty}`);
            showLoader(false);
            if (!r.success) { toast(r.message, 'danger'); return; }
            const body = document.getElementById('calcBody');
            body.innerHTML = r.data.items.map(it => `
                <tr>
                    <td>
                        <div class="small">${esc(it.material_name)}</div>
                        <small class="text-muted">${esc(it.sku)}</small>
                    </td>
                    <td class="text-end">
                        <strong>${fmtNum(it.required_qty)}</strong>
                        <small class="text-muted">${esc(it.unit)}</small>
                        ${it.shortage > 0 ? `<br><small class="text-danger">Short ${fmtNum(it.shortage)}</small>` : ''}
                    </td>
                </tr>
            `).join('');
            document.getElementById('calcCost').textContent = fmtMoney(r.data.total_cost);
            document.getElementById('calcResult').classList.remove('d-none');
        });
    }

    /* ==================================================================
       PRODUCTION ORDERS LIST
       ================================================================== */
    if (document.getElementById('prodBody')) initProdList();

    function initProdList() {
        const state = { page:1, q:'', status:'', date_from:'', date_to:'' };
        const body = document.getElementById('prodBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/production.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('prodCount').textContent = `${pg.total} order(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-gear"></i><p>No production orders found.</p></div></td></tr>';
                document.getElementById('prodPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(o => {
                const pct = o.quantity > 0 ? Math.min(100, Math.round((o.produced_qty / o.quantity) * 100)) : 0;
                return `
                <tr>
                    <td><a href="${G.baseUrl}/production/production-order-view.php?id=${o.id}" class="fw-semibold text-decoration-none">${esc(o.order_no)}</a></td>
                    <td>
                        <div class="gims-cell-title">${esc(o.product_name)}</div>
                        <small class="text-muted">${esc(o.sku)}</small>
                    </td>
                    <td>${fmtNum(o.quantity)}</td>
                    <td style="min-width:120px">
                        <div class="progress" style="height:6px">
                            <div class="progress-bar bg-success" style="width:${pct}%"></div>
                        </div>
                        <small class="text-muted">${pct}%</small>
                    </td>
                    <td>${esc(o.warehouse_name)}</td>
                    <td>${o.start_date ? new Date(o.start_date).toLocaleDateString() : '—'}</td>
                    <td>${o.expected_date ? new Date(o.expected_date).toLocaleDateString() : '—'}</td>
                    <td>${o.status_badge}</td>
                    <td class="text-end">
                        <a href="${G.baseUrl}/production/production-order-view.php?id=${o.id}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>`;
            }).join('');

            renderPager(pg, 'prodPager', 'prodPagerWrap', 'prodPageInfo', n => { state.page = n; load(); });
        }

        let dT;
        document.getElementById('prodSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        ['prodStatus','prodFrom','prodTo'].forEach(id => {
            document.getElementById((''+id).replace('prod',''))?.addEventListener('change', () => {});
        });
        document.getElementById('prodStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });
        document.getElementById('prodFrom').addEventListener('change', e => { state.date_from = e.target.value; state.page=1; load(); });
        document.getElementById('prodTo').addEventListener('change', e => { state.date_to = e.target.value; state.page=1; load(); });
        document.getElementById('prodReset').addEventListener('click', () => {
            state.q=''; state.status=''; state.date_from=''; state.date_to=''; state.page=1;
            ['prodSearch','prodStatus','prodFrom','prodTo'].forEach(id => document.getElementById(id).value = '');
            load();
        });

        load();
    }

    /* ==================================================================
       PRODUCTION CREATE
       ================================================================== */
    if (document.getElementById('prodOrderForm')) initProdCreate();

    function initProdCreate() {
        const form = document.getElementById('prodOrderForm');

        function filterBoms() {
            const pid = document.getElementById('prodProduct').value;
            const sel = document.getElementById('prodBom');
            [...sel.options].forEach(opt => {
                if (!opt.value) return;
                opt.style.display = (!pid || opt.dataset.product === pid) ? '' : 'none';
            });
        }
        document.getElementById('prodProduct').addEventListener('change', () => {
            filterBoms();
            preview();
        });
        document.getElementById('prodBom').addEventListener('change', preview);

        async function preview() {
            const bomId = document.getElementById('prodBom').value;
            const qty = parseFloat(document.querySelector('input[name="quantity"]').value) || 0;
            if (!bomId || !qty) { document.getElementById('materialPreview').innerHTML = '<p class="text-muted small mb-0">Select a product, BOM and quantity.</p>'; return; }

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/bom.php?action=calculate&bom_id=${bomId}&quantity=${qty}`);
            showLoader(false);
            if (!r.success) return;

            if (!r.data.items.length) {
                document.getElementById('materialPreview').innerHTML = '<p class="text-muted small mb-0">No items in this BOM.</p>';
                return;
            }
            document.getElementById('materialPreview').innerHTML = `
                <table class="table gims-table mb-0 small">
                    <tbody>${r.data.items.map(it => `
                        <tr>
                            <td>${esc(it.material_name)}</td>
                            <td class="text-end">${fmtNum(it.required_qty)} ${esc(it.unit)}</td>
                        </tr>
                    `).join('')}
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td>Est. Cost</td>
                            <td class="text-end fw-bold">${fmtMoney(r.data.total_cost)}</td>
                        </tr>
                    </tfoot>
                </table>
            `;
        }

        document.querySelector('input[name="quantity"]').addEventListener('input', preview);

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const payload = Object.fromEntries(fd.entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/production.php?action=save`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                setTimeout(() => window.location.href = `${G.baseUrl}/production/production-order-view.php?id=${r.data.id}`, 500);
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });

        filterBoms();
    }

    /* ==================================================================
       PRODUCTION ORDER VIEW — status buttons + material/output forms
       ================================================================== */
    document.querySelectorAll('.status-btn').forEach(b => b.addEventListener('click', async () => {
        const labels = { in_progress: 'start', paused: 'pause', completed: 'complete' };
        if (!confirm(`Are you sure you want to ${labels[b.dataset.status] || 'update'} this order?`)) return;
        showLoader(true);
        const r = await api(`${G.baseUrl}/api/production.php?action=update_status`, {method:'POST', body:{id:b.dataset.id, status:b.dataset.status}});
        showLoader(false);
        if (r.success) { toast(r.message, 'success'); setTimeout(() => location.reload(), 600); } else toast(r.message, 'danger');
    }));

    document.getElementById('issueForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = Object.fromEntries(fd.entries());
        showLoader(true);
        const r = await api(`${G.baseUrl}/api/production.php?action=issue_material`, {method:'POST', body: payload});
        showLoader(false);
        if (r.success) { toast(r.message, 'success'); setTimeout(() => location.reload(), 600); } else toast(r.message, 'danger');
    });

    document.getElementById('outputForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = Object.fromEntries(fd.entries());
        showLoader(true);
        const r = await api(`${G.baseUrl}/api/production.php?action=add_output`, {method:'POST', body: payload});
        showLoader(false);
        if (r.success) { toast(r.message, 'success'); setTimeout(() => location.reload(), 600); } else toast(r.message, 'danger');
    });

    /* ==================================================================
       Shared pager
       ================================================================== */
    function renderPager(pg, pagerId, wrapId, infoId, onChange) {
        const pager = document.getElementById(pagerId);
        const wrap  = document.getElementById(wrapId);
        const info  = document.getElementById(infoId);
        if (!pager) return;
        if (pg.pages <= 1) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'flex';
        info.textContent = `Showing ${pg.from}–${pg.to} of ${pg.total}`;
        const html = [];
        html.push(`<li class="page-item ${pg.page===1?'disabled':''}"><a class="page-link" href="#" data-p="${pg.page-1}">&laquo;</a></li>`);
        for (let i = Math.max(1, pg.page-2); i <= Math.min(pg.pages, pg.page+2); i++) {
            html.push(`<li class="page-item ${i===pg.page?'active':''}"><a class="page-link" href="#" data-p="${i}">${i}</a></li>`);
        }
        html.push(`<li class="page-item ${pg.page===pg.pages?'disabled':''}"><a class="page-link" href="#" data-p="${pg.page+1}">&raquo;</a></li>`);
        pager.innerHTML = html.join('');
        pager.querySelectorAll('a[data-p]').forEach(a => a.addEventListener('click', e => {
            e.preventDefault();
            const n = parseInt(a.dataset.p, 10);
            if (n >= 1 && n <= pg.pages && n !== pg.page) onChange(n);
        }));
    }
})();