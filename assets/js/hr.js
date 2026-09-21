/* =============================================================================
   GIMS — HR client (employees, departments, attendance)
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

    /* ==================================================================
       EMPLOYEES
       ================================================================== */
    if (document.getElementById('empBody')) initEmployees();

    function initEmployees() {
        const state = { page:1, q:'', department_id:'', status:'' };
        const body = document.getElementById('empBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/employees.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('empCount').textContent = `${pg.total} employee(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="9"><div class="gims-empty"><i class="bi bi-people"></i><p>No employees yet.</p></div></td></tr>';
                document.getElementById('empPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(e => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="gims-avatar sm">${esc((e.full_name||'?').charAt(0).toUpperCase())}</span>
                            <div>
                                <div class="gims-cell-title">${esc(e.full_name)}</div>
                                <small class="text-muted">${esc(e.email || e.nic || '')}</small>
                            </div>
                        </div>
                    </td>
                    <td><code>${esc(e.employee_no)}</code></td>
                    <td>${esc(e.department_name || '—')}</td>
                    <td>${esc(e.position || '—')}</td>
                    <td>${esc(e.phone || '—')}</td>
                    <td>${e.join_date ? new Date(e.join_date).toLocaleDateString() : '—'}</td>
                    <td class="text-end">${fmtMoney(e.salary)}</td>
                    <td>${e.status_badge}</td>
                    <td class="text-end">
                        ${window.EMPLOYEE_CAN_MANAGE ? `
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-secondary edit-emp" data-id="${e.id}"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger del-emp" data-id="${e.id}" data-name="${esc(e.full_name)}"><i class="bi bi-trash"></i></button>
                            </div>
                        ` : '—'}
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.edit-emp').forEach(b => b.addEventListener('click', async () => {
                showLoader(true);
                const rr = await api(`${G.baseUrl}/api/employees.php?action=get&id=${b.dataset.id}`);
                showLoader(false);
                if (rr.success) openEmployee(rr.data);
            }));
            body.querySelectorAll('.del-emp').forEach(b => b.addEventListener('click', async () => {
                if (!confirm(`Delete employee "${b.dataset.name}"?`)) return;
                showLoader(true);
                const rr = await api(`${G.baseUrl}/api/employees.php?action=delete`, {method:'POST', body:{id:b.dataset.id}});
                showLoader(false);
                if (rr.success) { toast(rr.message, 'success'); load(); } else toast(rr.message, 'danger');
            }));

            renderPager(pg, 'empPager', 'empPagerWrap', 'empPageInfo', n => { state.page = n; load(); });
        }

        let dT;
        document.getElementById('empSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); state.page=1; load(); }, 320);
        });
        document.getElementById('empDept').addEventListener('change', e => { state.department_id = e.target.value; state.page=1; load(); });
        document.getElementById('empStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });
        document.getElementById('empReset').addEventListener('click', () => {
            Object.assign(state, {q:'', department_id:'', status:'', page:1});
            ['empSearch','empDept','empStatus'].forEach(id => document.getElementById(id).value = '');
            load();
        });

        window.openEmployee = function (e) {
            document.getElementById('empModalTitle').textContent = e ? 'Edit Employee' : 'Add Employee';
            document.getElementById('empId').value       = e ? e.id : '';
            document.getElementById('empNo').value       = e ? e.employee_no : 'EMP-' + Math.floor(1000 + Math.random() * 9000);
            document.getElementById('empName').value     = e ? e.full_name : '';
            document.getElementById('empNic').value      = e ? (e.nic||'') : '';
            document.getElementById('empGender').value   = e ? (e.gender||'') : '';
            document.getElementById('empDob').value      = e ? (e.dob||'') : '';
            document.getElementById('empPhone').value    = e ? (e.phone||'') : '';
            document.getElementById('empEmail').value    = e ? (e.email||'') : '';
            document.getElementById('empAddress').value  = e ? (e.address||'') : '';
            document.getElementById('empDeptInput').value= e ? (e.department_id||'') : '';
            document.getElementById('empPosition').value = e ? (e.position||'') : '';
            document.getElementById('empJoin').value     = e ? (e.join_date||'') : '';
            document.getElementById('empSalary').value   = e ? (e.salary||0) : 0;
            document.getElementById('empEmergency').value= e ? (e.emergency_contact||'') : '';
            document.getElementById('empStatusInput').value = e ? e.status : 'active';
        };

        document.getElementById('empForm').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const fd = new FormData(ev.target);
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/employees.php?action=save`, {method:'POST', body: fd});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('empModal')).hide();
                load();
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });

        load();
    }

    /* ==================================================================
       DEPARTMENTS
       ================================================================== */
    if (document.getElementById('deptBody')) initDepartments();

    function initDepartments() {
        const body = document.getElementById('deptBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const r = await api(`${G.baseUrl}/api/employees.php?action=departments_list`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            document.getElementById('deptCount').textContent = `${items.length} department(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="6"><div class="gims-empty"><i class="bi bi-diagram-3"></i><p>No departments.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(d => `
                <tr>
                    <td><div class="gims-cell-title">${esc(d.name)}</div></td>
                    <td><code>${esc(d.code)}</code></td>
                    <td>${esc(d.description || '—')}</td>
                    <td class="text-end">${d.emp_count}</td>
                    <td>${d.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <button class="btn btn-sm btn-outline-secondary edit-dept" data-json='${esc(JSON.stringify(d))}'><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger del-dept" data-id="${d.id}" data-name="${esc(d.name)}"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.edit-dept').forEach(b => b.addEventListener('click', () => openDepartment(JSON.parse(b.dataset.json))));
            body.querySelectorAll('.del-dept').forEach(b => b.addEventListener('click', async () => {
                if (!confirm(`Delete department "${b.dataset.name}"?`)) return;
                showLoader(true);
                const rr = await api(`${G.baseUrl}/api/employees.php?action=delete_department`, {method:'POST', body:{id:b.dataset.id}});
                showLoader(false);
                if (rr.success) { toast(rr.message, 'success'); load(); } else toast(rr.message, 'danger');
            }));
        }

        window.openDepartment = function (d) {
            document.getElementById('deptModalTitle').textContent = d ? 'Edit Department' : 'Add Department';
            document.getElementById('deptId').value      = d ? d.id : '';
            document.getElementById('deptName').value    = d ? d.name : '';
            document.getElementById('deptCode').value    = d ? d.code : '';
            document.getElementById('deptDesc').value    = d ? (d.description||'') : '';
            document.getElementById('deptStatusInput').value = d ? d.status : 'active';
        };

        document.getElementById('deptForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(e.target).entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/employees.php?action=save_department`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('deptModal')).hide();
                load();
            } else toast(r.message, 'danger');
        });

        load();
    }

    /* ==================================================================
       ATTENDANCE
       ================================================================== */
    if (document.getElementById('attBody')) initAttendance();

    function initAttendance() {
        const state = { page:1, date:'', department_id:'', status:'' };
        const body = document.getElementById('attBody');
        const dateEl = document.getElementById('attDate');

        async function load() {
            state.date = dateEl.value;
            body.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/attendance.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            const pg = r.data.pagination;
            document.getElementById('attCount').textContent = `${pg.total} employee(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="6"><div class="gims-empty"><i class="bi bi-calendar-check"></i><p>No employees found.</p></div></td></tr>';
                document.getElementById('attPagerWrap').style.display = 'none';
                return;
            }

            body.innerHTML = items.map(e => `
                <tr data-employee="${e.employee_id}">
                    <td>
                        <div class="gims-cell-title">${esc(e.full_name)}</div>
                        <small class="text-muted">${esc(e.employee_no)}</small>
                    </td>
                    <td>${esc(e.department_name || '—')}</td>
                    <td><input type="time" class="form-control form-control-sm att-in" value="${e.check_in ? e.check_in.substring(0,5) : ''}" style="min-width:110px"></td>
                    <td><input type="time" class="form-control form-control-sm att-out" value="${e.check_out ? e.check_out.substring(0,5) : ''}" style="min-width:110px"></td>
                    <td>
                        <select class="form-select form-select-sm att-status" style="min-width:120px">
                            <option value="present" ${e.status==='present'?'selected':''}>Present</option>
                            <option value="absent" ${e.status==='absent'?'selected':''}>Absent</option>
                            <option value="late" ${e.status==='late'?'selected':''}>Late</option>
                            <option value="half_day" ${e.status==='half_day'?'selected':''}>Half Day</option>
                            <option value="leave" ${e.status==='leave'?'selected':''}>Leave</option>
                        </select>
                    </td>
                    <td><input type="text" class="form-control form-control-sm att-remarks" value="${esc(e.remarks || '')}" placeholder="Optional"></td>
                </tr>
            `).join('');

            renderPager(pg, 'attPager', 'attPagerWrap', 'attPageInfo', n => { state.page = n; load(); });
        }

        async function loadSummary() {
            const r = await api(`${G.baseUrl}/api/attendance.php?action=summary&date=${dateEl.value}`);
            if (r.success) {
                document.getElementById('kpiPresent').textContent = r.data.present || 0;
                document.getElementById('kpiAbsent').textContent = r.data.absent || 0;
                document.getElementById('kpiLate').textContent = r.data.late || 0;
                document.getElementById('kpiLeave').textContent = r.data.leave || 0;
                document.getElementById('kpiUnmarked').textContent = r.data.not_marked || 0;
                document.getElementById('kpiMarked').textContent =
                    (r.data.present||0) + (r.data.absent||0) + (r.data.late||0) + (r.data.half_day||0) + (r.data.leave||0);
            }
        }

        function collect() {
            const items = [];
            body.querySelectorAll('tr[data-employee]').forEach(tr => {
                items.push({
                    employee_id: tr.dataset.employee,
                    check_in: tr.querySelector('.att-in').value || null,
                    check_out: tr.querySelector('.att-out').value || null,
                    status: tr.querySelector('.att-status').value,
                    remarks: tr.querySelector('.att-remarks').value,
                });
            });
            return items;
        }

        document.getElementById('saveAllBtn').addEventListener('click', async () => {
            const items = collect();
            if (!items.length) { toast('Nothing to save.', 'warning'); return; }
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/attendance.php?action=bulk_mark`, {
                method:'POST',
                body: { att_date: dateEl.value, items }
            });
            showLoader(false);
            if (r.success) { toast(r.message, 'success'); load(); loadSummary(); } else toast(r.message, 'danger');
        });

        dateEl.addEventListener('change', () => { state.page = 1; load(); loadSummary(); });
        document.getElementById('attDept').addEventListener('change', e => { state.department_id = e.target.value; state.page=1; load(); });
        document.getElementById('attStatus').addEventListener('change', e => { state.status = e.target.value; state.page=1; load(); });
        document.getElementById('attReset').addEventListener('click', () => {
            Object.assign(state, {q:'', department_id:'', status:'', page:1});
            document.getElementById('attDept').value = '';
            document.getElementById('attStatus').value = '';
            load();
        });

        load();
        loadSummary();
    }

    /* ==================================================================
       ATTENDANCE REPORTS
       ================================================================== */
    if (document.getElementById('repBody')) initRepReports();

    function initRepReports() {
        const body = document.getElementById('repBody');

        document.getElementById('runReport').addEventListener('click', async () => {
            const from = document.getElementById('repFrom').value;
            const to = document.getElementById('repTo').value;
            const deptId = document.getElementById('repDept').value;

            showLoader(true);
            const qs = new URLSearchParams({
                action:'report', date_from: from, date_to: to, department_id: deptId
            });
            const r = await api(`${G.baseUrl}/api/attendance.php?${qs}`);
            showLoader(false);
            if (!r.success) { toast(r.message, 'danger'); return; }

            const items = r.data.items || [];
            document.getElementById('repCount').textContent = `${items.length} employee(s) · ${from} to ${to}`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="gims-empty"><i class="bi bi-bar-chart"></i><p>No data for this range.</p></div></td></tr>';
                return;
            }

            body.innerHTML = items.map(e => {
                const total = (parseFloat(e.present_days)||0) + (parseFloat(e.late_days)||0) +
                              (parseFloat(e.half_days)||0) + (parseFloat(e.absent_days)||0) + (parseFloat(e.leave_days)||0);
                return `
                    <tr>
                        <td>
                            <div class="gims-cell-title">${esc(e.full_name)}</div>
                            <small class="text-muted">${esc(e.employee_no)}</small>
                        </td>
                        <td>${esc(e.department_name || '—')}</td>
                        <td class="text-end text-success fw-semibold">${e.present_days}</td>
                        <td class="text-end text-warning">${e.late_days}</td>
                        <td class="text-end">${e.half_days}</td>
                        <td class="text-end text-danger">${e.absent_days}</td>
                        <td class="text-end text-info">${e.leave_days}</td>
                        <td class="text-end fw-bold">${total}</td>
                    </tr>
                `;
            }).join('');
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