// workspaceLibrary.js — Penyimpanan referensi Tulisin Workspace.
// Referensi disimpan per akun di server (workspace_references) dan di-cache
// secara reaktif di memori. localStorage hanya dipakai sebagai cadangan offline
// dan untuk migrasi otomatis saat server belum punya data.

import { reactive } from 'vue';
import { request } from './http';

const STORAGE_KEY = 'tulisin:workspace:library';

function uid() {
    return 'ws_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
}

function loadLocal() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function persistLocal(items) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
        // Abaikan bila localStorage tidak tersedia.
    }
}

// Store reaktif — sumber data tunggal untuk seluruh UI.
const store = reactive({ items: loadLocal() });

// Normalisasi penulis menjadi array { family, given } (dipahami parseCSLItem & authorYearLabel).
function parseAuthor(name) {
    const value = (name || '').trim();
    if (!value) return null;
    if (value.includes(',')) {
        const [family, given] = value.split(',').map((p) => p.trim());
        return { family: family || '', given: given || '' };
    }
    const parts = value.split(/\s+/);
    if (parts.length === 1) return { family: parts[0], given: '' };
    return { family: parts[parts.length - 1], given: parts.slice(0, -1).join(' ') };
}

function normalizeAuthors(author) {
    return (author || '')
        .replace(/&/g, ';')
        .split(/[;\n]/)
        .map(parseAuthor)
        .filter(Boolean);
}

// Ubah hasil readPdf()/AI menjadi item CSL-JSON.
export function pdfToCSL(pdf, filename = '') {
    return {
        id: uid(),
        type: pdf.type || 'article-journal',
        title: (pdf.title || '').trim() || 'Tanpa Judul',
        author: normalizeAuthors(pdf.author),
        issued: { 'date-parts': [[pdf.year || '']] },
        DOI: pdf.doi || '',
        'container-title': pdf.journal || '',
        volume: pdf.volume || '',
        issue: pdf.issue || '',
        page: pdf.page || '',
        // metadata tambahan (tidak dipakai formatter, hanya untuk tampilan Workspace)
        _pages: pdf.pageCount || pdf.pages || 1,
        _abstract: pdf.abstract || '',
        _keywords: Array.isArray(pdf.keywords) ? pdf.keywords : [],
        _snippet: pdf.snippet || '',
        _filename: filename || pdf.filename || '',
        _fileId: pdf.fileId || '',
        _fileUrl: pdf.fileUrl || '',
        _addedAt: Date.now(),
    };
}

export function listReferences() {
    return store.items;
}

// Sinkronkan referensi dengan server. Jika server kosong dan masih ada data
// localStorage, unggah data tersebut (migrasi otomatis) lalu bersihkan lokal.
export async function syncReferences() {
    try {
        const res = await request('/api/workspace/references');
        if (!res.ok) return store.items;

        let items = Array.isArray(res.data) ? res.data : [];
        const local = loadLocal();

        if (items.length === 0 && local.length) {
            const up = await request('/api/workspace/references', {
                method: 'POST',
                body: JSON.stringify({ items: local }),
            });
            if (up.ok && Array.isArray(up.data)) {
                items = up.data;
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch {
                    // Abaikan.
                }
            } else {
                items = local;
            }
        }

        store.items = items;
    } catch {
        // Tetap pakai data lokal bila gagal sinkron.
    }

    return store.items;
}

export function addReferences(items) {
    for (const item of items) {
        if (!store.items.some((r) => r.id === item.id)) store.items.push(item);
    }
    persistLocal(store.items);

    // Kirim ke server (tidak memblokir UI).
    request('/api/workspace/references', {
        method: 'POST',
        body: JSON.stringify({ items }),
    }).catch(() => {});

    return store.items;
}

export function updateReference(id, patch) {
    const idx = store.items.findIndex((r) => r.id === id);
    if (idx === -1) return store.items;

    const updated = { ...store.items[idx], ...patch };
    store.items.splice(idx, 1, updated);
    persistLocal(store.items);

    request('/api/workspace/references', {
        method: 'POST',
        body: JSON.stringify({ items: [updated] }),
    }).catch(() => {});

    return store.items;
}

export function removeReference(id) {
    store.items = store.items.filter((r) => r.id !== id);
    persistLocal(store.items);

    request(`/api/workspace/references/${encodeURIComponent(id)}`, {
        method: 'DELETE',
    }).catch(() => {});

    return store.items;
}
