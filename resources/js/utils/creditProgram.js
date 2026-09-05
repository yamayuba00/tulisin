// ---- Program Dapatkan Kredit ----
// Pengguna mengirim URL untuk diverifikasi admin, lalu mendapat kredit.
// Penyimpanan lokal sementara; nantinya diganti ke API backend.

const SUBMISSIONS_KEY = 'tulisin.creditSubmissions';

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
        // localStorage penuh/tidak tersedia — abaikan.
    }
}

function makeId() {
    return typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export function listSubmissions() {
    return readJson(SUBMISSIONS_KEY, []);
}

export function addSubmission({ url, notes = '' }) {
    const item = {
        id: makeId(),
        url,
        notes,
        status: 'pending', // pending | approved | rejected
        credits: 0,
        createdAt: Date.now(),
    };
    const items = listSubmissions();
    items.unshift(item);
    writeJson(SUBMISSIONS_KEY, items);
    return item;
}
