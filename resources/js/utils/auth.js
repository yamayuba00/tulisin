// Auth SPA berbasis cookie/session Sanctum.
// Tidak menyimpan token/user di localStorage — sesi dikelola server via cookie.
import { computed, ref } from 'vue';

const currentUser = ref(null);
const isAuthenticated = computed(() => !!currentUser.value);

// Promise inisialisasi: pulihkan sesi dari cookie saat app pertama dimuat.
let readyPromise = null;

function getCookie(name) {
    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'),
    );
    return match ? decodeURIComponent(match[1]) : null;
}

// Wrapper fetch: sertakan cookie sesi + header CSRF otomatis.
async function apiFetch(url, options = {}) {
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

// Ambil cookie CSRF + mulai sesi sebelum request yang mengubah state.
async function ensureCsrf() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
}

function errorMessage(data, fallback) {
    if (data?.message) return data.message;
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first[0]) return first[0];
    }
    return fallback;
}

async function login({ email, password }) {
    await ensureCsrf();
    const { ok, data } = await apiFetch('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
    });
    if (!ok) return { ok: false, error: errorMessage(data, 'Login gagal.') };
    currentUser.value = data.user || null;
    return { ok: true };
}

async function register(payload) {
    await ensureCsrf();
    const { ok, data } = await apiFetch('/api/auth/register', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    if (!ok) return { ok: false, error: errorMessage(data, 'Registrasi gagal.') };
    currentUser.value = data.user || null;
    return { ok: true };
}

async function resetPassword(email) {
    await ensureCsrf();
    const { ok, data } = await apiFetch('/api/auth/forgot-password', {
        method: 'POST',
        body: JSON.stringify({ email }),
    });
    if (!ok) return { ok: false, error: errorMessage(data, 'Gagal memproses permintaan.') };
    return { ok: true };
}

async function confirmResetPassword(payload) {
    await ensureCsrf();
    const { ok, data } = await apiFetch('/api/auth/reset-password', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    if (!ok) return { ok: false, error: errorMessage(data, 'Gagal mereset password.') };
    return { ok: true };
}

async function resendVerification() {
    await ensureCsrf();
    const { ok, data } = await apiFetch('/api/auth/send-verification', { method: 'POST' });
    if (!ok) return { ok: false, error: errorMessage(data, 'Gagal mengirim email verifikasi.') };
    return { ok: true, message: data?.message };
}

async function logout() {
    try {
        await ensureCsrf();
        await apiFetch('/api/auth/logout', { method: 'POST' });
    } catch {
        // Abaikan error logout; tetap bersihkan state lokal.
    } finally {
        currentUser.value = null;
    }
}

// Ambil data user yang sedang login (get me).
async function fetchMe() {
    const { ok, data } = await apiFetch('/api/auth/me', { method: 'GET' });
    currentUser.value = ok ? (data.user || null) : null;
    return currentUser.value;
}

// Pulihkan sesi sekali saat app dimuat (cache hasilnya).
function init() {
    if (!readyPromise) {
        readyPromise = fetchMe().catch(() => {
            currentUser.value = null;
        });
    }
    return readyPromise;
}

export function useAuth() {
    return {
        currentUser,
        isAuthenticated,
        login,
        register,
        resetPassword,
        confirmResetPassword,
        resendVerification,
        logout,
        fetchMe,
        init,
    };
}
