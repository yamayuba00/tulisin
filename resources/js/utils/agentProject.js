// ---- Agent AI: pembuat struktur project dari deskripsi ----
// Mengubah input user (jenis dokumen, judul, deskripsi, daftar bab) menjadi
// blok canvas yang siap dibuka di builder. Deterministik (belum LLM nyata).

export const AGENT_DOCUMENT_TYPES = [
    'Skripsi',
    'Tesis',
    'Disertasi',
    'Makalah',
    'Jurnal',
    'Laporan',
    'Proposal',
    'Esai',
    'Lainnya',
];

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

// Susun blok dokumen dari input agent. Mengembalikan payload project siap simpan.
export function buildAgentProject({ title = '', documentType = 'Skripsi', description = '', chapters = '' }) {
    const chapterList = normalizeChapters(chapters, documentType);
    const cleanTitle = title.trim() || 'Proyek Tanpa Judul';

    const blocks = [];
    blocks.push(makeBlock('cover'));

    const abstractText = description.trim()
        ? `<p>${description.trim()}</p>`
        : '<p>Ringkasan latar belakang, tujuan, metode, dan hasil utama dari penelitian atau karya ini.</p>';
    blocks.push(makeBlock('abstract', abstractText));

    blocks.push(makeBlock('toc'));

    for (const name of chapterList) {
        blocks.push(makeBlock('chapter', name));
        blocks.push(makeBlock('paragraph', `<p>Jelaskan isi bab "${name}" terkait topik "${cleanTitle}".</p>`));
    }

    blocks.push(makeBlock('references'));

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
