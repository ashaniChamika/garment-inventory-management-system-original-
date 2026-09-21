/* =============================================================================
   GIMS — global application script
   ============================================================================= */

(function () {
    'use strict';

    const G = window.GIMS || {};

    /* ------------------------------------------------------------------ */
    /*  CSRF header helper                                                */
    /* ------------------------------------------------------------------ */
    function csrfHeaders(extra) {
        return Object.assign({
            'X-CSRF-Token': G.csrfToken || '',
            'X-Requested-With': 'XMLHttpRequest'
        }, extra || {});
    }

    /* ------------------------------------------------------------------ */
    /*  Toast                                                             */
    /* ------------------------------------------------------------------ */
    function toast(message, type = 'info', timeout = 4000) {
        const container = document.getElementById('toastContainer');
        if (!container) { alert(message); return; }

        const iconMap = {
            info:    'info-circle',
            success: 'check-circle',
            warning: 'exclamation-triangle',
            danger:  'exclamation-octagon',
            error:   'exclamation-octagon'
        };
        const colorMap = {
            info:    'primary',
            success: 'success',
            warning: 'warning',
            danger:  'danger',
            error:   'danger'
        };
        const typeKey = type === 'error' ? 'error' : type;

        const el = document.createElement('div');
        el.className = `toast align-items-center text-bg-${colorMap[typeKey] || 'primary'} border-0`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${iconMap[typeKey] || 'info-circle'} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        container.appendChild(el);

        try {
            const t = new bootstrap.Toast(el, { delay: timeout });
            t.show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        } catch (e) {
            container.appendChild(el);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Loading overlay                                                   */
    /* ------------------------------------------------------------------ */
    function showLoader(show) {
        const l = document.getElementById('gimsLoader');
        if (!l) return;
        l.classList.toggle('show', !!show);
    }

    /* ------------------------------------------------------------------ */
    /*  API helper                                                        */
    /* ------------------------------------------------------------------ */
    async function api(url, options) {
        options = options || {};
        const headers = csrfHeaders(options.headers);

        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = typeof options.body === 'string'
                ? options.body
                : JSON.stringify(options.body);
        }

        try {
            const res = await fetch(url, Object.assign({}, options, { headers }));
            const text = await res.text();

            let json;
            try { json = JSON.parse(text); }
            catch (e) {
                console.error('[GIMS API] Non-JSON response:', text);
                throw new Error('Server returned an invalid response.');
            }
            return json;
        } catch (err) {
            console.error('[GIMS API]', err);
            return { success: false, message: err.message || 'Network error' };
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Sidebar toggle                                                    */
    /* ------------------------------------------------------------------ */
    function initSidebar() {
        const sb  = document.getElementById('gimsSidebar');
        const ov  = document.getElementById('sidebarOverlay');
        const btn = document.getElementById('sidebarToggle');
        if (!sb || !btn) return;

        const close = () => { sb.classList.remove('show'); ov && ov.classList.remove('show'); };
        const open  = () => { sb.classList.add('show');    ov && ov.classList.add('show'); };

        btn.addEventListener('click', () => {
            sb.classList.contains('show') ? close() : open();
        });
        ov && ov.addEventListener('click', close);
    }

    /* ------------------------------------------------------------------ */
    /*  Theme toggle                                                      */
    /* ------------------------------------------------------------------ */
    function initTheme() {
        const html = document.documentElement;
        const stored = localStorage.getItem('gims_theme');
        if (stored === 'dark') html.setAttribute('data-theme', 'dark');

        const btn = document.getElementById('themeToggle');
        if (!btn) return;

        btn.addEventListener('click', () => {
            const isDark = html.getAttribute('data-theme') === 'dark';
            if (isDark) {
                html.removeAttribute('data-theme');
                localStorage.setItem('gims_theme', 'light');
                btn.innerHTML = '<i class="bi bi-moon-stars"></i>';
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('gims_theme', 'dark');
                btn.innerHTML = '<i class="bi bi-sun"></i>';
            }
        });

        if (html.getAttribute('data-theme') === 'dark') {
            btn.innerHTML = '<i class="bi bi-sun"></i>';
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Global search redirect                                            */
    /* ------------------------------------------------------------------ */
    function initGlobalSearch() {
        const input = document.getElementById('globalSearch');
        if (!input) return;
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && input.value.trim()) {
                window.location.href = `${G.baseUrl}/products/index.php?q=${encodeURIComponent(input.value.trim())}`;
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Notification polling                                              */
    /* ------------------------------------------------------------------ */
    function initNotificationPolling() {
        const badge = document.getElementById('notifBadge');
        if (!badge) return;

        async function refresh() {
            const res = await api(`${G.baseUrl}/api/notifications.php?action=count`);
            if (res.success && res.data) {
                const n = res.data.count;
                if (n > 0) {
                    badge.textContent = n > 99 ? '99+' : n;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            }
        }
        setInterval(refresh, 60000);
    }

    /* ------------------------------------------------------------------ */
    /*  Confirm delete helper                                             */
    /* ------------------------------------------------------------------ */
    function initConfirmButtons() {
        document.addEventListener('click', (e) => {
            const el = e.target.closest('[data-confirm]');
            if (!el) return;
            const msg = el.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Bootstrap tooltips                                                */
    /* ------------------------------------------------------------------ */
    function initTooltips() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Public API                                                        */
    /* ------------------------------------------------------------------ */
    window.GIMS_APP = {
        toast,
        showLoader,
        api,
        csrfHeaders
    };

    /* ------------------------------------------------------------------ */
    /*  Init on DOM ready                                                 */
    /* ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', () => {
        initSidebar();
        initTheme();
        initGlobalSearch();
        initNotificationPolling();
        initConfirmButtons();
        initTooltips();
    });
})();