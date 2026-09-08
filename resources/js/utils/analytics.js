// Tracking traffic ringan: kirim beacon pageview ke backend saat navigasi SPA.
// Tidak mengganggu pengalaman user (fire-and-forget) & tanpa CSRF (GET).

function getSessionId() {
    let id = null;
    try {
        id = sessionStorage.getItem('tulisin_session_id');
    } catch {
        id = null;
    }
    if (!id) {
        id = typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : Math.random().toString(36).slice(2) + Date.now().toString(36);
        try {
            sessionStorage.setItem('tulisin_session_id', id);
        } catch {
            /* abaikan bila storage diblokir */
        }
    }
    return id;
}

export function trackPageview(path, referrer = '') {
    try {
        const params = new URLSearchParams({ path, session_id: getSessionId() });
        if (referrer) params.set('referrer', String(referrer).slice(0, 255));
        fetch(`/api/analytics/pageview?${params.toString()}`, {
            method: 'GET',
            credentials: 'include',
            keepalive: true,
        }).catch(() => {});
    } catch {
        /* abaikan semua error tracking */
    }
}
