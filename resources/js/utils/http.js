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

// Request streaming (Server-Sent Events). Memanggil onEvent(parsedJson) untuk
// tiap event `data:` yang diterima. Dipakai untuk menampilkan balasan AI bertahap.
export async function streamJson(url, options = {}, onEvent) {
    const method = (options.method || 'POST').toUpperCase();
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'text/event-stream');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    const token = getCookie('XSRF-TOKEN');
    if (token) headers.set('X-XSRF-TOKEN', token);

    const res = await fetch(url, { ...options, method, headers, credentials: 'include' });

    if (!res.ok) {
        const text = await res.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch {
            data = text;
        }
        throw new Error(data?.error || data?.message || `Request gagal (${res.status})`);
    }

    if (!res.body) {
        throw new Error('Browser tidak mendukung streaming.');
    }

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        let idx;
        while ((idx = buffer.indexOf('\n')) !== -1) {
            const line = buffer.slice(0, idx).trim();
            buffer = buffer.slice(idx + 1);

            if (!line.startsWith('data:')) continue;
            const raw = line.slice(5).trim();
            if (raw === '[DONE]') return;

            let parsed = null;
            try {
                parsed = JSON.parse(raw);
            } catch {
                continue;
            }
            if (parsed) onEvent(parsed);
        }
    }
}
