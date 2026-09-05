// ---- Kategori/tipe dokumen proyek ----
// Daftar kategori bersama, dipakai saat membuat project (SetupModal)
// dan untuk filter di halaman Lists Project.

export const PROJECT_CATEGORIES = [
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

export const PROJECT_CATEGORY_OPTIONS = PROJECT_CATEGORIES.map((c) => ({ value: c, label: c }));

export const DEFAULT_PROJECT_CATEGORY = 'Lainnya';
