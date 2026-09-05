// ---- Preset struktur per tipe dokumen ----
// Bagian siap pakai yang bisa disisipkan langsung ke canvas dari sidebar "Blok
// Konten". Setiap section tersusun dari blok-blok bawaan (chapter/h1/paragraph/
// cover/abstract/toc/references) sehingga tidak perlu tipe blok baru.

import {
    BookOpen,
    BookMarked,
    ListTree,
    Library,
    FileText,
    List,
    Table2,
    GraduationCap,
    Newspaper,
    ClipboardList,
    Lightbulb,
    PenLine,
} from 'lucide-vue-next';
import { buildTemplateBlocks } from './templates';

const PH = (text) => `<p>${text}</p>`;

export const DOCUMENT_SECTIONS = [
    {
        id: 'skripsi',
        label: 'Skripsi',
        icon: GraduationCap,
        sections: [
            {
                id: 'skripsi-awal',
                label: 'Bagian Awal',
                icon: ListTree,
                blocks: [
                    { type: 'cover' },
                    { type: 'abstract', content: PH('Ringkasan latar belakang, tujuan, metode, hasil, dan kesimpulan dalam satu paragraf.') },
                    { type: 'toc' },
                ],
            },
            {
                id: 'skripsi-bab1',
                label: 'Bab Pendahuluan',
                icon: BookOpen,
                blocks: [
                    { type: 'chapter', content: 'PENDAHULUAN' },
                    { type: 'h1', content: 'Latar Belakang' },
                    { type: 'paragraph', content: PH('Jelaskan latar belakang masalah dan alasan topik ini penting.') },
                    { type: 'h1', content: 'Rumusan Masalah' },
                    { type: 'paragraph', content: PH('Tuliskan rumusan masalah dalam bentuk kalimat tanya.') },
                    { type: 'h1', content: 'Tujuan Penelitian' },
                    { type: 'paragraph', content: PH('Sebutkan tujuan yang ingin dicapai.') },
                ],
            },
            {
                id: 'skripsi-bab2',
                label: 'Bab Kajian Pustaka',
                icon: BookMarked,
                blocks: [
                    { type: 'chapter', content: 'KAJIAN PUSTAKA' },
                    { type: 'h1', content: 'Landasan Teori' },
                    { type: 'paragraph', content: PH('Uraikan teori yang menjadi dasar penelitian, disertai sitasi.') },
                ],
            },
            {
                id: 'skripsi-bab3',
                label: 'Bab Metodologi',
                icon: Table2,
                blocks: [
                    { type: 'chapter', content: 'METODOLOGI PENELITIAN' },
                    { type: 'h1', content: 'Jenis Penelitian' },
                    { type: 'paragraph', content: PH('Jelaskan pendekatan dan jenis penelitian.') },
                    { type: 'h1', content: 'Teknik Pengumpulan Data' },
                    { type: 'paragraph', content: PH('Deskripsikan cara memperoleh data.') },
                ],
            },
            {
                id: 'skripsi-bab4',
                label: 'Bab Hasil & Pembahasan',
                icon: List,
                blocks: [
                    { type: 'chapter', content: 'HASIL DAN PEMBAHASAN' },
                    { type: 'h1', content: 'Hasil' },
                    { type: 'paragraph', content: PH('Paparkan temuan penelitian secara objektif.') },
                    { type: 'h1', content: 'Pembahasan' },
                    { type: 'paragraph', content: PH('Analisis hasil dan kaitkan dengan teori.') },
                ],
            },
            {
                id: 'skripsi-akhir',
                label: 'Bagian Akhir',
                icon: Library,
                blocks: [
                    { type: 'chapter', content: 'KESIMPULAN DAN SARAN' },
                    { type: 'h1', content: 'Kesimpulan' },
                    { type: 'paragraph', content: PH('Rangkum jawaban atas rumusan masalah.') },
                    { type: 'h1', content: 'Saran' },
                    { type: 'paragraph', content: PH('Berikan rekomendasi untuk penelitian lanjutan.') },
                    { type: 'references' },
                ],
            },
        ],
    },
    {
        id: 'tesis',
        label: 'Tesis',
        icon: GraduationCap,
        sections: [
            {
                id: 'tesis-awal',
                label: 'Bagian Awal',
                icon: ListTree,
                blocks: [
                    { type: 'cover' },
                    { type: 'abstract', content: PH('Ringkasan penelitian tesis dalam satu paragraf.') },
                    { type: 'toc' },
                ],
            },
            {
                id: 'tesis-bab1',
                label: 'Bab Pendahuluan',
                icon: BookOpen,
                blocks: [
                    { type: 'chapter', content: 'PENDAHULUAN' },
                    { type: 'h1', content: 'Latar Belakang' },
                    { type: 'paragraph', content: PH('Jelaskan latar belakang dan urgensi penelitian.') },
                    { type: 'h1', content: 'Rumusan Masalah' },
                    { type: 'paragraph', content: PH('Tuliskan rumusan masalah penelitian.') },
                    { type: 'h1', content: 'Tujuan dan Manfaat' },
                    { type: 'paragraph', content: PH('Sebutkan tujuan serta manfaat penelitian.') },
                ],
            },
            {
                id: 'tesis-bab2',
                label: 'Bab Kajian Pustaka',
                icon: BookMarked,
                blocks: [
                    { type: 'chapter', content: 'KAJIAN PUSTAKA' },
                    { type: 'h1', content: 'Landasan Teori' },
                    { type: 'paragraph', content: PH('Uraikan teori dan penelitian terdahulu.') },
                ],
            },
            {
                id: 'tesis-bab3',
                label: 'Bab Metodologi',
                icon: Table2,
                blocks: [
                    { type: 'chapter', content: 'METODOLOGI PENELITIAN' },
                    { type: 'h1', content: 'Desain Penelitian' },
                    { type: 'paragraph', content: PH('Jelaskan desain dan pendekatan penelitian.') },
                    { type: 'h1', content: 'Teknik Analisis Data' },
                    { type: 'paragraph', content: PH('Deskripsikan teknik analisis yang digunakan.') },
                ],
            },
            {
                id: 'tesis-akhir',
                label: 'Hasil, Kesimpulan & Pustaka',
                icon: Library,
                blocks: [
                    { type: 'chapter', content: 'HASIL DAN PEMBAHASAN' },
                    { type: 'h1', content: 'Hasil' },
                    { type: 'paragraph', content: PH('Paparkan hasil penelitian.') },
                    { type: 'chapter', content: 'KESIMPULAN DAN SARAN' },
                    { type: 'h1', content: 'Kesimpulan' },
                    { type: 'paragraph', content: PH('Simpulkan hasil penelitian.') },
                    { type: 'references' },
                ],
            },
        ],
    },
    {
        id: 'disertasi',
        label: 'Disertasi',
        icon: GraduationCap,
        sections: [
            {
                id: 'disertasi-awal',
                label: 'Bagian Awal',
                icon: ListTree,
                blocks: [
                    { type: 'cover' },
                    { type: 'abstract', content: PH('Ringkasan disertasi dalam satu paragraf.') },
                    { type: 'toc' },
                ],
            },
            {
                id: 'disertasi-bab1',
                label: 'Bab Pendahuluan',
                icon: BookOpen,
                blocks: [
                    { type: 'chapter', content: 'PENDAHULUAN' },
                    { type: 'h1', content: 'Latar Belakang' },
                    { type: 'paragraph', content: PH('Jelaskan latar belakang dan kebaruan penelitian.') },
                    { type: 'h1', content: 'Rumusan Masalah' },
                    { type: 'paragraph', content: PH('Tuliskan rumusan masalah penelitian.') },
                    { type: 'h1', content: 'Tujuan dan Kontribusi' },
                    { type: 'paragraph', content: PH('Sebutkan tujuan serta kontribusi keilmuan.') },
                ],
            },
            {
                id: 'disertasi-bab2',
                label: 'Bab Kajian Pustaka',
                icon: BookMarked,
                blocks: [
                    { type: 'chapter', content: 'KAJIAN PUSTAKA' },
                    { type: 'h1', content: 'Landasan Teori' },
                    { type: 'paragraph', content: PH('Uraikan teori dan penelitian terdahulu secara mendalam.') },
                ],
            },
            {
                id: 'disertasi-bab3',
                label: 'Bab Kerangka Konseptual',
                icon: List,
                blocks: [
                    { type: 'chapter', content: 'KERANGKA KONSEPTUAL' },
                    { type: 'h1', content: 'Kerangka Berpikir' },
                    { type: 'paragraph', content: PH('Jelaskan kerangka berpikir penelitian.') },
                ],
            },
            {
                id: 'disertasi-bab4',
                label: 'Bab Metodologi',
                icon: Table2,
                blocks: [
                    { type: 'chapter', content: 'METODOLOGI PENELITIAN' },
                    { type: 'h1', content: 'Desain Penelitian' },
                    { type: 'paragraph', content: PH('Jelaskan desain dan pendekatan penelitian.') },
                    { type: 'h1', content: 'Teknik Analisis Data' },
                    { type: 'paragraph', content: PH('Deskripsikan teknik analisis data.') },
                ],
            },
            {
                id: 'disertasi-akhir',
                label: 'Hasil, Kesimpulan & Pustaka',
                icon: Library,
                blocks: [
                    { type: 'chapter', content: 'HASIL DAN PEMBAHASAN' },
                    { type: 'h1', content: 'Hasil' },
                    { type: 'paragraph', content: PH('Paparkan hasil penelitian.') },
                    { type: 'chapter', content: 'KESIMPULAN DAN SARAN' },
                    { type: 'h1', content: 'Kesimpulan' },
                    { type: 'paragraph', content: PH('Simpulkan hasil dan kontribusi penelitian.') },
                    { type: 'references' },
                ],
            },
        ],
    },
    {
        id: 'makalah',
        label: 'Makalah',
        icon: FileText,
        sections: [
            {
                id: 'makalah-judul',
                label: 'Judul & Abstrak',
                icon: FileText,
                blocks: [
                    { type: 'cover' },
                    { type: 'abstract', content: PH('Ringkasan makalah dalam satu paragraf.') },
                ],
            },
            {
                id: 'makalah-pendahuluan',
                label: 'Pendahuluan',
                icon: BookOpen,
                blocks: [
                    { type: 'h1', content: 'Pendahuluan' },
                    { type: 'paragraph', content: PH('Latar belakang dan tujuan penulisan makalah.') },
                ],
            },
            {
                id: 'makalah-pembahasan',
                label: 'Pembahasan',
                icon: List,
                blocks: [
                    { type: 'h1', content: 'Pembahasan' },
                    { type: 'paragraph', content: PH('Uraikan isi dan analisis pembahasan.') },
                ],
            },
            {
                id: 'makalah-akhir',
                label: 'Kesimpulan & Pustaka',
                icon: Library,
                blocks: [
                    { type: 'h1', content: 'Kesimpulan' },
                    { type: 'paragraph', content: PH('Simpulkan isi makalah.') },
                    { type: 'references' },
                ],
            },
        ],
    },
    {
        id: 'jurnal',
        label: 'Jurnal',
        icon: Newspaper,
        sections: [
            {
                id: 'jurnal-abstrak',
                label: 'Abstrak & Kata Kunci',
                icon: FileText,
                blocks: [
                    { type: 'abstract', content: PH('Ringkasan artikel: tujuan, metode, hasil, kesimpulan.') },
                    { type: 'paragraph', content: PH('Kata Kunci: kata1, kata2, kata3') },
                ],
            },
            {
                id: 'jurnal-pendahuluan',
                label: 'Pendahuluan',
                icon: BookOpen,
                blocks: [
                    { type: 'h1', content: 'Pendahuluan' },
                    { type: 'paragraph', content: PH('Latar belakang, tujuan, dan kontribusi artikel.') },
                ],
            },
            {
                id: 'jurnal-metode',
                label: 'Metode',
                icon: Table2,
                blocks: [
                    { type: 'h1', content: 'Metode Penelitian' },
                    { type: 'paragraph', content: PH('Desain penelitian, sumber data, dan teknik analisis.') },
                ],
            },
            {
                id: 'jurnal-hasil',
                label: 'Hasil & Pembahasan',
                icon: List,
                blocks: [
                    { type: 'h1', content: 'Hasil dan Pembahasan' },
                    { type: 'paragraph', content: PH('Sajikan temuan utama dan interpretasinya.') },
                ],
            },
            {
                id: 'jurnal-akhir',
                label: 'Kesimpulan & Pustaka',
                icon: Library,
                blocks: [
                    { type: 'h1', content: 'Kesimpulan' },
                    { type: 'paragraph', content: PH('Tarik kesimpulan dan implikasi.') },
                    { type: 'references' },
                ],
            },
        ],
    },
    {
        id: 'laporan',
        label: 'Laporan',
        icon: ClipboardList,
        sections: [
            {
                id: 'laporan-judul',
                label: 'Halaman Judul & Ringkasan',
                icon: FileText,
                blocks: [
                    { type: 'cover' },
                    { type: 'abstract', content: PH('Ringkasan eksekutif laporan.') },
                    { type: 'toc' },
                ],
            },
            {
                id: 'laporan-pendahuluan',
                label: 'Pendahuluan',
                icon: BookOpen,
                blocks: [
                    { type: 'chapter', content: 'PENDAHULUAN' },
                    { type: 'h1', content: 'Latar Belakang' },
                    { type: 'paragraph', content: PH('Jelaskan latar belakang penyusunan laporan.') },
                    { type: 'h1', content: 'Tujuan' },
                    { type: 'paragraph', content: PH('Sebutkan tujuan laporan.') },
                ],
            },
            {
                id: 'laporan-isi',
                label: 'Isi Laporan',
                icon: List,
                blocks: [
                    { type: 'chapter', content: 'ISI LAPORAN' },
                    { type: 'h1', content: 'Hasil Kegiatan' },
                    { type: 'paragraph', content: PH('Uraikan hasil kegiatan secara rinci.') },
                ],
            },
            {
                id: 'laporan-akhir',
                label: 'Kesimpulan & Lampiran',
                icon: Library,
                blocks: [
                    { type: 'chapter', content: 'KESIMPULAN DAN SARAN' },
                    { type: 'h1', content: 'Kesimpulan' },
                    { type: 'paragraph', content: PH('Simpulkan hasil kegiatan.') },
                    { type: 'h1', content: 'Saran' },
                    { type: 'paragraph', content: PH('Berikan saran tindak lanjut.') },
                ],
            },
        ],
    },
    {
        id: 'proposal',
        label: 'Proposal',
        icon: Lightbulb,
        sections: [
            {
                id: 'proposal-pendahuluan',
                label: 'Pendahuluan',
                icon: BookOpen,
                blocks: [
                    { type: 'chapter', content: 'PENDAHULUAN' },
                    { type: 'h1', content: 'Latar Belakang' },
                    { type: 'paragraph', content: PH('Jelaskan alasan dan urgensi kegiatan.') },
                    { type: 'h1', content: 'Rumusan Masalah' },
                    { type: 'paragraph', content: PH('Tuliskan rumusan masalah.') },
                    { type: 'h1', content: 'Tujuan dan Manfaat' },
                    { type: 'paragraph', content: PH('Sebutkan tujuan dan manfaat kegiatan.') },
                ],
            },
            {
                id: 'proposal-tinjauan',
                label: 'Tinjauan Pustaka',
                icon: BookMarked,
                blocks: [
                    { type: 'chapter', content: 'TINJAUAN PUSTAKA' },
                    { type: 'h1', content: 'Landasan Teori' },
                    { type: 'paragraph', content: PH('Uraikan teori yang mendasari kegiatan.') },
                ],
            },
            {
                id: 'proposal-metode',
                label: 'Metode Pelaksanaan',
                icon: Table2,
                blocks: [
                    { type: 'chapter', content: 'METODE PELAKSANAAN' },
                    { type: 'h1', content: 'Tahapan Kegiatan' },
                    { type: 'paragraph', content: PH('Deskripsikan tahapan pelaksanaan.') },
                    { type: 'h1', content: 'Jadwal & Anggaran' },
                    { type: 'paragraph', content: PH('Cantumkan linimasa dan rencana anggaran.') },
                ],
            },
            {
                id: 'proposal-akhir',
                label: 'Penutup & Pustaka',
                icon: Library,
                blocks: [
                    { type: 'chapter', content: 'PENUTUP' },
                    { type: 'h1', content: 'Kesimpulan' },
                    { type: 'paragraph', content: PH('Simpulkan manfaat kegiatan.') },
                    { type: 'references' },
                ],
            },
        ],
    },
    {
        id: 'esai',
        label: 'Esai',
        icon: PenLine,
        sections: [
            {
                id: 'esai-pendahuluan',
                label: 'Pendahuluan',
                icon: BookOpen,
                blocks: [
                    { type: 'h1', content: 'Pendahuluan' },
                    { type: 'paragraph', content: PH('Perkenalkan topik dan tesis utama esai.') },
                ],
            },
            {
                id: 'esai-isi',
                label: 'Isi / Pembahasan',
                icon: List,
                blocks: [
                    { type: 'h1', content: 'Pembahasan' },
                    { type: 'paragraph', content: PH('Uraikan argumen dan bukti pendukung.') },
                ],
            },
            {
                id: 'esai-akhir',
                label: 'Kesimpulan & Pustaka',
                icon: Library,
                blocks: [
                    { type: 'h1', content: 'Kesimpulan' },
                    { type: 'paragraph', content: PH('Rangkum argumen dan tegaskan kembali tesis.') },
                    { type: 'references' },
                ],
            },
        ],
    },
];

// Instansiasi section menjadi blok canvas lengkap (dengan uid baru).
export function buildSectionBlocks(section) {
    return buildTemplateBlocks({ blocks: section.blocks });
}

// Cari section berdasarkan id-nya (lintas semua tipe dokumen).
export function findSection(id) {
    for (const doc of DOCUMENT_SECTIONS) {
        const found = doc.sections.find((s) => s.id === id);
        if (found) return found;
    }
    return null;
}
