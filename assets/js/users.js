/* =============================================================================
   GIMS — Users & Roles client
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

    /* ---------------- USERS ---------------- */
    if (document.getElementById('userBody')) initUsers();

    function initUsers() {
        const state = { q:'', role_id:'' };
        const body = document.getElementById('userBody');

        async function load() {
            body.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';
            const qs = new URLSearchParams({action:'list', ...state});
            const r = await api(`${G.baseUrl}/api/users.php?${qs}`);
            if (!r.success) { body.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-5">${esc(r.message)}</td></tr>`; return; }
            const items = r.data.items || [];
            document.getElementById('userCount').textContent = `${items.length} user(s)`;

            if (!items.length) {
                body.innerHTML = '<tr><td colspan="6"><div class="gims-empty"><i class="bi bi-people"></i><p>No users.</p></div></td></tr>';
                return;
            }
            body.innerHTML = items.map(u => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="gims-avatar sm">${esc((u.name||'?').charAt(0).toUpperCase())}</span>
                            <div>
                                <div class="gims-cell-title">${esc(u.name)}</div>
                                <small class="text-muted">${esc(u.email)}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-soft-primary">${esc(u.role_name)}</span></td>
                    <td>${esc(u.phone || '—')}</td>
                    <td class="small text-muted">${u.last_login ? new Date(u.last_login).toLocaleString() : 'Never'}</td>
                    <td>${u.status_badge}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <button class="btn btn-sm btn-outline-secondary edit-user" data-id="${u.id}"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger del-user" data-id="${u.id}" data-name="${esc(u.name)}"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');

            body.querySelectorAll('.edit-user').forEach(b => b.addEventListener('click', async () => {
                showLoader(true);
                const rr = await api(`${G.baseUrl}/api/users.php?action=get&id=${b.dataset.id}`);
                showLoader(false);
                if (rr.success) openUser(rr.data);
            }));
            body.querySelectorAll('.del-user').forEach(b => b.addEventListener('click', async () => {
                if (!confirm(`Delete user "${b.dataset.name}"?`)) return;
                showLoader(true);
                const rr = await api(`${G.baseUrl}/api/users.php?action=delete`, {method:'POST', body:{id:b.dataset.id}});
                showLoader(false);
                if (rr.success) { toast(rr.message, 'success'); load(); } else toast(rr.message, 'danger');
            }));
        }

        let dT;
        document.getElementById('userSearch').addEventListener('input', e => {
            clearTimeout(dT); dT = setTimeout(() => { state.q = e.target.value.trim(); load(); }, 320);
        });
        document.getElementById('userRole').addEventListener('change', e => { state.role_id = e.target.value; load(); });

        window.openUser = function (u) {
            document.getElementById('userModalTitle').textContent = u ? 'Edit User' : 'Add User';
            document.getElementById('userId').value     = u ? u.id : '';
            document.getElementById('userName').value   = u ? u.name : '';
            document.getElementById('userEmail').value  = u ? u.email : '';
            document.getElementById('userPhone').value  = u ? (u.phone||'') : '';
            document.getElementById('userRoleInput').value = u ? u.role_id : '';
            document.getElementById('userStatusInput').value = u ? u.status : 'active';
            document.getElementById('userPassword').value = '';
            document.getElementById('userPasswordC').value = '';
            document.getElementById('pwdReq').textContent = u ? '' : '*';
            document.getElementById('pwdHint').textContent = u ? 'Leave blank to keep current password.' : 'Minimum 8 characters.';
        };

        document.getElementById('userForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(e.target).entries());
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/users.php?action=save`, {method:'POST', body: payload});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                load();
            } else toast(r.message + (r.errors ? ': ' + r.errors.join(', ') : ''), 'danger');
        });

        load();
    }

    /* ---------------- ROLES + PERMISSIONS ---------------- */
    if (document.getElementById('rolesList')) initRoles();

    function initRoles() {
        const cont = document.getElementById('rolesList');
        let allPerms = {};
        let allPermGroups = {};

        async function load() {
            const [rRoles, rPerms] = await Promise.all([
                api(`${G.baseUrl}/api/users.php?action=roles_list`),
                api(`${G.baseUrl}/api/users.php?action=permissions_list`)
            ]);

            if (!rRoles.success) { cont.innerHTML = '<div class="gims-empty"><p>Failed to load roles.</p></div>'; return; }
            allPermGroups = rPerms.data.grouped || {};

            cont.innerHTML = rRoles.data.items.map(r => `
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <div>
                        <div class="gims-cell-title">${esc(r.name)}</div>
                        <small class="text-muted">${r.user_count} user(s) · ${r.perm_count} permission(s)</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary edit-perm" data-id="${r.id}" data-name="${esc(r.name)}" data-slug="${esc(r.slug)}">
                        <i class="bi bi-shield-lock"></i>
                    </button>
                </div>
            `).join('');

            cont.querySelectorAll('.edit-perm').forEach(b => b.addEventListener('click', () => openPermModal(b.dataset.id, b.dataset.name, b.dataset.slug)));
        }

        async function openPermModal(roleId, roleName, roleSlug) {
            document.getElementById('permRoleId').value = roleId;
            document.getElementById('permRoleName').textContent = roleName;

            if (roleSlug === 'super_admin') {
                document.getElementById('permGroups').innerHTML = '<div class="alert alert-info">Super Admin has all permissions by default and cannot be modified.</div>';
                document.getElementById('savePerms').disabled = true;
            } else {
                document.getElementById('savePerms').disabled = false;
                showLoader(true);
                const r = await api(`${G.baseUrl}/api/users.php?action=role_permissions&role_id=${roleId}`);
                showLoader(false);
                const current = new Set((r.data.permission_ids || []).map(Number));

                const html = Object.entries(allPermGroups).map(([module, perms]) => `
                    <div class="mb-3">
                        <h6 class="text-uppercase small text-muted mb-2">${esc(module)}</h6>
                        <div class="row g-2">
                            ${perms.map(p => `
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input perm-check" type="checkbox" value="${p.id}" id="perm${p.id}" ${current.has(Number(p.id))?'checked':''}>
                                        <label class="form-check-label small" for="perm${p.id}">${esc(p.name)}</label>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `).join('');
                document.getElementById('permGroups').innerHTML = html;
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('permModal')).show();
        }

        document.getElementById('savePerms').addEventListener('click', async () => {
            const roleId = document.getElementById('permRoleId').value;
            const perms = [];
            document.querySelectorAll('.perm-check:checked').forEach(c => perms.push(c.value));

            showLoader(true);
            const r = await api(`${G.baseUrl}/api/users.php?action=save_role_permissions`, {method:'POST', body:{role_id:roleId, permissions:perms}});
            showLoader(false);
            if (r.success) {
                toast(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('permModal')).hide();
                load();
            } else toast(r.message, 'danger');
        });

        load();
    }
})();