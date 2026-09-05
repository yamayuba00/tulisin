// HTTP client ringan untuk API internal (cookie/session Sanctum).
// Dipakai berulang oleh halaman-halaman frontend tanpa localStorage.

function getCookie(name) {
    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'),
    );
    return match ? decodeURIComponent(match[1]) : null;
}

// Ambil cookie CSRF + mulai sesi (diperlukan sebelum request state-changing).
export async function ensureCsrf() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
}

// Request generik. Selalu menyertakan cookie sesi + header CSRF otomatis.
export async function request(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        const token = getCookie('XSRF-TOKEN');
        if (token) headers.set('X-XSRF-TOKEN', token);
    }

    const res = await fetch(url, { ...options, method, headers, credentials: 'include' });

    let data = null;
    const text = await res.text();
    try {
        data = text ? JSON.parse(text) : null;
    } catch {
        data = text;
    }

    return { ok: res.ok, status: res.status, data };
}

// GET sederhana yang melempar error bila response gagal.
export async function getJson(url) {
    const { ok, status, data } = await request(url, { method: 'GET' });
    if (!ok) {
        const message = data?.message || data?.error || `Request gagal (${status})`;
        throw new Error(message);
    }
    return data;
}
