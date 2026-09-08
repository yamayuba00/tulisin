// ---- Agent AI: pembuat struktur project dari deskripsi ----
// Mengubah input user (jenis dokumen, judul, deskripsi, daftar bab) menjadi
// blok canvas yang siap dibuka di builder. Deterministik (belum LLM nyata).

import { PROJECT_CATEGORIES } from './projectCategories';

// Jenis dokumen mengikuti kategori project yang sama agar konsisten.
export const AGENT_DOCUMENT_TYPES = PROJECT_CATEGORIES;

export const DEFAULT_CHAPTERS = {
    Skripsi: ['Pendahuluan', 'Kajian Pustaka', 'Metodologi Penelitian', 'Hasil dan Pembahasan', 'Kesimpulan dan Saran'],
    Tesis: ['Pendahuluan', 'Kajian Pustaka', 'Metodologi Penelitian', 'Hasil dan Pembahasan', 'Kesimpulan dan Saran'],
    Disertasi: ['Pendahuluan', 'Kajian Pustaka', 'Metodologi Penelitian', 'Hasil dan Pembahasan', 'Kesimpulan dan Saran'],
    Makalah: ['Pendahuluan', 'Metode Penelitian', 'Hasil dan Pembahasan', 'Kesimpulan'],
    Jurnal: ['Pendahuluan', 'Metode', 'Hasil dan Pembahasan', 'Kesimpulan'],
    Laporan: ['Pendahuluan', 'Rencana Pelaksanaan', 'Anggaran', 'Penutup'],
    Proposal: ['Pendahuluan', 'Rencana Pelaksanaan', 'Anggaran', 'Penutup'],
    Esai: ['Pendahuluan', 'Pembahasan', 'Kesimpulan'],
    Lainnya: ['Pendahuluan', 'Pembahasan', 'Kesimpulan'],
};

// Bagian depan/belakang dokumen per jenis. Mengontrol blok mana yang disertakan
// agar struktur menyesuaikan fungsi tiap jenis dokumen (bukan selalu skripsi).
export const DOCUMENT_STRUCTURE = {
    Skripsi: { cover: true, abstract: true, toc: true, listTables: true, listFigures: true, references: true },
    Tesis: { cover: true, abstract: true, toc: true, listTables: true, listFigures: true, references: true },
    Disertasi: { cover: true, abstract: true, toc: true, listTables: true, listFigures: true, references: true },
    Makalah: { cover: true, abstract: true, toc: true, listTables: false, listFigures: false, references: true },
    Jurnal: { cover: false, abstract: true, toc: false, listTables: false, listFigures: false, references: true },
    Laporan: { cover: true, abstract: true, toc: true, listTables: true, listFigures: true, references: true },
    Proposal: { cover: true, abstract: true, toc: true, listTables: false, listFigures: false, references: true },
    Esai: { cover: false, abstract: false, toc: false, listTables: false, listFigures: false, references: true },
    Lainnya: { cover: true, abstract: true, toc: true, listTables: false, listFigures: false, references: true },
};

function alignFor(type) {
    if (['chapter', 'cover', 'formula'].includes(type)) return 'center';
    if (['paragraph', 'quote', 'abstract', 'blankPage'].includes(type)) return 'justify';
    return 'left';
}

function makeBlock(type, content = '', extra = {}) {
    return {
        uid: crypto.randomUUID(),
        type,
        content,
        indent: 0,
        align: alignFor(type),
        width: 100,
        spacing: 24,
        fontFamily: '',
        fontSize: 0,
        lineHeight: 0,
        color: '',
        caption: '',
        captionPosition: type === 'table' ? 'above' : 'below',
        showCaption: true,
        customNumber: '',
        pageTitle: type === 'blankPage' ? 'HALAMAN' : '',
        ...extra,
    };
}

function normalizeChapters(raw, type) {
    const fallback = DEFAULT_CHAPTERS[type] || DEFAULT_CHAPTERS.Lainnya;
    if (!raw || !raw.trim()) return fallback;
    const list = raw
        .split(/[,;\n]/)
        .map((s) => s.trim())
        .filter(Boolean);
    return list.length ? list : fallback;
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Cover berisi judul dokumen + jenis dokumen agar langsung tampil di canvas.
function coverContent(title, documentType) {
    return `<h1 style="font-size:1.6em;margin:0 0 0.5em;font-weight:700">${escapeHtml(title)}</h1>`
        + `<p style="margin:0;font-size:1em;color:#525252">${escapeHtml(documentType)}</p>`;
}

function abstractContent(description) {
    if (description.trim()) {
        return `<p>${escapeHtml(description.trim()).replace(/\n+/g, '<br>')}</p>`;
    }
    return '<p>Ringkasan latar belakang, tujuan, metode, dan hasil utama dari penelitian atau karya ini.</p>';
}

// Susun blok dokumen dari input agent. Mengembalikan payload project siap simpan.
export function buildAgentProject({ title = '', documentType = 'Skripsi', description = '', chapters = '' }) {
    const chapterList = normalizeChapters(chapters, documentType);
    const cleanTitle = title.trim() || 'Proyek Tanpa Judul';
    const sections = DOCUMENT_STRUCTURE[documentType] || DOCUMENT_STRUCTURE.Lainnya;

    const blocks = [];

    if (sections.cover) {
        blocks.push(makeBlock('cover', coverContent(cleanTitle, documentType)));
    } else {
        // Jenis tanpa halaman cover (Jurnal/Esai): judul jadi heading terpusat di atas.
        blocks.push(makeBlock('h1', escapeHtml(cleanTitle), { align: 'center' }));
    }

    if (sections.abstract) blocks.push(makeBlock('abstract', abstractContent(description)));
    if (sections.toc) blocks.push(makeBlock('toc'));
    if (sections.listTables) blocks.push(makeBlock('listTables'));
    if (sections.listFigures) blocks.push(makeBlock('listFigures'));

    for (const name of chapterList) {
        blocks.push(makeBlock('chapter', name));
        blocks.push(makeBlock('paragraph', `<p>Jelaskan isi bab "${escapeHtml(name)}" terkait topik "${escapeHtml(cleanTitle)}".</p>`));
    }

    if (sections.references) blocks.push(makeBlock('references'));

    return {
        name: cleanTitle,
        category: documentType,
        format: 'A4',
        orientation: 'portrait',
        margins: { top: 2.54, right: 2.54, bottom: 2.54, left: 2.54 },
        lastEdited: Date.now(),
        font: 'Times New Roman',
        customFont: '',
        fontSize: 12,
        lineHeight: 1.5,
        pageNumberPosition: 'bottom-center',
        frontMatterStyle: 'roman',
        bodyStyle: 'decimal',
        bodyStart: 1,
        citationStyle: 'APA',
        citedReferences: [],
        hiddenTocUids: [],
        blocks,
    };
}
