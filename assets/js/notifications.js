/* =============================================================================
   GIMS — Notifications page client
   ============================================================================= */

(function () {
    'use strict';
    const G = window.GIMS || {};
    const api = (u, o) => window.GIMS_APP.api(u, o);
    const toast = (m, t) => window.GIMS_APP.toast(m, t || 'info');
    const showLoader = (s) => window.GIMS_APP.showLoader(s);

    document.querySelectorAll('.mark-read').forEach(btn => {
        btn.addEventListener('click', async () => {
            showLoader(true);
            const r = await api(`${G.baseUrl}/api/notifications.php?action=read`, {method:'POST', body:{id: btn.dataset.id}});
            showLoader(false);
            if (r.success) { toast('Marked as read', 'success'); setTimeout(() => location.reload(), 300); }
        });
    });

    document.getElementById('markAllRead')?.addEventListener('click', async () => {
        if (!confirm('Mark all notifications as read?')) return;
        showLoader(true);
        const r = await api(`${G.baseUrl}/api/notifications.php?action=read_all`, {method:'POST'});
        showLoader(false);
        if (r.success) { toast(r.message, 'success'); setTimeout(() => location.reload(), 300); }
    });

    // Auto-mark visible notifications as read
    document.querySelectorAll('.gims-notif-item-lg.unread').forEach(el => {
        el.addEventListener('click', async (e) => {
            if (e.target.closest('a') || e.target.closest('button')) return;
            const id = el.dataset.id;
            await api(`${G.baseUrl}/api/notifications.php?action=read`, {method:'POST', body:{id}});
        });
    });
})();