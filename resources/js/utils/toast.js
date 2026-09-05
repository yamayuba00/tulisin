// Toast global (top-center) — satu gaya konsisten untuk seluruh aplikasi.
// Dipakai lewat `toast('pesan')` atau `toast('pesan', 'success'|'error'|'warning'|'info')`.

let host = null;
let timer = null;

const TONES = {
    info: { light: { bg: '#171717', fg: '#ffffff' }, dark: { bg: '#ffffff', fg: '#171717' } },
    success: { light: { bg: '#059669', fg: '#ffffff' }, dark: { bg: '#10b981', fg: '#ffffff' } },
    error: { light: { bg: '#dc2626', fg: '#ffffff' }, dark: { bg: '#ef4444', fg: '#ffffff' } },
    warning: { light: { bg: '#d97706', fg: '#ffffff' }, dark: { bg: '#f59e0b', fg: '#ffffff' } },
};

function ensureHost() {
    if (host && document.body.contains(host)) return host;
    host = document.createElement('div');
    document.body.appendChild(host);
    return host;
}

function isDark() {
    return document.documentElement.classList.contains('dark');
}

export function toast(message, type = 'info') {
    if (typeof document === 'undefined') return;

    const el = ensureHost();
    const scheme = TONES[type] || TONES.info;
    const tone = isDark() ? scheme.dark : scheme.light;

    el.textContent = message;
    el.style.cssText = `
        position: fixed;
        left: 50%;
        top: 16px;
        z-index: 100;
        pointer-events: none;
        background: ${tone.bg};
        color: ${tone.fg};
        border-radius: 9999px;
        padding: 8px 16px;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.25;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        opacity: 0;
        transform: translate(-50%, -8px);
        transition: opacity 0.2s ease, transform 0.2s ease;
    `;

    requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translate(-50%, 0)';
    });

    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translate(-50%, -8px)';
    }, 2500);
}
