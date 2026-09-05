// ---- Image File Manager: struktur data & kredit ----
// Koleksi gambar disimpan di backend (disk publik + tabel `media`), bukan
// localStorage, agar tidak kena kuota browser. Antarmuka async supaya pemanggil
// tetap sama.

import { request } from './http';
import { imageCostPerItem } from './creditPricing';

const USAGE_KEY = 'tulisin.imageUsage';

function readJson(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

function writeJson(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // metadata pemakaian kecil; abaikan bila gagal.
    }
}

// Daftar gambar (terbaru di depan), dari backend.
export async function listImages() {
    const res = await request('/api/media', { method: 'GET' });
    return res.ok && Array.isArray(res.data) ? res.data : [];
}

export async function addImage(file) {
    const form = new FormData();
    form.append('file', file);

    const res = await request('/api/media', { method: 'POST', body: form });
    if (!res.ok) {
        throw new Error(res.data?.error || 'Gagal mengunggah gambar.');
    }
    return res.data;
}

export async function removeImage(id) {
    const res = await request(`/api/media/${id}`, { method: 'DELETE' });
    if (!res.ok) {
        throw new Error(res.data?.error || 'Gagal menghapus gambar.');
    }
    return res.data;
}

// Pemakaian kredit/gambar (metadata kecil, tetap di localStorage).
export function getImageUsage() {
    const u = readJson(USAGE_KEY, { used: 0, creditsSpent: 0 });
    return { used: Number(u.used) || 0, creditsSpent: Number(u.creditsSpent) || 0 };
}

export function recordImageUse(count = 1) {
    const usage = getImageUsage();
    usage.used += count;
    usage.creditsSpent += count * imageCostPerItem();
    writeJson(USAGE_KEY, usage);
    return usage;
}
