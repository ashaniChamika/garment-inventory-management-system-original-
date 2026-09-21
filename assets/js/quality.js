/* =============================================================================
   GIMS — Quality Control client
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

    const fmtNum = (v) => Number(v || 0).toLocaleString(undefined, {maximumFractionDigits:2});

    /* ==================================================================
       QC LIST
       ================================================================== */
    if (document.getElementById('qcBody')) initQcList();

    function initQcList() {
        const state = { page:1, q:'', status:'', date_from:'', date_to:'' };
        const body = document.getElementById('qcBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/quality.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('qcCount').textContent = `${pg.total} inspection(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-patch-check"></i><p>No inspections yet.</p></div></td></tr>';
                document.getElementById('qcPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(q => `
                <tr>
                    <td><a href="${G.baseUrl}/quality-control/inspection-create.php?id=${q.id}" class="fw-semibold text-decoration-none">${esc(q.inspection_no)}</a></td>
                    <td>
                        <div class="gims-cell-title">${esc(q.product_name)}</div>
                        <small class="text-muted">${esc(q.sku)}</small>
                    </td>
                    <td><small>${esc(q.batch_no || '—')}</small></td>
                    <td class="text-end">${fmtNum(q.inspected_qty)}</td>
                    <td class="text-end text-success">${fmtNum(q.passed_qty)}</td>
                    <td class="text-end text-danger">${fmtNum(q.failed_qty)}</td>
                    <td class="text-end fw-semibold">${q.pass_rate}%</td>
                    <td>${q.status_badge}</td>
                    <td class="text-end">
                        <a href="${G.baseUrl}/quality-control/inspection-create.php?id=${q.id}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            `).join('');

            renderPager(pg, 'qcPager', 'qcPagerWrap', 'qcPageInfo', n => { state.page = n; load(); });
        }

        let dT;
        document.getElementById('qcSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        document.getElementById('qcStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });
        document.getElementById('qcFrom').addEventListener('change', e => { state.date_from = e.target.value; state.page=1; load(); });
        document.getElementById('qcTo').addEventListener('change', e => { state.date_to = e.target.value; state.page=1; load(); });
        document.getElementById('qcReset').addEventListener('click', () => {
            Object.assign(state, {q:'', status:'', date_from:'', date_to:'', page:1});
            ['qcSearch','qcStatus','qcFrom','qcTo'].forEach(id => document.getElementById(id).value = '');
            load();
        });

        load();
    }

    /* ==================================================================
       QC CREATE FORM
       ================================================================== */
    if (document.getElementById('qcForm')) initQcForm();

    function initQcForm() {
        const itemsBody = document.getElementById('defectItems');
        const productSel = document.getElementById('qcProduct');
        const inspectedIn = document.getElementById('qcInspected');
        const passedIn    = document.getElementById('qcPassed');
        const failedIn    = document.getElementById('qcFailed');

        /* auto-calc failed qty */
        function recalcFailed() {
            const ins = parseFloat(inspectedIn.value) || 0;
            const pass = parseFloat(passedIn.value) || 0;
            if (document.activeElement !== failedIn) {
                failedIn.value = Math.max(0, (ins - pass).toFixed(2));
            }
        }
        inspectedIn.addEventListener('input', recalcFailed);
        passedIn.addEventListener('input', recalcFailed);

        /* PO selection → product */
        document.getElementById('qcPo')?.addEventListener('change', function () {
            const pid = this.selectedOptions[0]?.dataset.product;
            if (pid) productSel.value = pid;
        });

        /* Defect rows */
        function addDefect(data) {
            data = data || { defect_type:'stitching', quantity:1, severity:'minor', description:'' };
            const tr = document.createElement('tr');
            const opts = [
                ['stitching','Stitching Defect'],['fabric','Fabric Defect'],['color','Color Defect'],
                ['size','Size Defect'],['print','Print Defect'],['button','Button Defect'],
                ['zipper','Zipper Defect'],['packaging','Packaging Defect'],['other','Other']
            ].map(([v,l]) => `<option value="${v}" ${data.defect_type===v?'selected':''}>${l}</option>`).join('');

            tr.innerHTML = `
                <td><select class="form-select form-select-sm def-type">${opts}</select></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm def-qty" value="${data.quantity}"></td>
                <td><select class="form-select form-select-sm def-sev">
                    <option value="minor" ${data.severity==='minor'?'selected':''}>Minor</option>
                    <option value="major" ${data.severity==='major'?'selected':''}>Major</option>
                    <option value="critical" ${data.severity==='critical'?'selected':''}>Critical</option>
                </select></td>
                <td><input type="text" class="form-control form-control-sm def-desc" value="${esc(data.description||'')}" maxlength="300"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger rm-row"><i class="bi bi-trash"></i></button></td>
            `;
            itemsBody.appendChild(tr);
            tr.querySelector('.rm-row').addEventListener('click', () => tr.remove());
        }

        document.getElementById('addDefect').addEventListener('click', () => addDefect());
        (window.QC_DEFECTS || []).forEach(addDefect);

        /* Submit */
        document.getElementById('qcForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            const payload = Object.fromEntries(fd.entries());
            payload.defects = [];
            itemsBody.querySelectorAll('tr').forEach(tr => {
                const qty = parseFloat(tr.querySelector('.def-qty').value) || 0;
                if (qty <= 0) return;
                payload.defects.push({
                    defect_type: tr.querySelector('.def-type').value,
                    quantity: qty,
                    severity: tr.querySelector('.def-sev').value,
                    description: tr.querySelector('.def-desc').value
                });
            });
            payload.apply_stock = form.querySelector('#applyStock')?.checked ? 1 : 0;

            if (!payload.product_id) { toast('Select a product.', 'warning'); return; }
            if (!payload.inspected_qty || parseFloat(payload.inspected_qty) <= 0) { toast('Enter inspected quantity.', 'warning'); return; }

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/quality.php?action=save`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                setTimeout(() => window.location.href = `${G.baseUrl}/quality-control/inspections.php`, 600);
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });
    }

    /* ==================================================================
       DEFECTS LIST
       ================================================================== */
    if (document.getElementById('defBody')) initDefects();

    function initDefects() {
        const body = document.getElementById('defBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'defects_list', severity: document.getElementById('defSeverity').value});
            const r = await api(`${G.baseUrl}/api/quality.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }

            const items = r.data.items || [];
            document.getElementById('defCount').textContent = `${items.length} defect(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="7"><div class="gims-empty"><i class="bi bi-bug"></i><p>No defects recorded.</p></div></td></tr>';
                return;
            }

            const sevCls = { minor:'badge-soft-info', major:'badge-soft-warning', critical:'badge-soft-danger' };
            body.innerHTML = items.map(d => `
                <tr>
                    <td><span class="fw-semibold">${esc(d.inspection_no)}</span></td>
                    <td>${new Date(d.inspection_date).toLocaleDateString()}</td>
                    <td>
                        <div class="gims-cell-title">${esc(d.product_name)}</div>
                        <small class="text-muted">${esc(d.sku)}</small>
                    </td>
                    <td><span class="badge badge-soft-secondary">${esc(d.defect_type.replace(/_/g,' ')).replace(/\b\w/g,c=>c.toUpperCase())}</span></td>
                    <td><span class="badge ${sevCls[d.severity] || 'badge-soft-secondary'}">${esc(d.severity)}</span></td>
                    <td class="text-end fw-semibold">${fmtNum(d.quantity)}</td>
                    <td>${esc(d.description || '—')}</td>
                </tr>
            `).join('');
        }

        document.getElementById('defSeverity').addEventListener('change', load);
        load();
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