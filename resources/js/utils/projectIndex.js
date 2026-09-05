// ---- Indeks project (metadata ringan + pratinjau) ----
// Menyimpan daftar project di localStorage sebagai entri kecil (id, nama,
// kategori, waktu edit, dan pratinjau teks ringan) agar halaman daftar project
// tidak perlu membaca/mem-parse seluruh isi project (yang bisa besar karena blok
// + data gambar). Pratinjau disimpan agar kartu project bisa menampilkan
// "snapshot" isi dokumen tanpa membaca file project penuh.

const INDEX_KEY = 'tulisin:projects';

const PREVIEW_MAX_LINES = 12;

function readIndex() {
    try {
        const raw = localStorage.getItem(INDEX_KEY);
        const data = raw ? JSON.parse(raw) : [];
        return Array.isArray(data) ? data : [];
    } catch {
        return [];
    }
}

function writeIndex(items) {
    try {
        localStorage.setItem(INDEX_KEY, JSON.stringify(items));
    } catch {
        // abaikan jika localStorage penuh/tidak tersedia
    }
}

function stripHtml(html) {
    return String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

// Susun pratinjau singkat dari blok dokumen menjadi baris { kind, text }.
// kind: 'heading' | 'subheading' | 'text'.
export function buildProjectPreview(blocks = []) {
    const lines = [];
    const push = (kind, text) => {
        if (!text) return;
        lines.push({ kind, text });
    };

    for (const b of blocks) {
        if (lines.length >= PREVIEW_MAX_LINES) break;
        const text = stripHtml(b.content).slice(0, 140);
        switch (b.type) {
            case 'abstract':
                push('heading', 'ABSTRAK');
                break;
            case 'toc':
                push('heading', 'DAFTAR ISI');
                break;
            case 'listTables':
                push('heading', 'DAFTAR TABEL');
                break;
            case 'listFigures':
                push('heading', 'DAFTAR GAMBAR');
                break;
            case 'references':
                push('heading', 'DAFTAR PUSTAKA');
                break;
            case 'chapter':
                push('heading', text || 'BAB');
                break;
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
            case 'h7':
            case 'h8':
            case 'h9':
            case 'h10':
                push('subheading', text);
                break;
            case 'bullet':
            case 'number':
            case 'quote':
            case 'paragraph':
                push('text', text);
                break;
            case 'formula':
                push('text', text || 'Rumus');
                break;
            case 'code':
                push('text', text || 'Kode');
                break;
            case 'table':
                push('text', 'Tabel');
                break;
            case 'image':
                push('text', 'Gambar');
                break;
            case 'cover':
            case 'blankPage':
            case 'pageBreak':
            case 'divider':
            case 'spacer':
                break;
            default:
                push('text', text);
        }
    }

    return lines;
}

// Daftar project, terbaru diedit di depan.
export function listProjects() {
    return readIndex().sort((a, b) => (Number(b.lastEdited) || 0) - (Number(a.lastEdited) || 0));
}

// Catat/perbarui metadata + pratinjau project ke indeks.
export function touchProject(id, meta = {}) {
    if (!id) return;
    const items = readIndex().filter((p) => p.id !== id);
    items.push({
        id,
        name: meta.name || 'Proyek Tanpa Judul',
        category: meta.category || 'Lainnya',
        lastEdited: Number(meta.lastEdited) || Date.now(),
        preview: Array.isArray(meta.preview)
            ? meta.preview
            : (Array.isArray(meta.blocks) ? buildProjectPreview(meta.blocks) : []),
    });
    writeIndex(items);
}

// Fallback: baca pratinjau langsung dari data project (untuk entri lama tanpa preview).
export function getProjectPreview(id) {
    try {
        const raw = localStorage.getItem(`tulisin:project:${id}`);
        if (!raw) return [];
        const data = JSON.parse(raw);
        return Array.isArray(data.blocks) ? buildProjectPreview(data.blocks) : [];
    } catch {
        return [];
    }
}

// Hapus project dari indeks beserta data project-nya.
export function removeProject(id) {
    if (!id) return;
    writeIndex(readIndex().filter((p) => p.id !== id));
    try {
        localStorage.removeItem(`tulisin:project:${id}`);
    } catch {
        // abaikan
    }
}
