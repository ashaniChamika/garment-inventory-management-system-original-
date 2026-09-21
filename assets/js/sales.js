/* =============================================================================
   GIMS — Sales client (customers, SOs, invoices, deliveries, returns)
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
    const fmtNum = (v) => Number(v || 0).toLocaleString(undefined, {maximumFractionDigits:2});

    /* ==================================================================
       CUSTOMERS
       ================================================================== */
    if (document.getElementById('custBody')) initCustomers();

    function initCustomers() {
        const state = { page:1, q:'', status:'' };
        const body = document.getElementById('custBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/customers.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('custCount').textContent = `${pg.total} customer(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-people"></i><p>No customers yet.</p></div></td></tr>';
                document.getElementById('custPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(c => `
                <tr>
                    <td>
                        <div class="gims-cell-title">${esc(c.name)}</div>
                        ${c.company ? `<small class="text-muted">${esc(c.company)}</small>` : ''}
                    </td>
                    <td>
                        <div>${esc(c.phone || '—')}</div>
                        <small class="text-muted">${esc(c.email || '')}</small>
                    </td>
                    <td>${esc(c.city || '—')}</td>
                    <td class="text-end">${fmtMoney(c.credit_limit)}</td>
                    <td class="text-end">${c.order_count}</td>
                    <td class="text-end">${fmtMoney(c.total_sales)}</td>
                    <td class="text-end ${c.outstanding > 0 ? 'text-danger fw-semibold' : ''}">${fmtMoney(c.outstanding)}</td>
                    <td>${c.status_badge}</td>
                    <td class="text-end">
                        ${window.CUSTOMER_CAN_MANAGE ? `
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-secondary edit-cust" data-json='${esc(JSON.stringify(c))}'><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger del-cust" data-id="${c.id}" data-name="${esc(c.name)}"><i class="bi bi-trash"></i></button>
                            </div>
                        ` : '—'}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.edit-cust').forEach(b => b.addEventListener('click', () => openCustomer(JSON.parse(b.dataset.json))));
            body.querySelectorAll('.del-cust').forEach(b => b.addEventListener('click', async () => {
                if (!confirm(`Delete customer "${b.dataset.name}"?`)) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/customers.php?action=delete`, {method:'POST', body:{id:b.dataset.id}});
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));

            renderPager(pg, 'custPager', 'custPagerWrap', 'custPageInfo', n => { state.page = n; load(); });
        }

        let dT;
        document.getElementById('custSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        document.getElementById('custStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });

        window.openCustomer = function (c) {
            document.getElementById('custModalTitle').textContent = c ? 'Edit Customer' : 'Add Customer';
            document.getElementById('custId').value       = c ? c.id : '';
            document.getElementById('custName').value     = c ? c.name : '';
            document.getElementById('custCompany').value  = c ? (c.company||'') : '';
            document.getElementById('custPhone').value    = c ? (c.phone||'') : '';
            document.getElementById('custEmail').value    = c ? (c.email||'') : '';
            document.getElementById('custTax').value      = c ? (c.tax_number||'') : '';
            document.getElementById('custAddress').value  = c ? (c.address||'') : '';
            document.getElementById('custCity').value     = c ? (c.city||'') : '';
            document.getElementById('custCountry').value  = c ? (c.country||'Sri Lanka') : 'Sri Lanka';
            document.getElementById('custCredit').value   = c ? (c.credit_limit||0) : 0;
            document.getElementById('custStatusInput').value = c ? c.status : 'active';
        };

        document.getElementById('custForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(e.target).entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/customers.php?action=save`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('custModal')).hide();
                load();
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });

        load();
    }

    /* ==================================================================
       SALES ORDERS LIST
       ================================================================== */
    if (document.getElementById('soBody')) initSoList();

    function initSoList() {
        const state = { page:1, q:'', status:'', date_from:'', date_to:'' };
        const body = document.getElementById('soBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/sales.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('soCount').textContent = `${pg.total} order(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-receipt"></i><p>No sales orders yet.</p></div></td></tr>';
                document.getElementById('soPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(o => `
                <tr>
                    <td><a href="${G.baseUrl}/sales/sales-order-view.php?id=${o.id}" class="fw-semibold text-decoration-none">${esc(o.order_no)}</a></td>
                    <td>
                        <div class="gims-cell-title">${esc(o.customer_name)}</div>
                        ${o.customer_company ? `<small class="text-muted">${esc(o.customer_company)}</small>` : ''}
                    </td>
                    <td>${esc(o.warehouse_name)}</td>
                    <td>${new Date(o.order_date).toLocaleDateString()}</td>
                    <td>${o.delivery_date ? new Date(o.delivery_date).toLocaleDateString() : '—'}</td>
                    <td class="text-end">${o.item_count}</td>
                    <td class="text-end fw-semibold">${fmtMoney(o.total)}</td>
                    <td>${o.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="${G.baseUrl}/sales/sales-order-view.php?id=${o.id}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                            ${['draft','pending'].includes(o.status) ? `<a href="${G.baseUrl}/sales/sales-order-create.php?id=${o.id}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');

            renderPager(pg, 'soPager', 'soPagerWrap', 'soPageInfo', n => { state.page = n; load(); });
        }

        let dT;
        document.getElementById('soSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        document.getElementById('soStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });
        document.getElementById('soFrom').addEventListener('change', e => { state.date_from = e.target.value; state.page=1; load(); });
        document.getElementById('soTo').addEventListener('change', e => { state.date_to = e.target.value; state.page=1; load(); });
        document.getElementById('soReset').addEventListener('click', () => {
            Object.assign(state, {q:'', status:'', date_from:'', date_to:'', page:1});
            ['soSearch','soStatus','soFrom','soTo'].forEach(id => document.getElementById(id).value = '');
            load();
        });

        load();
    }

    /* ==================================================================
       SALES ORDER CREATE
       ================================================================== */
    if (document.getElementById('soForm') && document.getElementById('soItems')) initSoCreate();

    function initSoCreate() {
        const itemsBody = document.getElementById('soItems');
        const products = window.SO_PRODUCTS || [];

        function addRow(data) {
            data = data || { product_id:'', quantity:1, unit_price:0, discount:0, tax:0 };
            const tr = document.createElement('tr');
            const opts = products.map(p =>
                `<option value="${p.id}" data-price="${p.price}" ${String(p.id)===String(data.product_id)?'selected':''}>${esc(p.name)} (${esc(p.sku)})</option>`
            ).join('');
            tr.innerHTML = `
                <td><select class="form-select form-select-sm so-product" required>
                    <option value="">— Select —</option>${opts}
                </select></td>
                <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm so-qty" required value="${data.quantity}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm so-price" required value="${data.unit_price}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm so-disc" value="${data.discount}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm so-tax" value="${data.tax}"></td>
                <td class="text-end fw-semibold line-total">—</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);

            tr.querySelector('.so-product').addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                if (opt && opt.dataset.price) tr.querySelector('.so-price').value = opt.dataset.price;
                recalc();
            });
            tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));
            tr.querySelector('.rm-row').addEventListener('click', () => { tr.remove(); recalc(); });
            recalc();
        }

        function recalc() {
            let subtotal = 0;
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const qty = parseFloat(tr.querySelector('.so-qty').value) || 0;
                const price = parseFloat(tr.querySelector('.so-price').value) || 0;
                const disc = parseFloat(tr.querySelector('.so-disc').value) || 0;
                const tax = parseFloat(tr.querySelector('.so-tax').value) || 0;
                const total = (qty * price) - disc + tax;
                tr.querySelector('.line-total').textContent = fmtMoney(total);
                subtotal += qty * price;
            });
            const disc = parseFloat(document.getElementById('soDiscount').value) || 0;
            const tax = parseFloat(document.getElementById('soTax').value) || 0;
            const ship = parseFloat(document.getElementById('soShipping').value) || 0;
            document.getElementById('soSubtotal').textContent = fmtMoney(subtotal);
            document.getElementById('soGrandTotal').textContent = fmtMoney(subtotal - disc + tax + ship);
        }

        document.getElementById('addSoItem').addEventListener('click', () => addRow());
        ['soDiscount','soTax','soShipping'].forEach(id => document.getElementById(id).addEventListener('input', recalc));

        (window.SO_ITEMS || []).forEach(addRow);
        if (!(window.SO_ITEMS || []).length) addRow();

        async function save(confirm) {
            const form = document.getElementById('soForm');
            const payload = Object.fromEntries(new FormData(form).entries());
            payload.items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const pid = tr.querySelector('.so-product').value;
                const qty = tr.querySelector('.so-qty').value;
                if (!pid || !qty) return;
                payload.items.push({
                    product_id: pid,
                    quantity: qty,
                    unit_price: tr.querySelector('.so-price').value,
                    discount: tr.querySelector('.so-disc').value,
                    tax: tr.querySelector('.so-tax').value
                });
            });
            if (!payload.items.length) { toast('Add at least one line item.', 'warning'); return; }
            if (!payload.customer_id) { toast('Select a customer.', 'warning'); return; }

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/sales.php?action=save_so`, {method:'POST', body: payload});
            if (!r.success) { showLoader(false); toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger'); return; }

            if (confirm) {
                const r2 = await api(`${G.baseUrl}/api/sales.php?action=confirm_so`, {method:'POST', body:{id:r.data.id}});
                showLoader(false);
                if (r2.success) toast('Order saved and confirmed.', 'success');
                else toast(r2.message, 'warning');
            } else {
                showLoader(false);
                toast(r.message, 'success');
            }
            setTimeout(() => window.location.href = `${G.baseUrl}/sales/sales-order-view.php?id=${r.data.id}`, 500);
        }

        document.getElementById('soForm').addEventListener('submit', e => { e.preventDefault(); save(false); });
        document.getElementById('soSaveConfirm').addEventListener('click', () => save(true));
    }

    /* ==================================================================
       ORDER VIEW — confirm button
       ================================================================== */
    document.querySelectorAll('.confirm-so').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('Confirm this order? Stock availability will be checked.')) return;
        showLoader(true);
        const r = await api(`${G.baseUrl}/api/sales.php?action=confirm_so`, {method:'POST', body:{id:b.dataset.id}});
        showLoader(false);
        if (r.success) { toast(r.message, 'success'); setTimeout(() => location.reload(), 600); } else toast(r.message, 'danger');
    }));

    /* ==================================================================
       INVOICES LIST
       ================================================================== */
    if (document.getElementById('invBody')) initInvList();

    function initInvList() {
        const state = { page:1, q:'', status:'', date_from:'', date_to:'' };
        const body = document.getElementById('invBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'invoices_list', ...state});
            const r = await api(`${G.baseUrl}/api/sales.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="10" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('invCount').textContent = `${pg.total} invoice(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="10"><div class="gims-empty"><i class="bi bi-receipt"></i><p>No invoices yet.</p></div></td></tr>';
                document.getElementById('invPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(i => `
                <tr>
                    <td><a href="${G.baseUrl}/sales/invoice-view.php?id=${i.id}" class="fw-semibold text-decoration-none">${esc(i.invoice_no)}</a></td>
                    <td>
                        <div class="gims-cell-title">${esc(i.customer_name)}</div>
                        ${i.customer_company ? `<small class="text-muted">${esc(i.customer_company)}</small>` : ''}
                    </td>
                    <td>${esc(i.order_no || '—')}</td>
                    <td>${new Date(i.invoice_date).toLocaleDateString()}</td>
                    <td>${i.due_date ? new Date(i.due_date).toLocaleDateString() : '—'}</td>
                    <td class="text-end fw-semibold">${fmtMoney(i.total)}</td>
                    <td class="text-end text-success">${fmtMoney(i.paid_amount)}</td>
                    <td class="text-end ${i.balance > 0 ? 'text-danger' : ''}">${fmtMoney(i.balance)}</td>
                    <td>${i.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="${G.baseUrl}/sales/invoice-view.php?id=${i.id}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            ${i.balance > 0 ? `<button class="btn btn-sm btn-outline-success pay-btn"
                                data-id="${i.id}" data-inv="${esc(i.invoice_no)}" data-bal="${i.balance}"><i class="bi bi-cash-coin"></i></button>` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.pay-btn').forEach(b => b.addEventListener('click', () => {
                document.getElementById('payInvId').value = b.dataset.id;
                document.getElementById('payInvNo').value = b.dataset.inv;
                document.getElementById('payBalance').value = fmtMoney(b.dataset.bal);
                document.getElementById('payAmount').value = b.dataset.bal;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('payModal')).show();
            }));

            renderPager(pg, 'invPager', 'invPagerWrap', 'invPageInfo', n => { state.page = n; load(); });
        }

        let dT;
        document.getElementById('invSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        document.getElementById('invStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });
        document.getElementById('invFrom').addEventListener('change', e => { state.date_from = e.target.value; state.page=1; load(); });
        document.getElementById('invTo').addEventListener('change', e => { state.date_to = e.target.value; state.page=1; load(); });
        document.getElementById('invReset').addEventListener('click', () => {
            Object.assign(state, {q:'', status:'', date_from:'', date_to:'', page:1});
            ['invSearch','invStatus','invFrom','invTo'].forEach(id => document.getElementById(id).value = '');
            load();
        });

        document.getElementById('payForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(e.target).entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/sales.php?action=record_payment`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
                load();
            } else toast(r.message, 'danger');
        });

        load();
    }

    /* ==================================================================
       INVOICE CREATE
       ================================================================== */
    if (document.getElementById('invForm') && document.getElementById('invItems')) initInvCreate();

    function initInvCreate() {
        const itemsBody = document.getElementById('invItems');
        const products = window.INV_PRODUCTS || [];

        function addRow(data) {
            data = data || { product_id:'', quantity:1, unit_price:0, discount:0, tax:0 };
            const tr = document.createElement('tr');
            const opts = products.map(p =>
                `<option value="${p.id}" data-price="${p.price}" ${String(p.id)===String(data.product_id)?'selected':''}>${esc(p.name)} (${esc(p.sku)})</option>`
            ).join('');
            tr.innerHTML = `
                <td><select class="form-select form-select-sm inv-product" required><option value="">— Select —</option>${opts}</select></td>
                <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm inv-qty" required value="${data.quantity}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm inv-price" required value="${data.unit_price}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm inv-disc" value="${data.discount}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm inv-tax" value="${data.tax}"></td>
                <td class="text-end fw-semibold line-total">—</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);

            tr.querySelector('.inv-product').addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                if (opt && opt.dataset.price) tr.querySelector('.inv-price').value = opt.dataset.price;
                recalc();
            });
            tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));
            tr.querySelector('.rm-row').addEventListener('click', () => { tr.remove(); recalc(); });
            recalc();
        }

        function recalc() {
            let subtotal = 0;
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const qty = parseFloat(tr.querySelector('.inv-qty').value) || 0;
                const price = parseFloat(tr.querySelector('.inv-price').value) || 0;
                const disc = parseFloat(tr.querySelector('.inv-disc').value) || 0;
                const tax = parseFloat(tr.querySelector('.inv-tax').value) || 0;
                tr.querySelector('.line-total').textContent = fmtMoney((qty*price) - disc + tax);
                subtotal += qty * price;
            });
            const disc = parseFloat(document.getElementById('invDiscount').value) || 0;
            const tax = parseFloat(document.getElementById('invTax').value) || 0;
            document.getElementById('invSubtotal').textContent = fmtMoney(subtotal);
            document.getElementById('invGrandTotal').textContent = fmtMoney(subtotal - disc + tax);
        }

        document.getElementById('addInvItem').addEventListener('click', () => addRow());
        ['invDiscount','invTax'].forEach(id => document.getElementById(id).addEventListener('input', recalc));

        (window.INV_ITEMS || []).forEach(addRow);
        if (!(window.INV_ITEMS || []).length) addRow();

        document.getElementById('invForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const payload = Object.fromEntries(new FormData(form).entries());
            payload.items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const pid = tr.querySelector('.inv-product').value;
                const qty = tr.querySelector('.inv-qty').value;
                if (!pid || !qty) return;
                payload.items.push({
                    product_id: pid, quantity: qty,
                    unit_price: tr.querySelector('.inv-price').value,
                    discount: tr.querySelector('.inv-disc').value,
                    tax: tr.querySelector('.inv-tax').value
                });
            });
            payload.deduct_stock = form.querySelector('#deductStock')?.checked ? 1 : 0;
            if (!payload.items.length) { toast('Add at least one line item.', 'warning'); return; }

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/sales.php?action=save_invoice`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                setTimeout(() => window.location.href = `${G.baseUrl}/sales/invoice-view.php?id=${r.data.id}`, 500);
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });
    }

    /* ==================================================================
       DELIVERIES
       ================================================================== */
    if (document.getElementById('delBody')) initDeliveries();

    function initDeliveries() {
        const body = document.getElementById('delBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'deliveries_list', status: document.getElementById('delStatus').value});
            const r = await api(`${G.baseUrl}/api/sales.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            document.getElementById('delCount').textContent = `${items.length} delivery(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-truck"></i><p>No deliveries yet.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(d => `
                <tr>
                    <td><span class="gims-cell-title">${esc(d.delivery_no)}</span></td>
                    <td>${esc(d.order_no || '—')}</td>
                    <td>
                        <div class="gims-cell-title">${esc(d.customer_name)}</div>
                        ${d.company ? `<small class="text-muted">${esc(d.company)}</small>` : ''}
                    </td>
                    <td>${new Date(d.delivery_date).toLocaleDateString()}</td>
                    <td>${esc(d.driver || '—')}</td>
                    <td>${esc(d.vehicle_no || '—')}</td>
                    <td>${d.status_badge}</td>
                    <td class="text-end">
                        ${['pending','dispatched'].includes(d.status) ? `
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" data-del="${d.id}" data-status="dispatched">Mark Dispatched</a></li>
                                    <li><a class="dropdown-item" href="#" data-del="${d.id}" data-status="delivered">Mark Delivered</a></li>
                                    <li><a class="dropdown-item text-danger" href="#" data-del="${d.id}" data-status="failed">Mark Failed</a></li>
                                </ul>
                            </div>
                        ` : '—'}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('[data-del]').forEach(a => a.addEventListener('click', async (e) => {
                e.preventDefault();
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/sales.php?action=update_delivery_status`, {method:'POST', body:{id:a.dataset.del, status:a.dataset.status}});
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
        }

        document.getElementById('delStatus').addEventListener('change', load);
        load();

        window.openDelivery = function () {
            document.getElementById('delForm').reset();
            document.getElementById('delId').value = '';
            document.getElementById('delSo').value = '';
        };

        document.getElementById('delSo').addEventListener('change', function () {
            const opt = this.selectedOptions[0];
            if (opt && opt.dataset.cust) {
                document.getElementById('delCustomer').value = opt.dataset.cust;
                document.querySelector('#delForm select[name="warehouse_id"]').value = opt.dataset.wh;
            }
        });

        document.getElementById('delForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(e.target).entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/sales.php?action=save_delivery`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('delModal')).hide();
                load();
            } else toast(r.message, 'danger');
        });
    }

    /* ==================================================================
       SALES RETURNS
       ================================================================== */
    if (document.getElementById('sretBody')) initReturns();

    function initReturns() {
        const body = document.getElementById('sretBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'returns_list', status: document.getElementById('sretStatus').value});
            const r = await api(`${G.baseUrl}/api/sales.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            document.getElementById('sretCount').textContent = `${items.length} return(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-arrow-return-left"></i><p>No returns yet.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(rr => `
                <tr>
                    <td><span class="gims-cell-title">${esc(rr.return_no)}</span></td>
                    <td>${esc(rr.customer_name)}${rr.company ? `<br><small class="text-muted">${esc(rr.company)}</small>` : ''}</td>
                    <td>${esc(rr.order_no || '—')}</td>
                    <td>${new Date(rr.return_date).toLocaleDateString()}</td>
                    <td>${esc((rr.reason || '—').substring(0, 40))}</td>
                    <td class="text-end fw-semibold">${fmtMoney(rr.total)}</td>
                    <td>${rr.status_badge}</td>
                    <td class="text-end">
                        ${rr.status === 'pending' ? `<button class="btn btn-sm btn-outline-success complete-sret" data-id="${rr.id}"><i class="bi bi-check2"></i></button>` : ''}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.complete-sret').forEach(b => b.addEventListener('click', async () => {
                if (!confirm('Complete this return? Stock will be restored.')) return;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/sales.php?action=complete_return`, {method:'POST', body:{id:b.dataset.id}});
                showLoader(false);
                if (r.success) { toast(r.message, 'success'); load(); } else toast(r.message, 'danger');
            }));
        }

        document.getElementById('sretStatus').addEventListener('change', load);
        load();

        const itemsBody = document.getElementById('sretItems');
        window.openReturn = function () { itemsBody.innerHTML = ''; addSretRow(); };

        function addSretRow() {
            const products = window.SRET_PRODUCTS || [];
            const tr = document.createElement('tr');
            const opts = products.map(p =>
                `<option value="${p.id}" data-price="${p.price}">${esc(p.name)} (${esc(p.sku)})</option>`
            ).join('');
            tr.innerHTML = `
                <td><select class="form-select form-select-sm sret-product" required><option value="">— Select —</option>${opts}</select></td>
                <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm sret-qty" value="1" required></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm sret-price" value="0"></td>
                <td class="text-end fw-semibold line-total">—</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);
            tr.querySelector('.sret-product').addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                if (opt && opt.dataset.price) tr.querySelector('.sret-price').value = opt.dataset.price;
                recalc();
            });
            tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));
            tr.querySelector('.rm-row').addEventListener('click', () => { tr.remove(); recalc(); });
            recalc();
        }

        function recalc() {
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const qty = parseFloat(tr.querySelector('.sret-qty').value) || 0;
                const price = parseFloat(tr.querySelector('.sret-price').value) || 0;
                tr.querySelector('.line-total').textContent = fmtMoney(qty * price);
            });
        }

        document.getElementById('addSretItem').addEventListener('click', addSretRow);

        document.getElementById('sretForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(e.target).entries());
            payload.items = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const pid = tr.querySelector('.sret-product').value;
                const qty = tr.querySelector('.sret-qty').value;
                if (!pid || !qty) return;
                payload.items.push({ product_id: pid, quantity: qty, unit_price: tr.querySelector('.sret-price').value });
            });
            if (!payload.items.length) { toast('Add at least one item.', 'warning'); return; }

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/sales.php?action=save_return`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('retModal')).hide();
                load();
            } else toast(r.message, 'danger');
        });
    }

    /* ==================================================================
       Pager
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