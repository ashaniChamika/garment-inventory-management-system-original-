/* =============================================================================
   GIMS — Purchasing client (suppliers, POs, GRNs, returns)
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

    /* ==================================================================
       SUPPLIERS
       ================================================================== */
    if (document.getElementById('supBody')) initSuppliers();

    function initSuppliers() {
        const state = { page: 1, q: '', status: '' };
        const body = document.getElementById('supBody');
        const countEl = document.getElementById('supCount');

        async function load() {
            body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/suppliers.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            const items = r.data.items || [];
            const pg = r.data.pagination;
            countEl.textContent = `${pg.total} supplier(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-people"></i><p>No suppliers yet.</p></div></td></tr>';
                document.getElementById('supPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(s => `
                <tr>
                    <td>
                        <div class="gims-cell-title">${esc(s.company_name)}</div>
                        ${s.tax_number ? `<small class="text-muted">${esc(s.tax_number)}</small>` : ''}
                    </td>
                    <td>
                        <div>${esc(s.contact_person || '—')}</div>
                        <small class="text-muted">${esc(s.phone || '')}</small>
                    </td>
                    <td>${esc(s.city || '—')}</td>
                    <td>${esc(s.payment_terms || '—')}</td>
                    <td class="text-end">${s.po_count}</td>
                    <td class="text-end">${fmtMoney(s.total_purchased)}</td>
                    <td class="text-end ${s.outstanding > 0 ? 'text-danger fw-semibold' : ''}">${fmtMoney(s.outstanding)}</td>
                    <td>${s.status_badge}</td>
                    <td class="text-end">
                        ${window.SUPPLIER_CAN_MANAGE ? `
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-secondary edit-sup" data-json='${esc(JSON.stringify(s))}' title="Edit"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger del-sup" data-id="${s.id}" data-name="${esc(s.company_name)}" title="Delete"><i class="bi bi-trash"></i></button>
                            </div>
                        ` : '—'}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.edit-sup').forEach(b => b.addEventListener('click', () => {
                openSupplier(JSON.parse(b.dataset.json));
            }));
            body.querySelectorAll('.del-sup').forEach(b => b.addEventListener('click', async () => {
                if (!confirm(`Delete supplier "${b.dataset.name}"?`)) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/suppliers.php?action=delete`, { method:'POST', body:{ id: b.dataset.id }});
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));

            renderPager(pg);
        }

        function renderPager(pg) {
            const pager = document.getElementById('supPager');
            const wrap  = document.getElementById('supPagerWrap');
            const info  = document.getElementById('supPageInfo');
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
                if (n >= 1 && n <= pg.pages && n !== pg.page) { state.page = n; load(); }
            }));
        }

        let dT; document.getElementById('supSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        document.getElementById('supStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });

        window.openSupplier = function (s) {
            document.getElementById('supModalTitle').textContent = s ? 'Edit Supplier' : 'Add Supplier';
            document.getElementById('supId').value      = s ? s.id : '';
            document.getElementById('supCompany').value = s ? s.company_name : '';
            document.getElementById('supContact').value = s ? (s.contact_person || '') : '';
            document.getElementById('supPhone').value   = s ? (s.phone || '') : '';
            document.getElementById('supEmail').value   = s ? (s.email || '') : '';
            document.getElementById('supTax').value     = s ? (s.tax_number || '') : '';
            document.getElementById('supAddress').value = s ? (s.address || '') : '';
            document.getElementById('supCity').value    = s ? (s.city || '') : '';
            document.getElementById('supCountry').value = s ? (s.country || 'Sri Lanka') : 'Sri Lanka';
            document.getElementById('supTerms').value   = s ? (s.payment_terms || '') : '';
            document.getElementById('supOpening').value = s ? (s.opening_balance || 0) : 0;
            document.getElementById('supStatusInput').value = s ? s.status : 'active';
            document.getElementById('supBank').value    = s ? (s.bank_details || '') : '';
        };

        document.getElementById('supForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/suppliers.php?action=save`, { method:'POST', body: payload });
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('supModal')).hide();
                load();
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });

        load();
    }

    /* ==================================================================
       PURCHASE ORDERS LIST
       ================================================================== */
    if (document.getElementById('poBody')) initPoList();

    function initPoList() {
        const state = { page:1, q:'', supplier_id:'', status:'', date_from:'', date_to:'' };
        const body = document.getElementById('poBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/purchases.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="10" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('poCount').textContent = `${pg.total} purchase order(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="10"><div class="gims-empty"><i class="bi bi-cart"></i><p>No purchase orders found.</p></div></td></tr>';
                document.getElementById('poPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(p => `
                <tr>
                    <td><a class="fw-semibold text-decoration-none" href="${G.baseUrl}/purchases/purchase-order-view.php?id=${p.id}">${esc(p.po_number)}</a></td>
                    <td>${esc(p.company_name)}</td>
                    <td>${esc(p.warehouse_name)}</td>
                    <td>${new Date(p.order_date).toLocaleDateString()}</td>
                    <td>${p.expected_date ? new Date(p.expected_date).toLocaleDateString() : '—'}</td>
                    <td class="text-end">${p.item_count}</td>
                    <td class="text-end fw-semibold">${fmtMoney(p.total)}</td>
                    <td class="text-end ${p.balance > 0 ? 'text-danger' : 'text-success'}">${fmtMoney(p.balance)}</td>
                    <td>${p.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="${G.baseUrl}/purchases/purchase-order-view.php?id=${p.id}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                            ${['draft','pending'].includes(p.status) ? `<a href="${G.baseUrl}/purchases/purchase-order-create.php?id=${p.id}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');

            renderPager(pg, 'poPager', 'poPagerWrap', 'poPageInfo', n => { state.page = n; load(); });
        }

        let dT; document.getElementById('poSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        ['poSupplier','poStatus','poFrom','poTo'].forEach(id => {
            document.getElementById(id).addEventListener('change', e => {
                const map = { poSupplier:'supplier_id', poStatus:'status', poFrom:'date_from', poTo:'date_to' };
                state[map[id]] = e.target.value; state.page=1; load();
            });
        });
        document.getElementById('poReset').addEventListener('click', () => {
            Object.assign(state, {q:'', supplier_id:'', status:'', date_from:'', date_to:'', page:1});
            ['poSearch','poSupplier','poStatus','poFrom','poTo'].forEach(id => document.getElementById(id).value = '');
            load();
        });

        load();
    }

    /* ==================================================================
       PO CREATE/EDIT
       ================================================================== */
    if (document.getElementById('poForm') && document.getElementById('poItems')) initPoForm();

    function initPoForm() {
        const itemsBody = document.getElementById('poItems');
        const products  = window.PO_PRODUCTS || [];

        function addRow(data) {
            data = data || { product_id:'', quantity:1, unit_price:0, discount:0, tax:0 };
            const tr = document.createElement('tr');
            const opts = products.map(p =>
                `<option value="${p.id}" data-price="${p.cost_price}" ${String(p.id)===String(data.product_id)?'selected':''}>${esc(p.name)} (${esc(p.sku)})</option>`
            ).join('');
            tr.innerHTML = `
                <td><select class="form-select form-select-sm po-product" required>
                    <option value="">— Select —</option>${opts}
                </select></td>
                <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm po-qty" required value="${data.quantity}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm po-price" required value="${data.unit_price}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm po-disc" value="${data.discount}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm po-tax" value="${data.tax}"></td>
                <td class="text-end fw-semibold line-total">—</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);

            tr.querySelector('.po-product').addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                if (opt && opt.dataset.price) tr.querySelector('.po-price').value = opt.dataset.price;
                recalc();
            });
            tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));
            tr.querySelector('.rm-row').addEventListener('click', () => { tr.remove(); recalc(); });
            recalc();
        }

        function recalc() {
            let subtotal = 0;
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const qty = parseFloat(tr.querySelector('.po-qty').value) || 0;
                const price = parseFloat(tr.querySelector('.po-price').value) || 0;
                const disc = parseFloat(tr.querySelector('.po-disc').value) || 0;
                const tax  = parseFloat(tr.querySelector('.po-tax').value) || 0;
                const total = (qty * price) - disc + tax;
                tr.querySelector('.line-total').textContent = fmtMoney(total);
                subtotal += qty * price;
            });
            const disc = parseFloat(document.getElementById('poDiscount').value) || 0;
            const tax  = parseFloat(document.getElementById('poTax').value) || 0;
            const ship = parseFloat(document.getElementById('poShipping').value) || 0;
            document.getElementById('poSubtotal').textContent = fmtMoney(subtotal);
            document.getElementById('poGrandTotal').textContent = fmtMoney(subtotal - disc + tax + ship);
        }

        document.getElementById('addPoItem').addEventListener('click', () => addRow());
        ['poDiscount','poTax','poShipping'].forEach(id =>
            document.getElementById(id).addEventListener('input', recalc));

        // Load existing items
        (window.PO_ITEMS || []).forEach(it => addRow(it));
        if (!(window.PO_ITEMS || []).length) addRow();

        async function saveAsSubmit(submit) {
            const form = document.getElementById('poForm');
            const fd = new FormData(form);
            const payload = Object.fromEntries(fd.entries());
            payload.items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const pid = tr.querySelector('.po-product').value;
                const qty = tr.querySelector('.po-qty').value;
                if (!pid || !qty) return;
                payload.items.push({
                    product_id: pid,
                    quantity:   qty,
                    unit_price: tr.querySelector('.po-price').value,
                    discount:   tr.querySelector('.po-disc').value,
                    tax:        tr.querySelector('.po-tax').value
                });
            });

            if (!payload.items.length) { toast('Add at least one line item.', 'warning'); return; }
            if (!payload.supplier_id)   { toast('Select a supplier.', 'warning'); return; }
            if (!payload.warehouse_id)  { toast('Select a warehouse.', 'warning'); return; }

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/purchases.php?action=save_po`, { method:'POST', body: payload });
            if (!r.success) { showLoader(false); toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger'); return; }

            if (submit) {
                const r2 = await api(`${G.baseUrl}/api/purchases.php?action=submit_po`, { method:'POST', body:{ id: r.data.id }});
                showLoader(false);
                if (r2.success) toast('PO saved and submitted.', 'success');
                else toast(r2.message, 'warning');
            } else {
                showLoader(false);
                toast(r.message, 'success');
            }
            setTimeout(() => window.location.href = `${G.baseUrl}/purchases/purchase-order-view.php?id=${r.data.id}`, 400);
        }

        document.getElementById('poForm').addEventListener('submit', e => { e.preventDefault(); saveAsSubmit(false); });
        document.getElementById('poSaveAndSubmit').addEventListener('click', () => saveAsSubmit(true));
    }

    /* ==================================================================
       PO VIEW (approve/submit buttons)
       ================================================================== */
    document.getElementById('btnSubmitPo')?.addEventListener('click', async function () {
        if (!confirm('Submit this PO for approval?')) return;
        showLoader(true);
        const r = await api(`${G.baseUrl}/api/purchases.php?action=submit_po`, { method:'POST', body:{ id: this.dataset.id }});
        showLoader(false);
        if (r.success) { toast(r.message, 'success'); setTimeout(() => location.reload(), 600); } else toast(r.message, 'danger');
    });
    document.getElementById('btnApprovePo')?.addEventListener('click', async function () {
        if (!confirm('Approve this purchase order?')) return;
        showLoader(true);
        const r = await api(`${G.baseUrl}/api/purchases.php?action=approve_po`, { method:'POST', body:{ id: this.dataset.id }});
        showLoader(false);
        if (r.success) { toast(r.message, 'success'); setTimeout(() => location.reload(), 600); } else toast(r.message, 'danger');
    });

    /* ==================================================================
       GRN LIST
       ================================================================== */
    if (document.getElementById('grnBody')) initGrnList();

    function initGrnList() {
        const state = { page:1, q:'', status:'' };
        const body = document.getElementById('grnBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'grn_list', ...state});
            const r = await api(`${G.baseUrl}/api/purchases.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="10" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('grnCount').textContent = `${pg.total} GRN(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="10"><div class="gims-empty"><i class="bi bi-box-arrow-in-down"></i><p>No goods received yet.</p></div></td></tr>';
                document.getElementById('grnPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(g => `
                <tr>
                    <td><span class="gims-cell-title">${esc(g.grn_number)}</span></td>
                    <td>${g.po_number ? `<a href="${G.baseUrl}/purchases/purchase-order-view.php?id=${g.po_id}" class="text-decoration-none">${esc(g.po_number)}</a>` : '—'}</td>
                    <td>${esc(g.company_name)}</td>
                    <td>${esc(g.warehouse_name)}</td>
                    <td>${new Date(g.received_date).toLocaleDateString()}</td>
                    <td>${esc(g.invoice_no || '—')}</td>
                    <td class="text-end">${g.item_count}</td>
                    <td class="text-end fw-semibold">${fmtMoney(g.total)}</td>
                    <td>${g.status_badge}</td>
                    <td class="text-end">
                        ${g.status === 'draft' ? `
                            <button class="btn btn-sm btn-outline-success complete-grn" data-id="${g.id}" title="Complete"><i class="bi bi-check2"></i></button>
                            <a href="${G.baseUrl}/purchases/grn-create.php?id=${g.id}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                        ` : ''}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.complete-grn').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Complete this GRN and add stock now?')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/purchases.php?action=complete_grn`, { method:'POST', body:{ id: b.dataset.id }});
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));

            renderPager(pg, 'grnPager', 'grnPagerWrap', 'grnPageInfo', n => { state.page = n; load(); });
        }

        let dT; document.getElementById('grnSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        document.getElementById('grnStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });
        document.getElementById('grnReset').addEventListener('click', () => {
            state.q=''; state.status=''; state.page=1;
            document.getElementById('grnSearch').value='';
            document.getElementById('grnStatus').value='';
            load();
        });

        load();
    }

    /* ==================================================================
       GRN CREATE
       ================================================================== */
    if (document.getElementById('grnForm') && document.getElementById('grnItems')) initGrnForm();

    function initGrnForm() {
        const itemsBody = document.getElementById('grnItems');
        const products  = window.GRN_PRODUCTS || [];

        function addRow(data) {
            data = data || { product_id:'', quantity:1, rejected_qty:0, unit_cost:0, po_item_id:null };
            const tr = document.createElement('tr');
            const opts = products.map(p =>
                `<option value="${p.id}" data-cost="${p.cost_price}" ${String(p.id)===String(data.product_id)?'selected':''}>${esc(p.name)} (${esc(p.sku)})</option>`
            ).join('');
            tr.innerHTML = `
                <td><select class="form-select form-select-sm grn-product" required>
                    <option value="">— Select —</option>${opts}
                </select></td>
                <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm grn-qty" value="${data.quantity}" required></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm grn-rej" value="${data.rejected_qty}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm grn-cost" value="${data.unit_cost}"></td>
                <td><input type="hidden" class="grn-po-item" value="${data.po_item_id || ''}">
                    <span class="text-muted small">${data.po_item_id ? 'From PO' : '—'}</span></td>
                <td class="text-end fw-semibold line-total">—</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);

            tr.querySelector('.grn-product').addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                if (opt && opt.dataset.cost) tr.querySelector('.grn-cost').value = opt.dataset.cost;
                recalc();
            });
            tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));
            tr.querySelector('.rm-row').addEventListener('click', () => { tr.remove(); recalc(); });
            recalc();
        }

        function recalc() {
            let total = 0, count = 0;
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const qty = parseFloat(tr.querySelector('.grn-qty').value) || 0;
                const rej = parseFloat(tr.querySelector('.grn-rej').value) || 0;
                const cost= parseFloat(tr.querySelector('.grn-cost').value) || 0;
                const lineTotal = (qty - rej) * cost;
                tr.querySelector('.line-total').textContent = fmtMoney(lineTotal);
                total += lineTotal; count++;
            });
            document.getElementById('grnItemsCount').textContent = count;
            document.getElementById('grnTotal').textContent = fmtMoney(total);
        }

        document.getElementById('addGrnItem').addEventListener('click', () => addRow());

        (window.GRN_ITEMS || []).forEach(addRow);
        if (!(window.GRN_ITEMS || []).length) addRow();

        // PO Selection behaviour: prefill supplier/warehouse + load items
        const poSelect = document.getElementById('grnPoSelect');
        if (poSelect) {
            poSelect.addEventListener('change', async function () {
                const opt = this.selectedOptions[0];
                if (!opt || !opt.value) return;
                const supplierId = opt.dataset.supplier;
                const whId = opt.dataset.warehouse;
                if (supplierId) document.getElementById('grnSupplier').value = supplierId;
                if (whId) document.querySelector('#grnForm select[name="warehouse_id"]').value = whId;

                showLoader(true);
                const r = await api(`${G.baseUrl}/api/purchases.php?action=po_items_for_grn&po_id=${opt.value}`);
                showLoader(false);
                if (r.success && r.data.items.length) {
                    itemsBody.innerHTML = '';
                    r.data.items.forEach(p => addRow({
                        product_id: p.product_id,
                        quantity: p.remaining,
                        rejected_qty: 0,
                        unit_cost: p.unit_price,
                        po_item_id: p.id
                    }));
                } else {
                    toast('No outstanding items on this PO.', 'warning');
                }
            });

            // Auto trigger if po_id was preset
            if (window.GRN_PO_ID && !window.GRN_EDIT_ID) {
                setTimeout(() => poSelect.dispatchEvent(new Event('change')), 300);
            }
        }

        async function save(complete) {
            const form = document.getElementById('grnForm');
            const fd = new FormData(form);
            const payload = Object.fromEntries(fd.entries());
            payload.items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const pid = tr.querySelector('.grn-product').value;
                const qty = tr.querySelector('.grn-qty').value;
                if (!pid || !qty) return;
                payload.items.push({
                    product_id:   pid,
                    quantity:     qty,
                    rejected_qty: tr.querySelector('.grn-rej').value,
                    unit_cost:    tr.querySelector('.grn-cost').value,
                    po_item_id:   tr.querySelector('.grn-po-item').value || null
                });
            });
            payload.complete_now = complete;

            if (!payload.items.length) { toast('Add at least one item.', 'warning'); return; }
            if (!payload.supplier_id)   { toast('Select a supplier.', 'warning'); return; }
            if (!payload.warehouse_id)  { toast('Select a warehouse.', 'warning'); return; }

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/purchases.php?action=save_grn`, { method:'POST', body: payload });
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                setTimeout(() => window.location.href = `${G.baseUrl}/purchases/goods-received.php`, 600);
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        }

        document.getElementById('grnSaveDraft').addEventListener('click', () => save(false));
        document.getElementById('grnSaveComplete').addEventListener('click', () => {
            if (!confirm('Complete this GRN and update stock now?')) return;
            save(true);
        });
    }

    /* ==================================================================
       PURCHASE RETURNS
       ================================================================== */
    if (document.getElementById('retBody')) initReturns();

    function initReturns() {
        const body = document.getElementById('retBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'returns_list', status: document.getElementById('retStatus').value});
            const r = await api(`${G.baseUrl}/api/purchases.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            const items = r.data.items || [];
            document.getElementById('retCount').textContent = `${items.length} return(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-arrow-return-left"></i><p>No returns yet.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(rr => `
                <tr>
                    <td><span class="gims-cell-title">${esc(rr.return_no)}</span></td>
                    <td>${esc(rr.company_name)}</td>
                    <td>${esc(rr.warehouse_name)}</td>
                    <td>${esc(rr.po_number || '—')}</td>
                    <td>${new Date(rr.return_date).toLocaleDateString()}</td>
                    <td>${esc((rr.reason || '—').substring(0, 40))}</td>
                    <td class="text-end fw-semibold">${fmtMoney(rr.total)}</td>
                    <td>${rr.status_badge}</td>
                    <td class="text-end">
                        ${rr.status === 'pending' ? `
                            <button class="btn btn-sm btn-outline-success complete-ret" data-id="${rr.id}" title="Complete"><i class="bi bi-check2"></i></button>
                        ` : ''}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.complete-ret').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Complete this return? Stock will be deducted.')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/purchases.php?action=complete_return`, { method:'POST', body:{ id: b.dataset.id }});
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
        }

        document.getElementById('retStatus').addEventListener('change', load);
        load();

        /* Modal */
        const itemsBody = document.getElementById('retItems');
        window.openReturn = function () { itemsBody.innerHTML = ''; addRetRow(); };

        function addRetRow() {
            const products = window.RET_PRODUCTS || [];
            const tr = document.createElement('tr');
            const opts = products.map(p =>
                `<option value="${p.id}" data-cost="${p.cost_price}">${esc(p.name)} (${esc(p.sku)})</option>`
            ).join('');
            tr.innerHTML = `
                <td><select class="form-select form-select-sm ret-product" required><option value="">— Select —</option>${opts}</select></td>
                <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm ret-qty" value="1" required></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm ret-cost" value="0"></td>
                <td class="text-end fw-semibold line-total">—</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);

            tr.querySelector('.ret-product').addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                if (opt && opt.dataset.cost) tr.querySelector('.ret-cost').value = opt.dataset.cost;
                recalc();
            });
            tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));
            tr.querySelector('.rm-row').addEventListener('click', () => { tr.remove(); recalc(); });
            recalc();
        }

        function recalc() {
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const qty = parseFloat(tr.querySelector('.ret-qty').value) || 0;
                const cost = parseFloat(tr.querySelector('.ret-cost').value) || 0;
                tr.querySelector('.line-total').textContent = fmtMoney(qty * cost);
            });
        }

        document.getElementById('addRetItem').addEventListener('click', addRetRow);

        document.getElementById('retForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            payload.items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const pid = tr.querySelector('.ret-product').value;
                const qty = tr.querySelector('.ret-qty').value;
                if (!pid || !qty) return;
                payload.items.push({
                    product_id: pid,
                    quantity:   qty,
                    unit_cost:  tr.querySelector('.ret-cost').value
                });
            });

            if (!payload.items.length) { toast('Add at least one item.', 'warning'); return; }

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/purchases.php?action=save_return`, { method:'POST', body: payload });
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('retModal')).hide();
                load();
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });
    }

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
        const s = Math.max(1, pg.page-2), e = Math.min(pg.pages, pg.page+2);
        if (s > 1) html.push(`<li class="page-item"><a class="page-link" href="#" data-p="1">1</a></li>`);
        if (s > 2) html.push(`<li class="page-item disabled"><span class="page-link">…</span></li>`);
        for (let i = s; i <= e; i++) {
            html.push(`<li class="page-item ${i===pg.page?'active':''}"><a class="page-link" href="#" data-p="${i}">${i}</a></li>`);
        }
        if (e < pg.pages - 1) html.push(`<li class="page-item disabled"><span class="page-link">…</span></li>`);
        if (e < pg.pages) html.push(`<li class="page-item"><a class="page-link" href="#" data-p="${pg.pages}">${pg.pages}</a></li>`);
        html.push(`<li class="page-item ${pg.page===pg.pages?'disabled':''}"><a class="page-link" href="#" data-p="${pg.page+1}">&raquo;</a></li>`);

        pager.innerHTML = html.join('');
        pager.querySelectorAll('a[data-p]').forEach(a => a.addEventListener('click', ev => {
            ev.preventDefault();
            const n = parseInt(a.dataset.p, 10);
            if (n >= 1 && n <= pg.pages && n !== pg.page) onChange(n);
        }));
    }
})();