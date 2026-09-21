/* =============================================================================
   GIMS — products & categories client
   ============================================================================= */

(function () {
    'use strict';

    const G = window.GIMS || {};
    const api = (url, opts) => window.GIMS_APP.api(url, opts);
    const toast = (msg, type) => window.GIMS_APP.toast(msg, type || 'info');
    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');

    /* ==================================================================
       PRODUCTS INDEX
       ================================================================== */
    const pBody = document.getElementById('pBody');
    if (pBody) initProductsIndex();

    function initProductsIndex() {
        const state = { page: 1, q: '', category_id: '', type: '', status: '', stock: '' };
        const qInput  = document.getElementById('pSearch');
        const catSel  = document.getElementById('pCategory');
        const typeSel = document.getElementById('pType');
        const stockSel= document.getElementById('pStock');
        const statSel = document.getElementById('pStatus');
        const resetBtn= document.getElementById('pReset');
        const pager   = document.getElementById('pPager');
        const pagerWrap = document.getElementById('pPagerWrap');
        const pageInfo  = document.getElementById('pPageInfo');
        const countEl   = document.getElementById('pCount');

        function readFilters() {
            state.q           = qInput.value.trim();
            state.category_id = catSel.value;
            state.type        = typeSel.value;
            state.stock       = stockSel.value;
            state.status      = statSel.value;
        }

        async function load() {
            readFilters();
            pBody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';

            const qs = new URLSearchParams({
                action: 'list',
                q: state.q,
                category_id: state.category_id,
                type: state.type,
                status: state.status,
                stock: state.stock,
                page: state.page
            });

            const res = await api(`${G.baseUrl}/api/products.php?${qs.toString()}`);
            if (!res.success) {
                pBody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-danger">${esc(res.message)}</td></tr>`;
                return;
            }

            const items = res.data.items || [];
            const pg = res.data.pagination;

            countEl.textContent = `${pg.total} product(s) found`;
            renderTable(items);
            renderPager(pg);
        }

        function renderTable(items) {
            if (!items.length) {
                pBody.innerHTML = `<tr><td colspan="9">
                    <div class="gims-empty"><i class="bi bi-inbox"></i><p>No products match your filters.</p></div>
                </td></tr>`;
                pagerWrap.style.display = 'none';
                return;
            }
            pBody.innerHTML = items.map(p => {
                const img = p.image
                    ? `<img src="${esc(G.assetsUrl)}/uploads/${esc(p.image)}" style="width:42px;height:42px;object-fit:cover;border-radius:8px">`
                    : `<div style="width:42px;height:42px;border-radius:8px;background:#eff6ff;color:#2563eb;display:grid;place-items:center"><i class="bi bi-box"></i></div>`;

                const actions = `
                    <a href="${esc(G.baseUrl)}/products/view.php?id=${p.id}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                    ${window.PRODUCTS_CAN_EDIT ? `<a href="${esc(G.baseUrl)}/products/edit.php?id=${p.id}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>` : ''}
                    ${window.PRODUCTS_CAN_DELETE ? `<button class="btn btn-sm btn-outline-danger delete-product" data-id="${p.id}" data-name="${esc(p.name)}" title="Delete"><i class="bi bi-trash"></i></button>` : ''}
                `;

                return `<tr>
                    <td>${img}</td>
                    <td>
                        <div class="gims-cell-title">${esc(p.name)}</div>
                        <small class="text-muted">${esc(p.sku)} · ${esc(p.product_code)}</small>
                        ${p.has_variants == 1 ? ' <span class="badge badge-soft-info ms-1" style="font-size:9.5px">VAR</span>' : ''}
                    </td>
                    <td>${esc(p.category_name || '—')}</td>
                    <td><span class="badge badge-soft-secondary">${esc(humanType(p.product_type))}</span></td>
                    <td class="text-end">${fmtMoney(p.cost_price)}</td>
                    <td class="text-end">${fmtMoney(p.selling_price)}</td>
                    <td class="text-end">
                        <div class="fw-semibold">${fmtNum(p.stock_qty)} <small class="text-muted fw-normal">${esc(p.unit)}</small></div>
                        <div>${p.stock_badge}</div>
                    </td>
                    <td>${p.status_label}</td>
                    <td class="text-end"><div class="d-flex gap-1 justify-content-end">${actions}</div></td>
                </tr>`;
            }).join('');

            pBody.querySelectorAll('.delete-product').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    const name = btn.dataset.name;
                    if (!confirm(`Delete product "${name}"? This can be restored by an administrator.`)) return;
                    window.GIMS_APP.showLoader(true);
                    const r = await api(`${G.baseUrl}/api/products.php?action=delete`, { method: 'POST', body: { id } });
                    window.GIMS_APP.showLoader(false);
                    if (r.success) { toast(r.message, 'success'); load(); }
                    else toast(r.message || 'Delete failed.', 'danger');
                });
            });
        }

        function renderPager(pg) {
            if (pg.pages <= 1) { pagerWrap.style.display = 'none'; return; }
            pagerWrap.style.display = 'flex';
            pageInfo.textContent = `Showing ${pg.from}–${pg.to} of ${pg.total}`;

            const html = [];
            html.push(`<li class="page-item ${pg.page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pg.page - 1}">&laquo;</a></li>`);

            const start = Math.max(1, pg.page - 2);
            const end   = Math.min(pg.pages, pg.page + 2);
            if (start > 1) html.push(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
            if (start > 2) html.push(`<li class="page-item disabled"><span class="page-link">…</span></li>`);
            for (let i = start; i <= end; i++) {
                html.push(`<li class="page-item ${i === pg.page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
            }
            if (end < pg.pages - 1) html.push(`<li class="page-item disabled"><span class="page-link">…</span></li>`);
            if (end < pg.pages) html.push(`<li class="page-item"><a class="page-link" href="#" data-page="${pg.pages}">${pg.pages}</a></li>`);

            html.push(`<li class="page-item ${pg.page === pg.pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pg.page + 1}">&raquo;</a></li>`);

            pager.innerHTML = html.join('');
            pager.querySelectorAll('a[data-page]').forEach(a => {
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    const n = parseInt(a.dataset.page, 10);
                    if (n >= 1 && n <= pg.pages && n !== pg.page) { state.page = n; load(); }
                });
            });
        }

        /* debounce search */
        let debTimer;
        qInput.addEventListener('input', () => {
            clearTimeout(debTimer);
            debTimer = setTimeout(() => { state.page = 1; load(); }, 350);
        });

        [catSel, typeSel, stockSel, statSel].forEach(el => {
            el.addEventListener('change', () => { state.page = 1; load(); });
        });

        resetBtn.addEventListener('click', () => {
            qInput.value = ''; catSel.value = ''; typeSel.value = '';
            stockSel.value = ''; statSel.value = '';
            state.page = 1; load();
        });

        load();
    }

    /* ==================================================================
       CATEGORIES INDEX
       ================================================================== */
    const catBody = document.getElementById('catBody');
    if (catBody) initCategoriesIndex();

    function initCategoriesIndex() {
        const searchEl = document.getElementById('catSearch');
        const parentEl = document.getElementById('catFilterParent');
        const countEl  = document.getElementById('catCount');
        const modal    = document.getElementById('catModal');
        const form     = document.getElementById('catForm');

        let all = [];

        async function load() {
            catBody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({
                action: 'list',
                q: searchEl.value.trim(),
                parent: parentEl.value
            });
            const res = await api(`${G.baseUrl}/api/categories.php?${qs.toString()}`);
            if (!res.success) {
                catBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-5">${esc(res.message)}</td></tr>`;
                return;
            }
            all = res.data.items || [];
            countEl.textContent = `${all.length} categor${all.length === 1 ? 'y' : 'ies'}`;
            renderList(all);
            fillParentSelect(all);
        }

        function renderList(items) {
            if (!items.length) {
                catBody.innerHTML = '<tr><td colspan="7"><div class="gims-empty"><i class="bi bi-diagram-3"></i><p>No categories yet.</p></div></td></tr>';
                return;
            }
            catBody.innerHTML = items.map(c => {
                const depth = c.parent_id ? 'padding-left:26px;color:#94a3b8' : '';
                const indent = c.parent_id ? '<i class="bi bi-arrow-return-right me-1"></i>' : '';
                const actions = `
                    ${window.CATEGORIES_CAN_EDIT ? `<button class="btn btn-sm btn-outline-secondary edit-cat" data-json='${esc(JSON.stringify(c))}'><i class="bi bi-pencil"></i></button>` : ''}
                    ${window.CATEGORIES_CAN_DELETE ? `<button class="btn btn-sm btn-outline-danger delete-cat" data-id="${c.id}" data-name="${esc(c.name)}"><i class="bi bi-trash"></i></button>` : ''}
                `;
                return `<tr>
                    <td style="${depth}">
                        <div class="gims-cell-title">${indent}${esc(c.name)}</div>
                        ${c.description ? `<small class="text-muted">${esc(c.description.substring(0,60))}${c.description.length > 60 ? '…' : ''}</small>` : ''}
                    </td>
                    <td><code class="small">${esc(c.slug)}</code></td>
                    <td>${esc(c.parent_name || '—')}</td>
                    <td class="text-end">${c.product_count}</td>
                    <td class="text-end">${c.child_count}</td>
                    <td>${c.status_badge}</td>
                    <td class="text-end"><div class="d-flex gap-1 justify-content-end">${actions}</div></td>
                </tr>`;
            }).join('');

            catBody.querySelectorAll('.edit-cat').forEach(btn => {
                btn.addEventListener('click', () => {
                    const c = JSON.parse(btn.dataset.json);
                    openModal(c);
                });
            });

            catBody.querySelectorAll('.delete-cat').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    const name = btn.dataset.name;
                    if (!confirm(`Delete category "${name}"?`)) return;
                    window.GIMS_APP.showLoader(true);
                    const r = await api(`${G.baseUrl}/api/categories.php?action=delete`, { method: 'POST', body: { id } });
                    window.GIMS_APP.showLoader(false);
                    if (r.success) { toast(r.message, 'success'); load(); }
                    else toast(r.message || 'Delete failed.', 'danger');
                });
            });
        }

        function fillParentSelect(items) {
            const sel = document.getElementById('catParent');
            const currentVal = sel.value;
            sel.innerHTML = '<option value="">— None (top level) —</option>' +
                items.filter(c => !c.parent_id).map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
            if (currentVal) sel.value = currentVal;
        }

        function openModal(c) {
            document.getElementById('catModalTitle').textContent = c ? 'Edit Category' : 'Add Category';
            document.getElementById('catId').value = c ? c.id : '';
            document.getElementById('catName').value = c ? c.name : '';
            document.getElementById('catDescription').value = c ? (c.description || '') : '';
            document.getElementById('catStatus').value = c ? c.status : 'active';
            document.getElementById('catParent').value = c && c.parent_id ? c.parent_id : '';

            const m = bootstrap.Modal.getOrCreateInstance(modal);
            m.show();
        }

        window.resetCatForm = function () {
            form.reset();
            document.getElementById('catId').value = '';
            document.getElementById('catModalTitle').textContent = 'Add Category';
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const payload = Object.fromEntries(fd.entries());
            window.GIMS_APP.showLoader(true);
            const r = await api(`${G.baseUrl}/api/categories.php?action=save`, { method: 'POST', body: payload });
            window.GIMS_APP.showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(modal)?.hide();
                load();
            } else {
                toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
            }
        });

        let debTimer;
        searchEl.addEventListener('input', () => { clearTimeout(debTimer); debTimer = setTimeout(load, 300); });
        parentEl.addEventListener('change', load);

        load();
    }

    /* ==================================================================
       Helpers
       ================================================================== */
    function fmtMoney(v) {
        const n = Number(v || 0);
        return G.currency + ' ' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fmtNum(v) {
        const n = Number(v || 0);
        return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }
    function humanType(t) {
        return String(t || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }
})();