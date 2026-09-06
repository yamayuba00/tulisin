// ---- Font kustom (TTF/OTF/WOFF) ----
// Font disimpan di backend (object storage + tabel `fonts`), bukan localStorage,
// agar tersedia lintas perangkat dan bisa disinkronkan ke dokumen yang dibagikan.

import { request } from './http';

export async function listCustomFonts() {
    const res = await request('/api/fonts', { method: 'GET' });
    return res.ok && Array.isArray(res.data?.fonts) ? res.data.fonts : [];
}

export async function addCustomFont(file) {
    const form = new FormData();
    form.append('file', file);

    const res = await request('/api/fonts', { method: 'POST', body: form });
    if (!res.ok) {
        throw new Error(res.data?.error || 'Gagal mengunggah font.');
    }
    return res.data?.font;
}

export async function removeCustomFont(id) {
    const res = await request(`/api/fonts/${encodeURIComponent(id)}`, { method: 'DELETE' });
    if (!res.ok) {
        throw new Error(res.data?.error || 'Gagal menghapus font.');
    }
    return res.data;
}

export function registerFontFace(font) {
    if (!font || typeof font.family !== 'string' || !font.dataUrl) return;
    const id = `custom-font-${font.family}`;
    if (document.getElementById(id)) return;
    const style = document.createElement('style');
    style.id = id;
    style.textContent =
        `@font-face { font-family: '${font.family.replace(/'/g, '')}'; ` +
        `src: url('${font.dataUrl}') format('${font.format || 'truetype'}'); ` +
        'font-weight: normal; font-style: normal; }';
    document.head.appendChild(style);
}

export function unregisterFontFace(family) {
    const el = document.getElementById(`custom-font-${family}`);
    if (el) el.remove();
}
