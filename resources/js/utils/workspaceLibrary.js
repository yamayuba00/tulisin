// workspaceLibrary.js — Penyimpanan referensi Tulisin Workspace.
// Mengubah hasil readPdf() menjadi CSL-JSON (format yang dipahami builder sitasi)
// lalu menyimpannya ke localStorage agar bisa dipakai di builder tanpa backend.

const STORAGE_KEY = 'tulisin:workspace:library';

function uid() {
    return 'ws_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
}

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
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function persist(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
}

export function addReferences(items) {
    const list = listReferences();
    for (const item of items) {
        if (!list.some((r) => r.id === item.id)) list.push(item);
    }
    persist(list);
    return list;
}

export function updateReference(id, patch) {
    const list = listReferences().map((r) => (r.id === id ? { ...r, ...patch } : r));
    persist(list);
    return list;
}

export function removeReference(id) {
    const list = listReferences().filter((r) => r.id !== id);
    persist(list);
    return list;
}
