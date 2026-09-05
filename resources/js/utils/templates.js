// ---- Template dokumen bawaan ----
// Template siap pakai yang bisa langsung diterapkan ke canvas builder.
// Setiap template berupa daftar "spec" blok (tanpa uid); uid dibuat baru saat
// template diinstantiasi ke project agar tiap project punya identitas unik.

// Spesifikasi blok minimal: type + content (+ pageTitle untuk halaman khusus).
function spec(type, content = '', extra = {}) {
    return { type, content, ...extra };
}

// Alignment bawaan mengikuti logika builder (disinkronkan manual).
function alignFor(type) {
    if (['chapter', 'cover', 'formula'].includes(type)) return 'center';
    if (['paragraph', 'quote', 'abstract', 'blankPage'].includes(type)) return 'justify';
    return 'left';
}

const PH = (text) => `<p>${text}</p>`;

// Biaya aktual pemakaian template dibaca dinamis dari `creditPricing` (default 5).
// Nilai di bawah dipakai sebagai cadangan bila pricing gagal dimuat.
export const TEMPLATE_COST = 5;

export const TEMPLATES = [
    {
        id: 'skripsi',
        title: 'Skripsi / Tesis',
        category: 'Skripsi',
        description: 'Struktur lengkap skripsi/tesis: cover, abstrak, daftar isi, bab pendahuluan hingga kesimpulan, dan daftar pustaka.',
        format: 'A4',
        font: 'Times New Roman',
        blocks: [
            spec('cover'),
            spec('abstract', PH('Abstrak penelitian berisi ringkasan latar belakang, tujuan, metode, hasil, dan kesimpulan dalam satu paragraf ringkas.')),
            spec('toc'),
            spec('chapter', 'PENDAHULUAN'),
            spec('h1', 'Latar Belakang'),
            spec('paragraph', PH('Jelaskan latar belakang masalah, alasan topik ini penting, serta kesenjangan yang ingin dijawab oleh penelitian ini.')),
            spec('h1', 'Rumusan Masalah'),
            spec('paragraph', PH('Tuliskan rumusan masalah dalam bentuk kalimat tanya yang menjadi fokus utama penelitian.')),
            spec('h1', 'Tujuan Penelitian'),
            spec('paragraph', PH('Sebutkan tujuan yang ingin dicapai sejalan dengan rumusan masalah.')),
            spec('chapter', 'KAJIAN PUSTAKA'),
            spec('h1', 'Landasan Teori'),
            spec('paragraph', PH('Uraikan teori dan konsep yang menjadi dasar penelitian, disertai sitasi dari sumber terpercaya.')),
            spec('chapter', 'METODOLOGI PENELITIAN'),
            spec('h1', 'Jenis Penelitian'),
            spec('paragraph', PH('Jelaskan pendekatan dan jenis penelitian yang digunakan (kualitatif/kuantitatif/mix-method).')),
            spec('h1', 'Teknik Pengumpulan Data'),
            spec('paragraph', PH('Deskripsikan cara memperoleh data: observasi, wawancara, kuesioner, atau studi pustaka.')),
            spec('chapter', 'HASIL DAN PEMBAHASAN'),
            spec('h1', 'Hasil'),
            spec('paragraph', PH('Paparkan temuan penelitian secara objektif, dapat dilengkapi tabel atau gambar.')),
            spec('h1', 'Pembahasan'),
            spec('paragraph', PH('Analisis hasil dan kaitkan dengan teori serta penelitian terdahulu.')),
            spec('chapter', 'KESIMPULAN DAN SARAN'),
            spec('h1', 'Kesimpulan'),
            spec('paragraph', PH('Rangkum jawaban atas rumusan masalah secara ringkas.')),
            spec('h1', 'Saran'),
            spec('paragraph', PH('Berikan rekomendasi untuk penelitian lanjutan atau penerapan praktis.')),
            spec('references'),
        ],
    },
    {
        id: 'makalah',
        title: 'Makalah / Jurnal',
        category: 'Makalah',
        description: 'Format artikel ilmiah ringkas: judul, abstrak, pendahuluan, metode, hasil, kesimpulan, dan daftar pustaka.',
        format: 'A4',
        font: 'Times New Roman',
        blocks: [
            spec('cover'),
            spec('abstract', PH('Ringkasan singkat artikel: tujuan, metode, hasil utama, dan kesimpulan dalam satu paragraf.')),
            spec('h1', 'Pendahuluan'),
            spec('paragraph', PH('Latar belakang dan tujuan artikel, serta kontribusi singkat terhadap bidang kajian.')),
            spec('h1', 'Metode Penelitian'),
            spec('paragraph', PH('Jelaskan desain penelitian, sumber data, dan teknik analisis yang digunakan.')),
            spec('h1', 'Hasil dan Pembahasan'),
            spec('paragraph', PH('Sajikan temuan utama dan interpretasinya secara padat.')),
            spec('h1', 'Kesimpulan'),
            spec('paragraph', PH('Tarik kesimpulan dari hasil dan sebutkan implikasi atau saran.')),
            spec('references'),
        ],
    },
    {
        id: 'proposal',
        title: 'Proposal / Laporan',
        category: 'Proposal',
        description: 'Kerangka proposal kegiatan/penelitian: latar belakang, tujuan, rencana pelaksanaan, anggaran, dan penutup.',
        format: 'A4',
        font: 'Times New Roman',
        blocks: [
            spec('cover'),
            spec('abstract', PH('Ringkasan eksekutif: gambaran umum kegiatan, tujuan, sasaran, dan estimasi anggaran.')),
            spec('toc'),
            spec('chapter', 'PENDAHULUAN'),
            spec('h1', 'Latar Belakang'),
            spec('paragraph', PH('Jelaskan alasan kegiatan/program ini diusulkan dan urgensi pelaksanaannya.')),
            spec('h1', 'Tujuan dan Sasaran'),
            spec('paragraph', PH('Uraikan tujuan umum, tujuan khusus, serta sasaran yang ingin dicapai.')),
            spec('chapter', 'RENCANA PELAKSANAAN'),
            spec('h1', 'Metode Pelaksanaan'),
            spec('paragraph', PH('Deskripsikan tahapan, metode, dan teknis pelaksanaan kegiatan.')),
            spec('h1', 'Jadwal Kegiatan'),
            spec('paragraph', PH('Cantumkan linimasa pelaksanaan dari awal hingga evaluasi akhir.')),
            spec('chapter', 'ANGGARAN'),
            spec('h1', 'Rencana Anggaran'),
            spec('paragraph', PH('Rinci kebutuhan anggaran per pos pengeluaran.')),
            spec('chapter', 'PENUTUP'),
            spec('h1', 'Kesimpulan'),
            spec('paragraph', PH('Simpulkan manfaat dan harapan dari pelaksanaan kegiatan.')),
            spec('references'),
        ],
    },
];

// Instansiasi blok template menjadi blok canvas lengkap dengan uid baru.
export function buildTemplateBlocks(template) {
    return (template.blocks || []).map((b) => ({
        uid: crypto.randomUUID(),
        type: b.type,
        content: b.content ?? '',
        indent: 0,
        align: alignFor(b.type),
        width: 100,
        spacing: 24,
        fontFamily: '',
        fontSize: 0,
        lineHeight: 0,
        color: '',
        caption: '',
        captionPosition: b.type === 'table' ? 'above' : 'below',
        showCaption: true,
        customNumber: '',
        pageTitle: b.pageTitle ?? (b.type === 'blankPage' ? 'HALAMAN' : ''),
    }));
}

// Bentuk payload project baru (localStorage builder) dari template.
// Mengikuti skema yang dibaca `loadProjectSettings` di apps/project/index.vue.
export function buildProjectPayload(template) {
    return {
        name: template.title,
        category: template.category,
        format: template.format || 'A4',
        orientation: 'portrait',
        margins: { top: 2.54, right: 2.54, bottom: 2.54, left: 2.54 },
        lastEdited: Date.now(),
        font: template.font || 'Times New Roman',
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
        blocks: buildTemplateBlocks(template),
    };
}

// ---- Template kustom (buatan user) ----
// Disimpan di localStorage agar bisa dibuat & dipakai ulang tanpa backend.

const CUSTOM_TEMPLATES_KEY = 'tulisin.customTemplates';

// Jenis blok yang bisa dipilih saat membuat template sendiri.
export const TEMPLATE_BLOCK_TYPES = [
    { type: 'cover', label: 'Cover' },
    { type: 'abstract', label: 'Abstrak' },
    { type: 'toc', label: 'Daftar Isi' },
    { type: 'chapter', label: 'Judul Bab' },
    { type: 'h1', label: 'Heading 1' },
    { type: 'h2', label: 'Heading 2' },
    { type: 'h3', label: 'Heading 3' },
    { type: 'paragraph', label: 'Paragraf' },
    { type: 'bullet', label: 'List Poin' },
    { type: 'number', label: 'List Nomor' },
    { type: 'quote', label: 'Kutipan' },
    { type: 'table', label: 'Tabel' },
    { type: 'image', label: 'Gambar' },
    { type: 'divider', label: 'Pembatas' },
    { type: 'spacer', label: 'Spacer (Jarak)' },
    { type: 'references', label: 'Daftar Pustaka' },
    { type: 'blankPage', label: 'Halaman Kosong' },
];

export function listCustomTemplates() {
    try {
        return JSON.parse(localStorage.getItem(CUSTOM_TEMPLATES_KEY) || '[]');
    } catch {
        return [];
    }
}

export function saveCustomTemplate(template) {
    const list = listCustomTemplates();
    const idx = list.findIndex((t) => t.id === template.id);
    if (idx >= 0) list[idx] = template;
    else list.push(template);
    localStorage.setItem(CUSTOM_TEMPLATES_KEY, JSON.stringify(list));
    return template;
}

export function deleteCustomTemplate(id) {
    const list = listCustomTemplates().filter((t) => t.id !== id);
    localStorage.setItem(CUSTOM_TEMPLATES_KEY, JSON.stringify(list));
}
