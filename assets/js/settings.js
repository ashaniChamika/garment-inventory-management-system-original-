/* =============================================================================
   GIMS — Settings client
   ============================================================================= */

(function () {
    'use strict';
    const G = window.GIMS || {};
    const api = (u, o) => window.GIMS_APP.api(u, o);
    const toast = (m, t) => window.GIMS_APP.toast(m, t || 'info');
    const showLoader = (s) => window.GIMS_APP.showLoader(s);

    if (!document.getElementById('settingsForm')) return;

    /* Section navigation */
    document.querySelectorAll('.gims-settings-nav a').forEach(a => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.gims-settings-nav a').forEach(x => x.classList.remove('active'));
            a.classList.add('active');
            const target = document.querySelector(a.getAttribute('href'));
            target?.scrollIntoView({behavior:'smooth', block:'start'});
        });
    });

    /* Switches → hidden inputs */
    document.getElementById('allowNeg')?.addEventListener('change', function () {
        document.getElementById('allowNegInput').value = this.checked ? '1' : '0';
    });
    document.getElementById('maintenance')?.addEventListener('change', function () {
        document.getElementById('maintenanceInput').value = this.checked ? '1' : '0';
    });

    /* Submit */
    document.getElementById('settingsForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);

        // ensure hidden checkbox values are also in form
        if (!fd.has('settings[allow_negative_stock]')) {
            fd.append('settings[allow_negative_stock]', '0');
        }
        if (!fd.has('settings[maintenance_mode]')) {
            fd.append('settings[maintenance_mode]', '0');
        }

        showLoader(true);
        const r = await api(`${G.baseUrl}/api/settings.php?action=save`, {method:'POST', body: fd});
        showLoader(false);
        if (r.success) {
            toast(r.message, 'success');
        } else {
            toast(r.message, 'danger');
        }
    });
})();