<script setup>
import { computed, ref, nextTick, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    Heading,
    Heading1,
    Heading2,
    Heading3,
    Heading4,
    Heading5,
    Heading6,
    Sigma,
    Pilcrow,
    List,
    ListOrdered,
    Quote,
    Minus,
    BookOpen,
    Table2,
    Image as ImageIcon,
    AlignLeft,
    AlignCenter,
    AlignRight,
    AlignJustify,
    FileText,
    BookMarked,
    ListTree,
    Library,
    MoveVertical,
    FilePlus2,
    Code2,
} from 'lucide-vue-next';
import HeaderBuilder from './components/HeaderBuilder.vue';
import DownloadModal from './components/DownloadModal.vue';
import BlockPalette from './components/BlockPalette.vue';
import InspectorPanel from './components/InspectorPanel.vue';
import PageCanvas from './components/PageCanvas.vue';
import PrintView from './components/PrintView.vue';
import SetupModal from './components/SetupModal.vue';
import DeleteConfirmModal from './components/DeleteConfirmModal.vue';
import PreviewModal from './components/PreviewModal.vue';
import PlagiarismModal from './components/PlagiarismModal.vue';
import TurnitinModal from './components/TurnitinModal.vue';
import AiHistoryModal from './components/AiHistoryModal.vue';
import CitationBrowserModal from './components/CitationBrowserModal.vue';
import ContextMenus from './components/ContextMenus.vue';
import CodeBlockModal from './components/CodeBlockModal.vue';
import ImageFileManager from './components/ImageFileManager.vue';
import WorkspaceViewer from './components/WorkspaceViewer.vue';
import AgentCanvasModal from './components/AgentCanvasModal.vue';
import ShareModal from './components/ShareModal.vue';
import { listCustomFonts, addCustomFont, registerAllCustomFonts } from '../../utils/fontManager';
import { formatCitation, authorYearLabel, parseCSLItem, cslFormatter } from '../../utils/csl-formatter';
import { listReferences as listWorkspaceReferences } from '../../utils/workspaceLibrary';
import { PROJECT_CATEGORY_OPTIONS, DEFAULT_PROJECT_CATEGORY } from '../../utils/projectCategories';
import { touchProject } from '../../utils/projectIndex';
import { DOCUMENT_SECTIONS, buildSectionBlocks, findSection } from '../../utils/sections';
import { getJson, request, ensureCsrf } from '../../utils/http';
import { creditPricing, loadCreditPricing } from '../../utils/creditPricing';
import { buildTemplateBlocks } from '../../utils/templates';
import { renderMarkdown } from '../../utils/markdown';
import { toast } from '../../utils/toast';

const route = useRoute();
const router = useRouter();
const projectId = computed(() => route.query.builder || '');

// Mode "Tulisin Workspace": buka referensi dari Workspace (hasil PDF) hanya untuk
// dibaca, tanpa editor. Diaktifkan via ?builder=<id>&workspace=true&edit=false.
const workspaceView = computed(() => route.query.workspace === 'true');
const workspaceReference = computed(() => {
    if (!workspaceView.value) return null;
    const id = route.query.builder || '';
    return listWorkspaceReferences().find((r) => r.id === id) || null;
});

// Pastikan URL builder selalu memuat query tab & edit (mis. ?tab=t.0&edit=true).
// Jika tidak ada, redirect sekali agar URL konsisten.
function ensureBuilderQuery() {
    if (workspaceView.value) return;
    if (!route.query.builder) return;
    const q = { ...route.query };
    let changed = false;
    if (!q.tab) {
        q.tab = 't.0';
        changed = true;
    }
    if (q.edit === undefined) {
        q.edit = 'true';
        changed = true;
    }
    if (changed) router.replace({ query: q });
}

// Jenis blok konten yang bisa diseret ke halaman.
const blockGroups = [
    { id: 'component', label: 'Block Component' },
    { id: 'page', label: 'Page' },
];

const blockTypes = [
    { id: 'cover', label: 'Cover', icon: FileText, group: 'page' },
    { id: 'abstract', label: 'Abstract', icon: BookMarked, group: 'page' },
    { id: 'toc', label: 'Daftar Isi', icon: ListTree, group: 'page' },
    { id: 'listTables', label: 'Daftar Tabel', icon: Table2, group: 'page' },
    { id: 'listFigures', label: 'Daftar Gambar', icon: ImageIcon, group: 'page' },
    { id: 'references', label: 'Daftar Pustaka', icon: Library, group: 'page' },
    { id: 'blankPage', label: 'Blank Page', icon: FilePlus2, group: 'page' },
    { id: 'chapter', label: 'Per Bab (Judul Bab)', icon: BookOpen, group: 'page' },
    { id: 'h1', label: 'Heading 1', icon: Heading1, group: 'component' },
    { id: 'h2', label: 'Heading 2', icon: Heading2, group: 'component' },
    { id: 'h3', label: 'Heading 3', icon: Heading3, group: 'component' },
    { id: 'h4', label: 'Heading 4', icon: Heading4, group: 'component' },
    { id: 'h5', label: 'Heading 5', icon: Heading5, group: 'component' },
    { id: 'h6', label: 'Heading 6', icon: Heading6, group: 'component' },
    { id: 'h7', label: 'Heading 7', icon: Heading, group: 'component' },
    { id: 'h8', label: 'Heading 8', icon: Heading, group: 'component' },
    { id: 'h9', label: 'Heading 9', icon: Heading, group: 'component' },
    { id: 'h10', label: 'Heading 10', icon: Heading, group: 'component' },
    { id: 'formula', label: 'Rumus (Typst)', icon: Sigma, group: 'component' },
    { id: 'paragraph', label: 'Paragraf', icon: Pilcrow, group: 'component' },
    { id: 'bullet', label: 'List Poin', icon: List, group: 'component' },
    { id: 'number', label: 'List Nomor', icon: ListOrdered, group: 'component' },
    { id: 'quote', label: 'Kutipan', icon: Quote, group: 'component' },
    { id: 'code', label: 'Kode (Code)', icon: Code2, group: 'component' },
    { id: 'table', label: 'Tabel', icon: Table2, group: 'component' },
    { id: 'image', label: 'Gambar', icon: ImageIcon, group: 'component' },
    { id: 'divider', label: 'Pembatas', icon: Minus, group: 'component' },
    { id: 'spacer', label: 'Spacer (Jarak)', icon: MoveVertical, group: 'component' },
];

// Tipe heading (h1..h10) beserta level penomorannya (1..10).
const headingTypes = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7', 'h8', 'h9', 'h10'];

// Level heading: chapter = 0, h1 = 1, ..., h10 = 10. Non-heading = null.
function headingLevelOf(type) {
    if (type === 'chapter') return 0;
    const i = headingTypes.indexOf(type);
    return i >= 0 ? i + 1 : null;
}

// True untuk chapter dan h1..h10 (blok judul yang bernomor otomatis).
function isHeadingType(type) {
    return type === 'chapter' || headingTypes.includes(type);
}

const alignOptions = [
    { id: 'left', icon: AlignLeft },
    { id: 'center', icon: AlignCenter },
    { id: 'right', icon: AlignRight },
    { id: 'justify', icon: AlignJustify },
];

// Blok yang sudah diletakkan di halaman (sumber kebenaran flat).
const canvasBlocks = ref([]);

// Undo/redo untuk operasi struktural (insert/remove/move/page break).
const undoStack = ref([]);
const redoStack = ref([]);
const MAX_HISTORY = 100;
let lastContentEditUid = null;

function snapshotBlocks() {
    return JSON.parse(JSON.stringify(canvasBlocks.value));
}

function pushHistory() {
    undoStack.value.push(snapshotBlocks());
    if (undoStack.value.length > MAX_HISTORY) undoStack.value.shift();
    redoStack.value = [];
    lastContentEditUid = null;
}

function undo() {
    if (!undoStack.value.length) return;
    redoStack.value.push(snapshotBlocks());
    canvasBlocks.value = undoStack.value.pop();
    selectedUid.value = null;
}

function redo() {
    if (!redoStack.value.length) return;
    undoStack.value.push(snapshotBlocks());
    canvasBlocks.value = redoStack.value.pop();
    selectedUid.value = null;
}
// Blok konten nyata (tanpa pemecah halaman internal untuk duplikat).
const contentBlocks = computed(() => canvasBlocks.value.filter((b) => b.type !== 'pageBreak'));

// Daftar blok untuk Document Tabs, lengkap dengan level indent (grouping ala Word).
const documentTabs = computed(() => {
    let level = 0;
    return contentBlocks.value.map((b) => {
        const lvl = headingLevelOf(b.type);
        if (lvl !== null) level = lvl;
        return { ...b, level };
    });
});
const selectedUid = ref(null);
const dropIndex = ref(null);
const deleteConfirmOpen = ref(false);

const blocksOpen = ref(false);
const inspectorOpen = ref(false);
const inspectorTab = ref('toc');
const canvasEl = ref(null);

// Format font dokumen (mengikuti ketentuan kampus).
const fontChoice = ref('Times New Roman');
const customFont = ref('');
const pageFontSize = ref(12);
const pageLineHeight = ref(1.5);

// Format halaman & margin dokumen (A4/A5 + margin dalam cm).
const pageFormat = ref('A4');
const pageSizes = {
    A4: { widthMm: 210, heightMm: 297 },
    A5: { widthMm: 148, heightMm: 210 },
};
const pageMargins = ref({ top: 2.54, right: 2.54, bottom: 2.54, left: 2.54 });
const pageOrientation = ref('portrait'); // 'portrait' | 'landscape'

const blockSearch = ref('');

const baseFontOptions = [
    'Times New Roman', 'Arial', 'Calibri', 'Cambria', 'Garamond',
    'Georgia', 'Book Antiqua', 'Palatino Linotype', 'Tahoma', 'Verdana',
];

// Font custom (TTF/OTF/WOFF) yang diunggah pengguna, digabung ke daftar font.
const customFonts = ref(listCustomFonts());
const fontOptions = computed(() => [
    ...baseFontOptions,
    ...customFonts.value.map((f) => f.family),
]);
const fontFileInput = ref(null);

const pageFormatOptions = [
    { value: 'A4', label: 'A4 (210 × 297 mm)' },
    { value: 'A5', label: 'A5 (148 × 210 mm)' },
];

const pageOrientationOptions = [
    { value: 'portrait', label: 'Potret (Portrait)' },
    { value: 'landscape', label: 'Lanskap (Landscape)' },
];

const projectCategoryOptions = PROJECT_CATEGORY_OPTIONS;

const lineHeightOptions = [
    { value: 1, label: '1 (Tunggal)' },
    { value: 1.15, label: '1.15' },
    { value: 1.5, label: '1.5' },
    { value: 2, label: '2 (Ganda)' },
];

const pageNumberPositionOptions = [
    { value: 'none', label: 'Tanpa nomor' },
    { value: 'bottom-center', label: 'Bawah tengah' },
    { value: 'bottom-left', label: 'Bawah kiri' },
    { value: 'bottom-right', label: 'Bawah kanan' },
    { value: 'top-center', label: 'Atas tengah' },
    { value: 'top-left', label: 'Atas kiri' },
    { value: 'top-right', label: 'Atas kanan' },
];

const frontMatterStyleOptions = [
    { value: 'roman', label: 'Romawi (i, ii, iii)' },
    { value: 'decimal', label: 'Angka (1, 2, 3)' },
];

const bodyStyleOptions = [
    { value: 'decimal', label: 'Angka (1, 2, 3)' },
    { value: 'roman', label: 'Romawi (i, ii, iii)' },
];

const captionPositionOptions = [
    { value: 'above', label: 'Di atas' },
    { value: 'below', label: 'Di bawah' },
];

const fontSelectOptions = computed(() => [
    ...fontOptions.value,
    { value: '__custom__', label: 'Font Kustom...' },
]);

const blockLineHeightOptions = computed(() => [
    { value: 0, label: 'Default (ikuti dokumen)' },
    ...lineHeightOptions,
]);

// Project & modal setup.
const projectName = ref('');
const projectCategory = ref(DEFAULT_PROJECT_CATEGORY);
const setupOpen = ref(false);
const setupMode = ref('setup'); // 'setup' (pertama kali) | 'edit' (proyek sudah ada)
const draftName = ref('');
const draftCategory = ref(DEFAULT_PROJECT_CATEGORY);
const draftFormat = ref('A4');
const draftOrientation = ref('portrait');
const draftMargins = ref({ top: 2.54, right: 2.54, bottom: 2.54, left: 2.54 });

// Info header builder.
const lastEdited = ref(null); // timestamp ms, null = belum pernah diedit.
const totalCredits = ref(0); // TODO: ambil dari API backend.

const lastEditedLabel = computed(() => {
    if (!lastEdited.value) return 'Belum diedit';
    return new Date(lastEdited.value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const downloadOpen = ref(false);
// Sedang mengekspor dokumen (agar UI tidak membeku & mencegah klik ganda).
const exporting = ref(false);
// Modal Agent AI Canvas (header): membantu di dalam canvas, bukan pindah halaman.
const agentModalOpen = ref(false);

// ---- Blok kode (CodeBlock) ----
const codeModalOpen = ref(false);
const codeDraft = ref('');
const codeEditingUid = ref(null);

// ---- Image File Manager ----
const imageManagerOpen = ref(false);

// ---- Bagikan dokumen (public view) ----
const shareOpen = ref(false);
// Payload share sama dengan payload yang disimpan ke DB (termasuk data render
// pagination/numbering/caption agar halaman public tampil identik dengan preview).
const sharePayload = computed(() => projectPayload());

function openShare() {
    shareOpen.value = true;
}

// ---- Sitasi & Daftar Pustaka (dari Tulisin Workspace) ----
const citationStyle = ref('APA');

const citationStyleOptions = ['IEEE', 'APA', 'MLA', 'Harvard', 'Chicago']
    .map((s) => ({ value: s, label: s }));

// Referensi yang sudah disitasi (untuk Daftar Pustaka otomatis).
const citedReferences = ref([]);

// Referensi tersedia: dari Tulisin Workspace (hasil parsing PDF).
const allReferences = computed(() => listWorkspaceReferences());

const workspaceReferenceCount = computed(() => allReferences.value.length);

// Buka Tulisin Workspace untuk mengelola referensi (upload PDF).
function openWorkspace() {
    router.push('/apps/u/workspace');
}

// Catat referensi ke daftar sitasi dan hasilkan teks sitasi (in-text).
function citeReference(ref) {
    if (!citedReferences.value.some((r) => r.id === ref.id)) {
        citedReferences.value.push(ref);
    }
    const index = citedReferences.value.findIndex((r) => r.id === ref.id) + 1;
    return formatCitation(parseCSLItem(ref), citationStyle.value, index);
}

// Sisipkan sitasi langsung ke kursor pada blok teks yang sedang diedit.
function insertInlineCitation(ref) {
    const citation = citeReference(ref);
    const el = document.activeElement;
    if (el && el.isContentEditable) {
        document.execCommand('insertHTML', false, citation);
        el.dispatchEvent(new Event('input', { bubbles: true }));
    } else {
        const b = canvasBlocks.value.find((x) => x.uid === selectedUid.value);
        if (b) b.content = (b.content || '') + citation;
    }
}

// ---- Browser referensi (modal tabel untuk memilih sitasi) ----
const citationBrowserOpen = ref(false);
const citationSearch = ref('');

const filteredReferences = computed(() => {
    const q = citationSearch.value.trim().toLowerCase();
    const refs = allReferences.value;
    if (!q) return refs;
    return refs.filter((r) => {
        const hay = `${authorYearLabel(r)} ${r.title} ${r['container-title'] || ''}`.toLowerCase();
        return hay.includes(q);
    });
});

function citationPreview(ref) {
    const idx = allReferences.value.findIndex((x) => x.id === ref.id) + 1;
    return formatCitation(parseCSLItem(ref), citationStyle.value, idx);
}

function openCitationBrowser() {
    citationSearch.value = '';
    citationBrowserOpen.value = true;
}
function closeCitationBrowser() {
    citationBrowserOpen.value = false;
}
function selectReferenceFromBrowser(ref) {
    insertInlineCitation(ref);
    closeCitationBrowser();
}

const effectiveFontFamily = computed(() =>
    fontChoice.value === '__custom__'
        ? (customFont.value.trim() || 'Times New Roman')
        : fontChoice.value,
);

const pageStyle = computed(() => ({
    fontFamily: effectiveFontFamily.value,
    fontSize: `${pageFontSize.value}pt`,
    lineHeight: pageLineHeight.value,
}));

const mmToPx = (mm) => (Number(mm) || 0) * 96 / 25.4;
const cmToPx = (cm) => (Number(cm) || 0) * 96 / 2.54;

const currentPageSize = computed(() => {
    const base = pageSizes[pageFormat.value] || pageSizes.A4;
    // Orientasi lanskap menukar lebar & tinggi.
    if (pageOrientation.value === 'landscape') {
        return { widthMm: base.heightMm, heightMm: base.widthMm };
    }
    return base;
});

const pageDimensions = computed(() => ({
    width: `${currentPageSize.value.widthMm}mm`,
    height: `${currentPageSize.value.heightMm}mm`,
}));

// Aturan @page dinamis agar orientasi (portrait/landscape) ikut saat cetak.
const printPageRule = computed(() =>
    `@media print{@page{size:${pageDimensions.value.width} ${pageDimensions.value.height};margin:0}}`,
);

// Suntik aturan @page ke <head> lewat elemen <style> (tidak bisa memakai <style>
// di dalam template SFC). Diperbarui setiap orientasi/format berubah.
let printPageStyleEl = null;
watch(printPageRule, (rule) => {
    if (!printPageStyleEl) {
        printPageStyleEl = document.createElement('style');
        printPageStyleEl.id = 'print-page-rule';
        document.head.appendChild(printPageStyleEl);
    }
    printPageStyleEl.textContent = rule;
}, { immediate: true });

const pagePadding = computed(() => ({
    paddingTop: `${cmToPx(pageMargins.value.top)}px`,
    paddingRight: `${cmToPx(pageMargins.value.right)}px`,
    paddingBottom: `${cmToPx(pageMargins.value.bottom)}px`,
    paddingLeft: `${cmToPx(pageMargins.value.left)}px`,
}));

const pageBoxStyle = computed(() => ({
    ...pageDimensions.value,
    ...pagePadding.value,
    ...pageStyle.value,
    boxSizing: 'border-box',
    overflow: 'hidden',
}));

const mirrorStyle = computed(() => ({
    width: pageDimensions.value.width,
    ...pagePadding.value,
    boxSizing: 'border-box',
    fontFamily: effectiveFontFamily.value,
    fontSize: `${pageFontSize.value}pt`,
    lineHeight: pageLineHeight.value,
}));

// Tinggi area konten untuk batas pecah halaman (tinggi mm dikurangi margin cm).
const contentHeightPx = computed(() =>
    mmToPx(currentPageSize.value.heightMm)
    - cmToPx(pageMargins.value.top)
    - cmToPx(pageMargins.value.bottom),
);

// Tinggi penuh satu halaman (px) — dipakai untuk virtualisasi daftar halaman.
const pageHeightPx = computed(() => mmToPx(currentPageSize.value.heightMm));

const groupedBlockTypes = computed(() => {
    const q = blockSearch.value.trim().toLowerCase();
    return blockGroups
        .map((g) => ({
            ...g,
            types: blockTypes.filter((t) => {
                if (t.group !== g.id) return false;
                if (!q) return true;
                return t.label.toLowerCase().includes(q) || t.id.toLowerCase().includes(q);
            }),
        }))
        .filter((g) => g.types.length > 0);
});

const selectedBlock = computed(() => canvasBlocks.value.find((b) => b.uid === selectedUid.value) || null);

// Blok teks yang mendukung penyisipan sitasi inline.
const isTextBlock = computed(() => {
    const t = selectedBlock.value?.type;
    return ['paragraph', 'abstract', 'blankPage', 'quote', 'bullet', 'number'].includes(t) || isHeadingType(t);
});

// Judul/bab yang punya penomoran otomatis dan bisa diatur custom.
const isHeadingBlock = computed(() => {
    const t = selectedBlock.value?.type;
    return isHeadingType(t);
});

// Blok yang mendukung pengaturan spasi baris & warna teks per blok (semua blok
// yang merender teks, termasuk Daftar Isi/Tabel/Gambar/Pustaka dan Cover).
const canStyleText = computed(() => {
    const t = selectedBlock.value?.type;
    if (!t) return false;
    return isTextBlock.value || ['toc', 'listTables', 'listFigures', 'references', 'cover'].includes(t);
});

// Saat ada blok yang dipilih, buka panel kanan (pengaturan blok tampil menggantikan tab).
watch(selectedUid, (val) => {
    if (val) {
        inspectorOpen.value = true;
    }
});

// Penanda saat memuat data dari localStorage agar tidak memicu simpan/timestamp ulang.
let isLoading = false;

// Catat waktu edit terakhir & simpan otomatis saat isi canvas berubah.
watch(canvasBlocks, () => {
    if (isLoading) return;
    lastEdited.value = Date.now();
    scheduleSave();
}, { deep: true });

// Bab aktif (berdasarkan posisi blok terpilih, atau bab terakhir).
const currentChapter = computed(() => {
    let current = '';
    for (const b of canvasBlocks.value) {
        if (b.type === 'chapter') current = (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        if (b.uid === selectedUid.value) break;
    }
    return current;
});

const wordCount = computed(() => {
    const text = (selectedBlock.value?.content || '').replace(/<[^>]*>/g, ' ').trim();
    return text ? text.split(/\s+/).length : 0;
});

// ---- Penomoran otomatis (Bab, 1, 1.1, 1.1.1, dst.) ----
const numberingMap = computed(() => {
    const map = {};
    const counters = new Array(11).fill(0); // [0]=chapter, [1..10]=h1..h10
    const romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    for (const block of canvasBlocks.value) {
        if (block.type === 'chapter') {
            counters[0] += 1;
            for (let i = 1; i <= 10; i++) counters[i] = 0;
            map[block.uid] = block.customNumber || `BAB ${romans[counters[0] - 1] || counters[0]}`;
        } else {
            const lvl = headingLevelOf(block.type);
            if (lvl) {
                counters[lvl] += 1;
                for (let i = lvl + 1; i <= 10; i++) counters[i] = 0;
                const parts = [String(counters[0] || 1)];
                for (let i = 1; i <= lvl; i++) parts.push(String(counters[i]));
                map[block.uid] = block.customNumber || parts.join('.');
            }
        }
    }
    return map;
});

// ---- Nomor halaman ----
const pageNumberPosition = ref('bottom-center');
const frontMatterStyle = ref('roman');
const bodyStyle = ref('decimal');
const bodyStart = ref(1);
const pageSettingsOpen = ref(false);
const pageSettingsPos = ref({ x: 0, y: 0 });

// Simpan otomatis saat pengaturan dokumen berubah.
watch(
    [fontChoice, customFont, pageFontSize, pageLineHeight, pageFormat, pageOrientation, pageMargins, pageNumberPosition, frontMatterStyle, bodyStyle, bodyStart, citationStyle, citedReferences],
    () => {
        if (isLoading) return;
        scheduleSave();
    },
    { deep: true },
);
const pageMenu = ref({ open: false, x: 0, y: 0, pIndex: 0 });
const blockMenu = ref({ open: false, x: 0, y: 0, uid: null });

const pageNumberClass = computed(() => {
    switch (pageNumberPosition.value) {
        case 'bottom-left': return 'bottom-3 left-10';
        case 'bottom-right': return 'bottom-3 right-10';
        case 'top-center': return 'top-3 left-0 right-0 text-center';
        case 'top-left': return 'top-3 left-10';
        case 'top-right': return 'top-3 right-10';
        default: return 'bottom-3 left-0 right-0 text-center';
    }
});

const firstBodyPageIndex = computed(() => {
    for (let i = 0; i < pages.value.length; i++) {
        if (pages.value[i].some((b) => b.type === 'chapter')) return i;
    }
    return pages.value.length;
});

// Halaman cover tidak diberi nomor halaman.
function isCoverPage(pIndex) {
    const page = pages.value[pIndex];
    return !!page && page.some((b) => b.type === 'cover');
}

function toRoman(num) {
    const table = [[1000, 'm'], [900, 'cm'], [500, 'd'], [400, 'cd'], [100, 'c'], [90, 'xc'], [50, 'l'], [40, 'xl'], [10, 'x'], [9, 'ix'], [5, 'v'], [4, 'iv'], [1, 'i']];
    let result = '';
    for (const [v, s] of table) {
        while (num >= v) { result += s; num -= v; }
    }
    return result;
}

function pageNumberLabel(pIndex) {
    if (isCoverPage(pIndex)) return '';
    if (pIndex < firstBodyPageIndex.value) {
        // Halaman front-matter (abstrak, daftar isi, dll.) dihitung tanpa cover.
        let n = 0;
        for (let i = 0; i <= pIndex; i++) {
            if (!isCoverPage(i)) n++;
        }
        return frontMatterStyle.value === 'roman' ? toRoman(n) : String(n);
    }
    const n = pIndex - firstBodyPageIndex.value + bodyStart.value;
    return bodyStyle.value === 'roman' ? toRoman(n) : String(n);
}

function openPageSettings(e) {
    pageSettingsPos.value = {
        x: Math.min(e.clientX, window.innerWidth - 308),
        y: Math.min(e.clientY, window.innerHeight - 440),
    };
    pageSettingsOpen.value = true;
    closePageMenu();
    downloadOpen.value = false;
}

function closePageSettings() {
    pageSettingsOpen.value = false;
}

function openPageMenu(e, pIndex) {
    pageMenu.value = {
        open: true,
        x: Math.min(e.clientX, window.innerWidth - 220),
        y: Math.min(e.clientY, window.innerHeight - 130),
        pIndex,
    };
    closePageSettings();
    downloadOpen.value = false;
}

function closePageMenu() {
    pageMenu.value.open = false;
}

function openBlockMenu(e, uid) {
    selectedUid.value = uid;
    blockMenu.value = {
        open: true,
        x: Math.min(e.clientX, window.innerWidth - 220),
        y: Math.min(e.clientY, window.innerHeight - 130),
        uid,
    };
    closePageSettings();
    closePageMenu();
    downloadOpen.value = false;
}

function closeBlockMenu() {
    blockMenu.value.open = false;
}

const blockMenuBlock = computed(() => canvasBlocks.value.find((b) => b.uid === blockMenu.value.uid) || null);
const blockMenuTypeLabel = computed(() => (blockMenuBlock.value ? typeLabel(blockMenuBlock.value.type) : 'Block'));

function blockMenuDelete() {
    const uid = blockMenu.value.uid;
    closeBlockMenu();
    if (uid) removeBlockByUid(uid);
}

function blockMenuUploadImage() {
    const uid = blockMenu.value.uid;
    closeBlockMenu();
    selectedUid.value = uid;
    nextTick(() => triggerImageUpload());
}

function paraphraseBlock() {
    const uid = blockMenu.value.uid;
    closeBlockMenu();
    if (uid) openPlagiarismCheck(uid);
}

function deletePageFromMenu() {
    const pIndex = pageMenu.value.pIndex;
    closePageMenu();
    deletePage(pIndex);
}

function duplicatePage(pIndex) {
    const blocks = flatPageBlocks(pIndex);
    if (blocks.length === 0) return;
    const clones = blocks.map((b) => ({
        ...b,
        uid: crypto.randomUUID(),
        content: b.content ?? '',
    }));
    // Jika blok pertama halaman ini bukan pemecah halaman, sisipkan pemecah
    // halaman agar duplikatnya selalu menjadi halaman baru (bukan menyatu).
    const firstIsBreak = isPageBreakType(blocks[0].type);
    const insert = firstIsBreak
        ? clones
        : [{ type: 'pageBreak', uid: crypto.randomUUID(), content: '' }, ...clones];
    const insertAt = flatPageStart(pIndex) + flatPageBlockCount(pIndex);
    pushHistory();
    canvasBlocks.value.splice(insertAt, 0, ...insert);
}

function duplicateFromMenu() {
    const pIndex = pageMenu.value.pIndex;
    closePageMenu();
    duplicatePage(pIndex);
}

function openSettingsFromMenu() {
    const pos = { clientX: pageMenu.value.x, clientY: pageMenu.value.y };
    closePageMenu();
    openPageSettings(pos);
}

// ---- Pagination: pecah blok menjadi beberapa halaman ----
const blockHeights = {};
const measureEls = {};
const pages = ref([]);

// ---- Download PDF per bab / semua bab ----
// Scope cetak/PDF: 'all' (semua bab), 'front' (bagian depan), atau uid chapter.
const printScope = ref('all');

// Jenis "Bagian" dokumen: cover, abstract, daftar, blank page, dan bab.
const pageSectionTypes = ['cover', 'abstract', 'toc', 'listTables', 'listFigures', 'references', 'blankPage', 'chapter'];

// Rentang halaman untuk tiap blok (semua tipe) — untuk unduh per bagian/per blok.
const blockPageRanges = computed(() => {
    const map = {};
    pages.value.forEach((page, pIndex) => {
        for (const chunk of page || []) {
            if (!chunk.uid) continue;
            const r = map[chunk.uid] || (map[chunk.uid] = { start: pIndex, end: pIndex });
            r.start = Math.min(r.start, pIndex);
            r.end = Math.max(r.end, pIndex);
        }
    });
    return map;
});

// Map index halaman → uid bab (null untuk bagian depan sebelum bab pertama).
const chapterUidByPage = computed(() => {
    const map = [];
    let current = null;
    for (let i = 0; i < pages.value.length; i++) {
        for (const b of pages.value[i] || []) {
            if (b.type === 'chapter') current = b.uid;
        }
        map[i] = current;
    }
    return map;
});

// Indeks halaman yang masuk dalam sebuah "component" scope (bagian/bab/blok).
function pagesForComponentScope(uid) {
    const block = canvasBlocks.value.find((b) => b.uid === uid);
    // Bab mencakup seluruh halaman hingga bab berikutnya, bukan hanya halaman judulnya.
    if (block && block.type === 'chapter') {
        const set = [];
        for (let i = 0; i < pages.value.length; i++) {
            if (chapterUidByPage.value[i] === uid) set.push(i);
        }
        return set;
    }
    const r = blockPageRanges.value[uid];
    if (!r) return [];
    const set = [];
    for (let i = r.start; i <= r.end; i++) set.push(i);
    return set;
}

// Halaman yang cocok dengan scope tertentu (untuk cetak/download).
function pagesForScope(scope) {
    return pages.value
        .map((page, pIndex) => ({ page, pIndex }))
        .filter(({ pIndex }) => {
            if (!scope || scope === 'all') return true;
            if (scope === 'front') return chapterUidByPage.value[pIndex] === null;
            if (scope.startsWith('component:')) {
                return pagesForComponentScope(scope.slice('component:'.length)).includes(pIndex);
            }
            return chapterUidByPage.value[pIndex] === scope;
        });
}

// Blok dokumen (flat) yang termasuk dalam sebuah scope — untuk ekspor Word.
function blocksForScope(scope) {
    if (!scope || scope === 'all') return canvasBlocks.value;
    if (!scope.startsWith('component:')) return [];
    const uid = scope.slice('component:'.length);
    const idx = canvasBlocks.value.findIndex((b) => b.uid === uid);
    if (idx === -1) return [];
    const block = canvasBlocks.value[idx];
    if (block.type === 'chapter') {
        const result = [];
        for (let i = idx; i < canvasBlocks.value.length; i++) {
            const b = canvasBlocks.value[i];
            if (i > idx && b.type === 'chapter') break;
            result.push(b);
        }
        return result;
    }
    return [block];
}

// Halaman yang ikut dicetak/diunduh sesuai scope.
const printPages = computed(() => pagesForScope(printScope.value));

// Label bagian dokumen (cover/abstract/daftar/bab/blank page) untuk opsi download.
function downloadSectionLabel(b) {
    if (b.type === 'blankPage' && b.pageTitle) return b.pageTitle;
    if (b.type === 'chapter') {
        const num = numberingMap.value[b.uid] || '';
        const title = (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        return [num, title].filter(Boolean).join(' ').trim() || 'Bab';
    }
    return typeLabel(b.type);
}

// Label blok konten untuk opsi download.
function blockDownloadLabel(b) {
    const num = numberingMap.value[b.uid];
    const preview = blockPreview(b);
    if (num) return `${num} ${preview}`.trim();
    return preview || typeLabel(b.type);
}

// Format unduhan yang dipilih pengguna di modal: 'pdf' atau 'word'.
const downloadFormat = ref('pdf');

// Biaya unduh: dasar + tambahan per kelipatan 10 halaman (tarif dari admin).
function downloadCost(pages) {
    const p = Math.max(0, Number(pages) || 0);
    const base = Number(creditPricing.value.download_base) || 0;
    const per10 = Number(creditPricing.value.download_per_10_pages) || 0;
    return base + per10 * Math.floor(Math.max(0, p - 1) / 10);
}

// Jumlah halaman yang tercakup oleh sebuah scope (untuk menghitung biaya unduh).
function pageCountForScope(scope) {
    return pagesForScope(scope).length;
}

// Opsi scope download, dikelompokkan otomatis: Semua, Per Bagian, Per Blok.
const downloadScopes = computed(() => {
    const groups = [];

    groups.push({
        label: 'Semua Dokumen',
        items: [{
            id: 'all',
            label: 'Semua',
            scope: 'all',
            pages: pages.value.length,
            cost: downloadCost(pages.value.length),
        }],
    });

    const sections = canvasBlocks.value
        .filter((b) => pageSectionTypes.includes(b.type))
        .map((b) => {
            const scope = `component:${b.uid}`;
            const pages = pageCountForScope(scope);
            return {
                id: `section-${b.uid}`,
                label: downloadSectionLabel(b),
                scope,
                pages,
                cost: downloadCost(pages),
            };
        });
    if (sections.length) groups.push({ label: 'Per Bagian', items: sections });

    const blocks = canvasBlocks.value
        .filter((b) => !pageSectionTypes.includes(b.type) && b.type !== 'pageBreak')
        .map((b) => {
            const scope = `component:${b.uid}`;
            const pages = pageCountForScope(scope);
            return {
                id: `block-${b.uid}`,
                label: blockDownloadLabel(b),
                scope,
                pages,
                cost: downloadCost(pages),
            };
        });
    if (blocks.length) groups.push({ label: 'Per Blok', items: blocks });

    return groups;
});

function setMeasureRef(uid, el) {
    if (el) measureEls[uid] = el;
    else delete measureEls[uid];
}

// Referensi elemen canvas utama (dipakai untuk drag-drop, keyboard, scroll-to-block).
function setCanvasEl(el) {
    canvasEl.value = el;
}

function isPageBreakType(type) {
    return ['cover', 'abstract', 'toc', 'listTables', 'listFigures', 'references', 'blankPage', 'chapter', 'pageBreak'].includes(type);
}

function measureAndPaginate() {
    if (canvasBlocks.value.length === 0) {
        pages.value = [[]];
        return;
    }
    for (const uid of Object.keys(measureEls)) {
        const el = measureEls[uid];
        if (el) blockHeights[uid] = el.offsetHeight;
    }
    const result = [];
    let current = [];
    let acc = 0;
    for (const block of canvasBlocks.value) {
        const h = blockHeights[block.uid] || 0;
        const forceBreak = isPageBreakType(block.type);

        // Blok daftar (Daftar Isi/Tabel/Gambar/Pustaka serta list poin/nomor) dipecah
        // ke beberapa halaman agar selalu mengisi sisa ruang sebelum pindah. List
        // poin/nomor ikut mengisi sisa halaman (bukan langsung lompat); list "bagian"
        // hanya dipecah bila lebih tinggi dari satu halaman.
        const isItemList = block.type === 'bullet' || block.type === 'number';
        const needsSplit = splittableListTypes.includes(block.type) && (
            isItemList ? acc + h > contentHeightPx.value : h > contentHeightPx.value
        );
        if (needsSplit) {
            const total = isItemList
                ? countTopLevelListItems(block.content)
                : (listEntryCounts.value[block.type] || 0);
            if (total > 0) {
                const titleH = sectionListTypes.includes(block.type) ? listTitleHeight.value : 0;
                const entryH = Math.max(1, (h - titleH) / total);
                // Daftar "bagian" (Daftar Isi/Tabel/Gambar/Pustaka) selalu mulai halaman baru.
                if (!isItemList && current.length) {
                    result.push(current);
                    current = [];
                    acc = 0;
                }
                let start = 0;
                let idx = 0;
                while (start < total) {
                    const chunkTitle = idx === 0 ? titleH : 0;
                    let avail = contentHeightPx.value - acc - chunkTitle;
                    if (avail < entryH) {
                        // Tidak cukup ruang untuk satu entri; tutup halaman berjalan.
                        if (current.length) {
                            result.push(current);
                            current = [];
                            acc = 0;
                        }
                        avail = contentHeightPx.value - chunkTitle;
                    }
                    const cap = Math.max(1, Math.floor(avail / entryH));
                    const end = Math.min(total, start + cap);
                    current.push({
                        ...block,
                        chunkKey: `${block.uid}#${idx}`,
                        sliceStart: start,
                        sliceEnd: end,
                    });
                    acc += chunkTitle + entryH * (end - start);
                    start = end;
                    idx += 1;
                    // Masih ada sisa entri: akhiri halaman berjalan (dianggap penuh).
                    if (start < total) {
                        result.push(current);
                        current = [];
                        acc = 0;
                    }
                }
                continue;
            }
        }

        if (forceBreak && current.length) {
            result.push(current);
            current = [];
            acc = 0;
        } else if (current.length && acc + h > contentHeightPx.value) {
            result.push(current);
            current = [];
            acc = 0;
        }
        current.push(block);
        acc += h;
    }
    if (current.length) result.push(current);
    pages.value = result;
}

let refreshFrame = null;
function refreshPages() {
    if (refreshFrame) return;
    refreshFrame = requestAnimationFrame(() => {
        refreshFrame = null;
        measureAndPaginate();
    });
}

watch(canvasBlocks, refreshPages, { deep: true });
watch([effectiveFontFamily, pageFontSize, pageLineHeight, pageFormat, pageOrientation, pageMargins], refreshPages, { deep: true });

function onWindowResize() {
    refreshPages();
}

// Cek apakah kursor berada tepat di awal area edit (tidak ada teks sebelum kursor).
function isCaretAtStartOfEditable(editable) {
    const sel = window.getSelection();
    if (!sel || !sel.isCollapsed || sel.rangeCount === 0) return false;
    const caret = sel.getRangeAt(0);
    if (caret.startOffset !== 0) return false;
    const pre = document.createRange();
    pre.selectNodeContents(editable);
    pre.setEnd(caret.startContainer, caret.startOffset);
    return pre.toString().trim() === '';
}

// Cegah Tab memindahkan fokus keluar dari area edit di canvas.
function onGlobalKeydown(e) {
    const el = e.target;

    // Ctrl/Cmd + P → buka pratinjau dokumen, bukan dialog print browser.
    if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        openPreview();
        return;
    }

    // Ctrl/Cmd + Enter → buat halaman baru dan pindahkan blok yang sedang difokus ke sana.
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        const host = el && el.closest ? el.closest('[data-block-uid]') : null;
        const uid = host ? host.getAttribute('data-block-uid') : null;
        if (uid) {
            e.preventDefault();
            insertPageBreakBefore(uid);
        }
        return;
    }

    // Ctrl/Cmd + Z → undo; Ctrl/Cmd + Shift + Z atau Ctrl/Cmd + Y → redo.
    // Jangan ganggu undo bawaan di input/textarea/select (mis. kotak cari).
    if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
        const inFormField = el && el.closest && el.closest('input, textarea, select');
        if (inFormField) return;
        e.preventDefault();
        if (e.shiftKey) {
            redo();
        } else {
            undo();
        }
        return;
    }

    if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || e.key === 'Y')) {
        const inFormField = el && el.closest && el.closest('input, textarea, select');
        if (inFormField) return;
        e.preventDefault();
        redo();
        return;
    }

    if (e.key === 'Tab') {
        const inCanvas = canvasEl.value && canvasEl.value.contains(el);
        const editable = el && el.closest && el.closest('[contenteditable="true"]');
        if (inCanvas && editable) {
            e.preventDefault();
        }
        return;
    }

    // Backspace di awal blok yang tepat setelah pemecah halaman → hapus pemecah
    // halaman, sehingga blok kembali ke halaman sebelumnya (seperti Word).
    if (e.key === 'Backspace') {
        const editable = el && el.closest && el.closest('[contenteditable="true"]');
        if (editable) {
            const host = el.closest('[data-block-uid]');
            const uid = host ? host.getAttribute('data-block-uid') : null;
            if (uid && isCaretAtStartOfEditable(editable) && removePageBreakBefore(uid)) {
                e.preventDefault();
                return;
            }
            return;
        }
        const formField = el && el.closest && el.closest('input, textarea, select');
        if (formField) return;
        if (!selectedBlock.value) return;
        e.preventDefault();
        requestDeleteBlock();
    }

    // Hapus blok yang sedang dipilih dengan tombol Delete,
    // selama kita tidak sedang mengetik di dalam area edit.
    if (e.key === 'Delete') {
        const editing = el && el.closest && el.closest('[contenteditable="true"], input, textarea, select');
        if (editing) return;
        if (!selectedBlock.value) return;
        e.preventDefault();
        requestDeleteBlock();
    }
}

onMounted(async () => {
    if (workspaceView.value) return;
    ensureBuilderQuery();
    registerAllCustomFonts();
    refreshPages();
    loadCredits();
    loadCreditPricing();
    window.addEventListener('resize', onWindowResize);
    window.addEventListener('beforeunload', flushSave);
    document.addEventListener('keydown', onGlobalKeydown);

    // Database adalah sumber kebenaran. Muat dari server dulu; localStorage
    // hanya dipakai sebagai cadangan bila server tidak bisa dihubungi (offline).
    let loaded = await loadProjectFromServer();
    if (!loaded) {
        loaded = loadProjectSettings();
    }
    if (!loaded) {
        openSetup();
    }
});

onBeforeUnmount(() => {
    flushSave();
    window.removeEventListener('resize', onWindowResize);
    window.removeEventListener('beforeunload', flushSave);
    document.removeEventListener('keydown', onGlobalKeydown);
});

// Jumlah blok unik (flat) pada halaman-halaman sebelum pIndex.
function flatPageStart(pIndex) {
    const seen = new Set();
    for (let i = 0; i < pIndex; i++) {
        for (const chunk of pages.value[i] || []) seen.add(chunk.uid);
    }
    return seen.size;
}

// Jumlah blok unik (flat) pada halaman pIndex.
function flatPageBlockCount(pIndex) {
    const seen = new Set();
    for (const chunk of pages.value[pIndex] || []) seen.add(chunk.uid);
    return seen.size;
}

// Blok unik (flat) pada halaman pIndex, sesuai urutan kemunculan.
function flatPageBlocks(pIndex) {
    const seen = new Set();
    const blocks = [];
    for (const chunk of pages.value[pIndex] || []) {
        if (seen.has(chunk.uid)) continue;
        seen.add(chunk.uid);
        const block = canvasBlocks.value.find((b) => b.uid === chunk.uid);
        if (block) blocks.push(block);
    }
    return blocks;
}

// ---- Navigasi & informasi halaman (di bawah canvas) ----
const currentPage = ref(1); // 1-based
const pageJump = ref(1);
const pageCanvas = ref(null); // referensi komponen canvas (untuk virtual scroll)

watch(currentPage, (v) => { pageJump.value = v; });
watch(pages, () => {
    if (pages.value.length && currentPage.value > pages.value.length) {
        currentPage.value = Math.max(1, pages.value.length);
    }
});

// Bagian depan (front matter) yang menggunakan nomor halaman romawi
// dan tetap perlu muncul di Daftar Isi secara otomatis.
const frontMatterTitles = {
    abstract: 'ABSTRAK',
    toc: 'DAFTAR ISI',
    listTables: 'DAFTAR TABEL',
    listFigures: 'DAFTAR GAMBAR',
    references: 'DAFTAR PUSTAKA',
};

// Judul untuk entri Daftar Isi dari blok section (front matter / blank page).
function sectionTitleForToc(b) {
    if (b.type === 'blankPage') return (b.pageTitle || '').trim() || 'HALAMAN';
    return frontMatterTitles[b.type] || '';
}

// Entri Daftar Isi yang disembunyikan pengguna.
const hiddenTocUids = ref([]);

function toggleTocEntry(uid) {
    const i = hiddenTocUids.value.indexOf(uid);
    if (i >= 0) hiddenTocUids.value.splice(i, 1);
    else hiddenTocUids.value.push(uid);
    scheduleSave();
}

// Semua entri Daftar Isi (sebelum difilter yang disembunyikan).
const tocEntriesAll = computed(() => {
    const strip = (html) => (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return canvasBlocks.value
        .filter((b) => isHeadingType(b.type) || frontMatterTitles[b.type] || b.type === 'blankPage')
        .map((b) => {
            const pageIndex = pages.value.findIndex((p) => p.some((x) => x.uid === b.uid));
            const isFront = Boolean(frontMatterTitles[b.type]) || b.type === 'blankPage';
            return {
                uid: b.uid,
                type: b.type,
                level: isFront ? 0 : (headingLevelOf(b.type) ?? 0),
                number: isFront ? '' : (numberingMap.value[b.uid] || ''),
                text: isFront ? sectionTitleForToc(b) : strip(b.content),
                pageLabel: pageIndex >= 0 ? pageNumberLabel(pageIndex) : '',
                hidden: hiddenTocUids.value.includes(b.uid),
            };
        });
});

// Entri Daftar Isi yang tampil (tidak disembunyikan).
const tocEntries = computed(() => tocEntriesAll.value.filter((e) => !e.hidden));

// Peta entri Daftar Isi berdasarkan uid (untuk daftar struktur terpadu di panel "Isi").
const tocEntryByUid = computed(() => {
    const map = {};
    for (const e of tocEntriesAll.value) map[e.uid] = e;
    return map;
});

// Nomor caption tabel/gambar otomatis per bab, mis. 1.1, 1.2, 2.1.
const captionNumbers = computed(() => {
    const map = {};
    let chapterIndex = 0;
    let tableIndex = 0;
    let figureIndex = 0;
    for (const block of canvasBlocks.value) {
        if (block.type === 'chapter') {
            chapterIndex += 1;
            tableIndex = 0;
            figureIndex = 0;
        } else if (block.type === 'table') {
            tableIndex += 1;
            map[block.uid] = `${chapterIndex || 1}.${tableIndex}`;
        } else if (block.type === 'image') {
            figureIndex += 1;
            map[block.uid] = `${chapterIndex || 1}.${figureIndex}`;
        }
    }
    return map;
});

const tableEntries = computed(() => canvasBlocks.value
    .filter((b) => b.type === 'table' && b.showCaption !== false)
    .map((b) => {
        const pageIndex = pages.value.findIndex((p) => p.some((x) => x.uid === b.uid));
        return {
            uid: b.uid,
            number: captionNumbers.value[b.uid] || '',
            text: (b.caption || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(),
            pageLabel: pageIndex >= 0 ? pageNumberLabel(pageIndex) : '',
        };
    })
    .filter((e) => e.text),
);

const figureEntries = computed(() => canvasBlocks.value
    .filter((b) => b.type === 'image' && b.showCaption !== false)
    .map((b) => {
        const pageIndex = pages.value.findIndex((p) => p.some((x) => x.uid === b.uid));
        return {
            uid: b.uid,
            number: captionNumbers.value[b.uid] || '',
            text: (b.caption || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(),
            pageLabel: pageIndex >= 0 ? pageNumberLabel(pageIndex) : '',
        };
    })
    .filter((e) => e.text),
);

// Daftar Pustaka otomatis dari referensi yang sudah disitasi.
// Prefiks "[n]" (gaya IEEE) dibuang karena penomoran 1, 2, 3 ditampilkan terpisah.
const referenceEntries = computed(() =>
    cslFormatter(citedReferences.value, citationStyle.value, { mode: 'bibliography' })
        .map((text) => text.replace(/^\[\d+\]\s*/, '')),
);

// ---- Pecah blok daftar (Daftar Isi/Tabel/Gambar/Pustaka & list poin/nomor) menjadi beberapa halaman ----
const splittableListTypes = ['toc', 'listTables', 'listFigures', 'references', 'bullet', 'number'];
// Jenis daftar "bagian" yang punya judul (mis. "DAFTAR ISI") pada chunk pertama.
const sectionListTypes = ['toc', 'listTables', 'listFigures', 'references'];

// Hitung jumlah <li> level-atas pada list poin/nomor (dipakai untuk memecah list panjang).
function countTopLevelListItems(html) {
    if (!html) return 0;
    const doc = new DOMParser().parseFromString(html, 'text/html');
    let count = 0;
    for (const child of doc.body.children) {
        if (child.tagName === 'LI') count += 1;
    }
    return count;
}

// Estimasi tinggi judul bagian (mis. "DAFTAR ISI") untuk menghitung kapasitas halaman.
const listTitleHeight = computed(() => {
    const fontSizePx = pageFontSize.value * 96 / 72;
    return fontSizePx * 1.25 * pageLineHeight.value + 16;
});

// Jumlah entri per tipe blok daftar (dihitung tanpa bergantung pada `pages` agar tidak sirkular).
const listEntryCounts = computed(() => {
    const strip = (html) => (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return {
        toc: canvasBlocks.value.filter((b) => (isHeadingType(b.type) || frontMatterTitles[b.type] || b.type === 'blankPage') && !hiddenTocUids.value.includes(b.uid)).length,
        listTables: canvasBlocks.value.filter((b) => b.type === 'table' && b.showCaption !== false && strip(b.caption)).length,
        listFigures: canvasBlocks.value.filter((b) => b.type === 'image' && b.showCaption !== false && strip(b.caption)).length,
        references: referenceEntries.value.length,
    };
});

// ---- Manipulasi blok ----
function defaultContent(type) {
    if (type === 'table') {
        const cell = (text) => `<td style="border: 1px solid #d4d4d4; padding: 6px 8px;">${text}</td>`;
        const row = (cells) => `<tr>${cells.map(cell).join('')}</tr>`;
        return (
            '<table style="border-collapse: collapse;">' +
            row(['Kolom 1', 'Kolom 2', 'Kolom 3']) +
            row(['', '', '']) +
            row(['', '', '']) +
            '</table>'
        );
    }
    if (type === 'bullet' || type === 'number') return '<li><br></li>';
    if (type === 'formula') return 'x^2 + y^2 = r^2';
    if (type === 'code') return '// Tempel kode kamu di sini\nprint("Hello, World!");';
    return '';
}

// Alignment bawaan tiap jenis blok: judul/rumus di tengah, isi teks (prosa) rata
// kanan-kiri (justify), sisanya rata kiri. Tetap bisa diubah lewat tombol Penempatan.
function defaultAlign(type) {
    if (['chapter', 'cover', 'formula'].includes(type)) return 'center';
    if (['paragraph', 'quote', 'abstract', 'blankPage'].includes(type)) return 'justify';
    return 'left';
}

function insertBlock(type, index) {
    const block = {
        uid: crypto.randomUUID(),
        type: type.id,
        content: defaultContent(type.id),
        indent: 0,
        align: defaultAlign(type.id),
        width: 100,
        spacing: 24,
        fontFamily: '',
        fontSize: 0,
        lineHeight: 0,
        color: '',
        caption: '',
    captionPosition: type.id === 'table' ? 'above' : 'below',
    showCaption: true,
    customNumber: '',
    pageTitle: type.id === 'blankPage' ? 'HALAMAN' : '',
};
    pushHistory();
    canvasBlocks.value.splice(index, 0, block);
    selectedUid.value = block.uid;
}

// Sisipkan blok baru tepat setelah blok yang sedang dipilih.
// Jika tidak ada yang dipilih, sisipkan di akhir halaman yang sedang dilihat
// (agar tetap masuk ke bab yang sedang dibuka, bukan loncat ke akhir dokumen).
function insertAfterSelected(type) {
    let index;
    if (selectedUid.value) {
        index = canvasBlocks.value.findIndex((b) => b.uid === selectedUid.value) + 1;
    } else if (pages.value.length) {
        const pIndex = Math.min(pages.value.length - 1, Math.max(0, currentPage.value - 1));
        const page = pages.value[pIndex] || [];
        const last = page[page.length - 1];
        index = last ? canvasBlocks.value.findIndex((b) => b.uid === last.uid) + 1 : canvasBlocks.value.length;
    } else {
        index = canvasBlocks.value.length;
    }
    insertBlock(type, index);
}

// Sisipkan seluruh blok dari preset struktur dokumen (section) sekaligus.
// Posisi sama seperti insertAfterSelected: setelah blok terpilih, atau di akhir
// halaman yang sedang dilihat, atau di akhir canvas jika masih kosong.
function insertSection(sectionId) {
    const section = findSection(sectionId);
    if (!section) return;
    const blocks = buildSectionBlocks(section);
    if (!blocks.length) return;
    let index;
    if (selectedUid.value) {
        index = canvasBlocks.value.findIndex((b) => b.uid === selectedUid.value) + 1;
    } else if (pages.value.length) {
        const pIndex = Math.min(pages.value.length - 1, Math.max(0, currentPage.value - 1));
        const page = pages.value[pIndex] || [];
        const last = page[page.length - 1];
        index = last ? canvasBlocks.value.findIndex((b) => b.uid === last.uid) + 1 : canvasBlocks.value.length;
    } else {
        index = canvasBlocks.value.length;
    }
    pushHistory();
    canvasBlocks.value.splice(index, 0, ...blocks);
    selectedUid.value = blocks[0].uid;
}

// ---- Terapkan jawaban Agent AI ke canvas ----
// Ubah teks markdown dari agent menjadi blok canvas (heading/paragraf/list),
// lalu sisipkan setelah blok terpilih (atau akhir canvas bila kosong).
function stripInlineMarkdown(text) {
    return String(text)
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1$2')
        .replace(/`([^`]+)`/g, '$1');
}

function parseAgentToBlocks(text) {
    const lines = String(text || '').replace(/\r\n/g, '\n').split('\n');
    const blocks = [];
    let para = [];
    let list = null;

    const flushPara = () => {
        if (para.length) {
            blocks.push({ type: 'paragraph', content: renderMarkdown(para.join(' ')) });
            para = [];
        }
    };
    const flushList = () => {
        if (list && list.items.length) {
            blocks.push({ type: list.type, content: list.items.map((i) => `<li>${renderMarkdown(i)}</li>`).join('') });
            list = null;
        }
    };

    for (const raw of lines) {
        const line = raw.trim();
        if (!line) {
            flushPara();
            flushList();
            continue;
        }

        const heading = line.match(/^(#{1,6})\s+(.*)$/);
        if (heading) {
            flushPara();
            flushList();
            blocks.push({ type: `h${heading[1].length}`, content: stripInlineMarkdown(heading[2].trim()) });
            continue;
        }

        const bullet = line.match(/^[-*]\s+(.*)$/);
        if (bullet) {
            flushPara();
            if (!list || list.type !== 'bullet') {
                flushList();
                list = { type: 'bullet', items: [] };
            }
            list.items.push(bullet[1].trim());
            continue;
        }

        const number = line.match(/^\d+[.)]\s+(.*)$/);
        if (number) {
            flushPara();
            if (!list || list.type !== 'number') {
                flushList();
                list = { type: 'number', items: [] };
            }
            list.items.push(number[1].trim());
            continue;
        }

        flushList();
        para.push(line);
    }
    flushPara();
    flushList();

    return blocks;
}

function applyAgentToCanvas(text) {
    const specs = parseAgentToBlocks(text);
    if (!specs.length) return;
    const blocks = buildTemplateBlocks({ blocks: specs });
    let index;
    if (selectedUid.value) {
        index = canvasBlocks.value.findIndex((b) => b.uid === selectedUid.value) + 1;
    } else if (pages.value.length) {
        const pIndex = Math.min(pages.value.length - 1, Math.max(0, currentPage.value - 1));
        const page = pages.value[pIndex] || [];
        const last = page[page.length - 1];
        index = last ? canvasBlocks.value.findIndex((b) => b.uid === last.uid) + 1 : canvasBlocks.value.length;
    } else {
        index = canvasBlocks.value.length;
    }
    pushHistory();
    canvasBlocks.value.splice(index, 0, ...blocks);
    selectedUid.value = blocks[0].uid;
    showToast('Konten agent ditambahkan ke canvas.');
    agentModalOpen.value = false;
}

// Sisipkan pemecah halaman tepat sebelum blok, sehingga blok tersebut pindah ke halaman baru.
function insertPageBreakBefore(uid) {
    const index = canvasBlocks.value.findIndex((b) => b.uid === uid);
    if (index === -1) return;
    const prev = canvasBlocks.value[index - 1];
    if (prev && prev.type === 'pageBreak') return;
    pushHistory();
    canvasBlocks.value.splice(index, 0, { type: 'pageBreak', uid: crypto.randomUUID(), content: '' });
}

// Hapus pemecah halaman tepat sebelum blok (mengembalikan blok ke halaman sebelumnya).
// Mengembalikan true jika berhasil dihapus.
function removePageBreakBefore(uid) {
    const index = canvasBlocks.value.findIndex((b) => b.uid === uid);
    if (index <= 0) return false;
    const prev = canvasBlocks.value[index - 1];
    if (!prev || prev.type !== 'pageBreak') return false;
    pushHistory();
    canvasBlocks.value.splice(index - 1, 1);
    return true;
}

function updateContent(uid, content) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.content = content;
}

function updatePageTitle(uid, title) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.pageTitle = title;
}

function updateIndent(uid, indent) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.indent = Math.min(6, Math.max(0, indent));
}

function setFirstLineIndent(uid, value) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.firstLineIndent = Boolean(value);
}

function setAlign(uid, align) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.align = align;
}

function setColumns(uid, columns) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.columns = Math.min(3, Math.max(1, Number(columns) || 1));
}

function setWidth(uid, width) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.width = Math.min(100, Math.max(10, Number(width) || 100));
}

function setCaption(uid, caption) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.caption = caption;
}

function setCaptionPosition(uid, position) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.captionPosition = position === 'below' ? 'below' : 'above';
}

function setShowCaption(uid, value) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.showCaption = Boolean(value);
}

function setCustomNumber(uid, value) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.customNumber = String(value || '').trim();
}

function setSpacing(uid, spacing) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.spacing = Math.min(500, Math.max(0, Number(spacing) || 0));
}

function setBlockFont(uid, fontFamily) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.fontFamily = fontFamily || '';
}

function setBlockFontSize(uid, size) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.fontSize = Math.min(72, Math.max(0, Number(size) || 0));
}

function setBlockLineHeight(uid, value) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.lineHeight = Number(value) > 0 ? Math.min(3, Math.max(0.5, Number(value))) : 0;
}

function setTextColor(uid, color) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (b) b.color = color || '';
}

function hasType(dataTransfer, type) {
    return Array.from(dataTransfer.types || []).includes(type);
}

function onPaletteDragStart(e, type) {
    e.dataTransfer.effectAllowed = 'copy';
    e.dataTransfer.setData('application/x-palette-block', type.id);
}

function onBlockDragStart(e, uid) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('application/x-canvas-block', uid);
}

function moveBlock(uid, targetIndex) {
    const from = canvasBlocks.value.findIndex((b) => b.uid === uid);
    if (from === -1) return;
    pushHistory();
    const [block] = canvasBlocks.value.splice(from, 1);
    const to = from < targetIndex ? targetIndex - 1 : targetIndex;
    canvasBlocks.value.splice(to, 0, block);
}

// Geser blok naik/turun satu posisi di dalam canvas.
function moveBlockBy(uid, delta) {
    const from = canvasBlocks.value.findIndex((b) => b.uid === uid);
    if (from === -1) return;
    const to = from + delta;
    if (to < 0 || to >= canvasBlocks.value.length) return;
    pushHistory();
    const [block] = canvasBlocks.value.splice(from, 1);
    canvasBlocks.value.splice(to, 0, block);
}

// Pemetaan uid -> indeks flat di canvasBlocks (dipakai untuk menempatkan indikator drop).
const uidToFlatIndex = computed(() => {
    const m = new Map();
    canvasBlocks.value.forEach((b, i) => m.set(b.uid, i));
    return m;
});

// Tampilkan indikator drop tepat sebelum chunk pertama blok pada indeks flat dropIndex.
// Tidak dipakai untuk chunk lanjutan blok daftar (tidak bisa disisip di tengah blok).
function isDropIndicatorBefore(block) {
    if (dropIndex.value == null) return false;
    if (uidToFlatIndex.value.get(block.uid) !== dropIndex.value) return false;
    return block.sliceStart == null || block.sliceStart === 0;
}

function computeDropIndex(clientY) {
    const nodes = canvasEl.value
        ? Array.from(canvasEl.value.querySelectorAll('[data-block-uid]'))
            .filter((n) => !n.closest('[aria-hidden="true"]'))
        : [];
    let lastVisibleIndex = -1;
    for (const node of nodes) {
        const rect = node.getBoundingClientRect();
        const uid = node.getAttribute('data-block-uid');
        const idx = canvasBlocks.value.findIndex((b) => b.uid === uid);
        if (idx > lastVisibleIndex) lastVisibleIndex = idx;
        if (clientY < rect.top + rect.height / 2) {
            return idx === -1 ? canvasBlocks.value.length : idx;
        }
    }
    // Virtualisasi: blok di luar viewport tidak ada di DOM. Bila kursor berada di
    // bawah blok terakhir yang terlihat, sisipkan tepat setelahnya (bukan loncat
    // ke akhir dokumen).
    return lastVisibleIndex === -1 ? 0 : lastVisibleIndex + 1;
}

function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = hasType(e.dataTransfer, 'application/x-palette-block') ? 'copy' : 'move';
    dropIndex.value = computeDropIndex(e.clientY);
}

function onDrop(e) {
    e.preventDefault();
    const index = computeDropIndex(e.clientY);
    const paletteId = e.dataTransfer.getData('application/x-palette-block');
    const movingUid = e.dataTransfer.getData('application/x-canvas-block');

    if (paletteId) {
        const type = blockTypes.find((t) => t.id === paletteId);
        if (type) insertBlock(type, index);
    } else if (movingUid) {
        moveBlock(movingUid, index);
    }
    dropIndex.value = null;
}

function onDragEnd() {
    dropIndex.value = null;
}

function typeLabel(id) {
    return blockTypes.find((t) => t.id === id)?.label || id;
}

function typeIcon(id) {
    return blockTypes.find((t) => t.id === id)?.icon || null;
}

function blockPreview(b) {
    if (b.type === 'blankPage') return 'Halaman baru';
    if (b.type === 'spacer') return `${b.spacing || 24}px`;
    if (b.type === 'divider') return '—';
    if (b.type === 'code') {
        const line = (b.content || '').trim().split('\n')[0] || '';
        return line ? line.slice(0, 24) : '(kosong)';
    }
    const text = (b.content || b.caption || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return text ? text.slice(0, 24) : '(kosong)';
}

// ---- Kredit (saldo & pemakaian) ----
async function loadCredits() {
    try {
        const data = await getJson('/api/wallet');
        totalCredits.value = data.balance || 0;
    } catch {
        // Biarkan 0 bila gagal memuat saldo.
    }
}

// Potong saldo kredit untuk suatu fitur. Return true bila berhasil.
async function spendCredits(amount, reason) {
    try {
        const res = await request('/api/wallet/spend', {
            method: 'POST',
            body: JSON.stringify({ credits: amount, reason }),
        });
        if (res.ok) {
            totalCredits.value = res.data.balance ?? (totalCredits.value - amount);
            return true;
        }
        showToast(res.data?.error || 'Saldo koin tidak mencukupi.');
        return false;
    } catch {
        showToast('Gagal memotong koin. Coba lagi.');
        return false;
    }
}

// ---- AI (asisten umum + generate per blok) ----
// Ringkasan isi canvas untuk dikirim ke backend AI.
const canvasSummary = computed(() => {
    const total = contentBlocks.value.length;
    if (total === 0) return 'Canvas masih kosong.';
    return contentBlocks.value
        .map((b, i) => `${i + 1}. [${typeLabel(b.type)}] ${blockPreview(b) || '(kosong)'}`)
        .join('\n');
});

// Agent AI Canvas: kondisi kosong + daftar komponen blok (sidebar kiri).
const agentEmpty = computed(() => contentBlocks.value.length === 0);
const agentBlockTypes = computed(() => blockTypes.map(({ id, label }) => ({ id, label })));

function openAgent() {
    closePageSettings();
    closePageMenu();
    closeBlockMenu();
    agentModalOpen.value = true;
}

const aiGenInput = ref('');
const aiGenOutput = ref('');
const aiGenLoading = ref(false);

const blockAiPrompts = computed(() => {
    const t = selectedBlock.value?.type;
    if (isHeadingType(t)) {
        return ['Tuliskan poin penting untuk bagian ini', 'Kembangkan judul menjadi paragraf pengantar'];
    }
    if (t === 'paragraph') {
        return ['Tambahkan paragraf di bab 2 di bagian ini', 'Perbaiki tata bahasa paragraf ini'];
    }
    if (t === 'abstract') return ['Tulis abstrak 200 kata'];
    if (t === 'quote') return ['Buatkan kutipan singkat terkait topik'];
    return ['Tolong Tambahkan paragraf di bab 2 di bagian blablablaa'];
});

async function generateBlockContent() {
    const prompt = aiGenInput.value.trim();
    if (!prompt) return;
    if (!(await spendCredits(creditPricing.value.ai_generate, 'ai_generate'))) return;
    aiGenLoading.value = true;
    try {
        const res = await request('/api/ai/generate', {
            method: 'POST',
            body: JSON.stringify({
                agent: 'copilot',
                message: prompt,
                context: canvasSummary.value,
                uuid: projectId.value,
            }),
        });
        aiGenOutput.value = res.ok
            ? (res.data?.reply || '')
            : (res.data?.error || 'Gagal menghubungi AI.');
    } catch {
        aiGenOutput.value = 'Gagal menghubungi AI. Coba lagi.';
    } finally {
        aiGenLoading.value = false;
    }
}

function insertGeneratedContent() {
    if (!selectedBlock.value || !aiGenOutput.value) return;
    selectedBlock.value.content = ((selectedBlock.value.content || '') + aiGenOutput.value).trim();
    aiGenOutput.value = '';
    aiGenInput.value = '';
}

// ---- Pratinjau dokumen (read-only) & zoom ----
const previewOpen = ref(false);

function openPreview() {
    previewOpen.value = true;
}
function closePreview() {
    previewOpen.value = false;
}

// Cetak dokumen canvas (hanya isi halaman, bukan UI builder).
function printDocument() {
    printScope.value = 'all';
    window.print();
}

// ---- Screening plagiarism (target Turnitin < 20%) ----
const plagiarismOpen = ref(false);
const plagiarismLoading = ref(false);
const plagiarismResult = ref(null);

// Tipe blok yang tidak punya teks tulis user (dilewati saat mengumpulkan teks).
const NON_TEXT_BLOCK_TYPES = new Set([
    'image', 'spacer', 'divider', 'pageBreak', 'formula', 'table',
    'toc', 'listTables', 'listFigures', 'references',
]);

function blockPlainText(b) {
    return (b?.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

async function openPlagiarismCheck(blockUid = null) {
    closePageMenu();

    const source = blockUid
        ? canvasBlocks.value.filter((b) => b.uid === blockUid)
        : canvasBlocks.value;
    const textBlocks = source.filter(
        (b) => !NON_TEXT_BLOCK_TYPES.has(b.type) && blockPlainText(b),
    );

    if (!textBlocks.length) {
        showToast('Tidak ada teks untuk diperiksa.');
        return;
    }

    plagiarismOpen.value = true;
    plagiarismLoading.value = true;
    plagiarismResult.value = null;

    const text = textBlocks
        .map((b) => (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim())
        .join('\n\n');

    try {
        const res = await request('/api/ai/generate', {
            method: 'POST',
            body: JSON.stringify({
                agent: 'plagiarism',
                message: 'Cek kemiripan teks berikut dan berikan saran parafrase agar di bawah 20%.',
                context: text || 'Tidak ada teks.',
                uuid: projectId.value,
            }),
        });

        let parsed = null;
        if (res.ok && typeof res.data?.reply === 'string') {
            try { parsed = JSON.parse(res.data.reply); } catch { parsed = null; }
        }

        if (!res.ok) {
            showToast(res.data?.error || 'Gagal menghubungi AI.');
            plagiarismResult.value = { similarity: 0, matches: [], sources: [] };
        } else if (parsed && Array.isArray(parsed.matches)) {
            const matches = parsed.matches.map((m) => {
                const original = (m.original || '').trim();
                const block = original
                    ? textBlocks.find((b) =>
                        (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().includes(original),
                    )
                    : null;
                return {
                    blockUid: block ? block.uid : null,
                    blockLabel: block ? typeLabel(block.type) : 'Teks terdeteksi',
                    matched: original,
                    similarity: Number(parsed.similarity) || 0,
                    source: 'Deteksi AI',
                    suggestion: (m.suggestion || '').trim(),
                    applied: false,
                };
            }).filter((m) => m.suggestion && m.suggestion !== m.matched);
            plagiarismResult.value = {
                similarity: Number(parsed.similarity) || 0,
                _baseSimilarity: Number(parsed.similarity) || 0,
                matches,
                sources: matches.map((m) => ({ title: m.source, match: m.similarity })),
            };
            saveAiResult('plagiarism', Number(parsed.similarity) || 0, matches);
        } else {
            plagiarismResult.value = { similarity: 0, matches: [], sources: [] };
            showToast('AI belum mengembalikan hasil. Coba lagi.');
        }
    } catch {
        showToast('Gagal menghubungi AI. Coba lagi.');
        plagiarismResult.value = { similarity: 0, matches: [], sources: [] };
    } finally {
        plagiarismLoading.value = false;
    }
}

function gotoPlagiarismMatch(match) {
    if (!match.blockUid) return;
    closePlagiarism();
    nextTick(() => scrollToBlock(match.blockUid));
}

// Turunkan skor kemiripan secara proporsional tiap saran diterapkan,
// agar setiap aksi terasa konsisten (skor turun, bukan naik).
function recalcSimilarity(result) {
    if (!result || !Array.isArray(result.matches) || result.matches.length === 0) return;
    const base = result._baseSimilarity ?? result.similarity;
    result._baseSimilarity = base;
    const applied = result.matches.filter((m) => m.applied).length;
    const total = result.matches.length;
    result.similarity = Math.max(0, Math.round(base * (1 - applied / total)));
}

async function applyPlagiarismFix(match) {
    if (!match || match.applied || match.rejected) return;
    // Setiap parafrase yang diterapkan memotong 1 koin.
    if (!(await spendCredits(creditPricing.value.ai_plagiarism, 'plagiarism_paraphrase'))) return;
    const b = canvasBlocks.value.find((x) => x.uid === match.blockUid);
    if (!b) return;
    const plain = (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    b.content = plain.includes(match.matched) ? plain.replace(match.matched, match.suggestion) : match.suggestion;
    match.applied = true;
    recalcSimilarity(plagiarismResult.value);
}

// Pertahankan teks asli (abaikan saran parafrase untuk blok ini).
function keepPlagiarismMatch(match) {
    match.rejected = true;
}

function closePlagiarism() {
    plagiarismOpen.value = false;
    plagiarismLoading.value = false;
    plagiarismResult.value = null;
}

// ---- Turnitin AI Optimizer ----
const turnitinOpen = ref(false);
const turnitinLoading = ref(false);
const turnitinResult = ref(null);

async function openTurnitinOptimizer() {
    closePageMenu();

    const textBlocks = canvasBlocks.value.filter(
        (b) => !NON_TEXT_BLOCK_TYPES.has(b.type) && blockPlainText(b),
    );

    if (!textBlocks.length) {
        showToast('Tidak ada teks untuk dioptimasi.');
        return;
    }

    if (!(await spendCredits(creditPricing.value.ai_turnitin, 'turnitin_optimize'))) return;
    turnitinOpen.value = true;
    turnitinLoading.value = true;
    turnitinResult.value = null;

    const text = textBlocks
        .map((b) => blockPlainText(b))
        .join('\n\n');

    try {
        const res = await request('/api/ai/generate', {
            method: 'POST',
            body: JSON.stringify({
                agent: 'turnitin',
                message: 'Periksa kemiripan teks ini dengan sumber lain, lalu tulis ulang kalimat yang mirip agar skor kemiripan turun.',
                context: text || 'Tidak ada teks.',
                uuid: projectId.value,
            }),
        });

        let parsed = null;
        if (res.ok && typeof res.data?.reply === 'string') {
            try { parsed = JSON.parse(res.data.reply); } catch { parsed = null; }
        }

        if (!res.ok) {
            showToast(res.data?.error || 'Gagal menghubungi AI.');
            turnitinResult.value = { similarity: 0, matches: [] };
        } else if (parsed && Array.isArray(parsed.matches)) {
            const matches = parsed.matches.map((m) => {
                const original = (m.original || '').trim();
                const block = original
                    ? textBlocks.find((b) =>
                        (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().includes(original),
                    )
                    : null;
                return {
                    blockUid: block ? block.uid : null,
                    blockLabel: block ? typeLabel(block.type) : 'Teks terdeteksi',
                    matched: original,
                    similarity: Number(parsed.similarity) || 0,
                    suggestion: m.suggestion || '',
                    applied: false,
                    rejected: false,
                };
            });
            turnitinResult.value = {
                similarity: Number(parsed.similarity) || 0,
                _baseSimilarity: Number(parsed.similarity) || 0,
                matches,
            };
            saveAiResult('turnitin', Number(parsed.similarity) || 0, matches);
        } else {
            turnitinResult.value = { similarity: 0, matches: [] };
            showToast('AI belum mengembalikan hasil. Coba lagi.');
        }
    } catch {
        showToast('Gagal menghubungi AI. Coba lagi.');
        turnitinResult.value = { similarity: 0, matches: [] };
    } finally {
        turnitinLoading.value = false;
    }
}

function gotoTurnitinMatch(match) {
    if (!match.blockUid) return;
    closeTurnitin();
    nextTick(() => scrollToBlock(match.blockUid));
}

function applyTurnitinFix(match) {
    const b = canvasBlocks.value.find((x) => x.uid === match.blockUid);
    if (!b) return;
    const plain = (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    b.content = plain.includes(match.matched) ? plain.replace(match.matched, match.suggestion) : match.suggestion;
    match.applied = true;
    recalcSimilarity(turnitinResult.value);
}

function keepTurnitinMatch(match) {
    match.rejected = true;
}

function closeTurnitin() {
    turnitinOpen.value = false;
    turnitinLoading.value = false;
    turnitinResult.value = null;
}

// ---- Riwayat hasil AI (turnitin/plagiarism) ----
const aiHistoryOpen = ref(false);
const aiHistoryLoading = ref(false);
const aiHistoryList = ref([]);

// Simpan hasil scan agar bisa dibuka kembali sebagai laporan & media belajar.
async function saveAiResult(type, score, matches) {
    if (!projectId.value) return;
    try {
        await request(`/api/projects/${encodeURIComponent(projectId.value)}/ai-results`, {
            method: 'POST',
            body: JSON.stringify({ type, score, matches }),
        });
    } catch {
        // Non-blocking: gagal simpan riwayat tidak boleh menghentikan alur utama.
    }
}

async function openAiHistory() {
    closePageMenu();
    aiHistoryOpen.value = true;
    aiHistoryLoading.value = true;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(projectId.value)}/ai-results`, {
            method: 'GET',
        });
        aiHistoryList.value = res.ok ? (res.data?.results || []) : [];
    } catch {
        aiHistoryList.value = [];
    } finally {
        aiHistoryLoading.value = false;
    }
}

// Kembalikan teks blok ke versi sebelum saran AI diterapkan.
function applyHistoryRevert(match) {
    const b = canvasBlocks.value.find((x) => x.uid === match.blockUid);
    if (!b) return;
    const plain = (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    if (plain.includes(match.suggestion)) {
        b.content = plain.replace(match.suggestion, match.matched);
    } else {
        b.content = match.matched;
    }
    showToast('Teks dikembalikan ke aslinya.');
}

async function deleteAiResult(entry) {
    if (!entry?.id || !projectId.value) return;
    try {
        await request(`/api/projects/${encodeURIComponent(projectId.value)}/ai-results/${entry.id}`, {
            method: 'DELETE',
        });
        aiHistoryList.value = aiHistoryList.value.filter((x) => x.id !== entry.id);
    } catch {
        showToast('Gagal menghapus riwayat.');
    }
}

function deselectBlock() {
    selectedUid.value = null;
}

function blockHasContent(b) {
    if (!b) return false;
    if (b.type === 'spacer' || b.type === 'divider') return false;
    if (b.type === 'image') return Boolean(b.content && String(b.content).trim());
    const text = (b.content || b.caption || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return Boolean(text);
}

function requestDeleteBlock() {
    if (!selectedBlock.value) return;
    if (blockHasContent(selectedBlock.value)) {
        deleteConfirmOpen.value = true;
    } else {
        removeBlock();
    }
}

function confirmDeleteBlock() {
    deleteConfirmOpen.value = false;
    removeBlock();
}

function cancelDeleteBlock() {
    deleteConfirmOpen.value = false;
}

function removeBlock() {
    if (!selectedBlock.value) return;
    const index = canvasBlocks.value.findIndex((b) => b.uid === selectedUid.value);
    if (index !== -1) {
        pushHistory();
        canvasBlocks.value.splice(index, 1);
    }
    selectedUid.value = null;
}

function removeBlockByUid(uid) {
    const index = canvasBlocks.value.findIndex((b) => b.uid === uid);
    if (index !== -1) {
        pushHistory();
        canvasBlocks.value.splice(index, 1);
    }
    if (selectedUid.value === uid) selectedUid.value = null;
}

function scrollToBlock(uid) {
    // Dengan virtualisasi, blok hanya ada di DOM bila halamannya dirender.
    // Cari halaman pemuat blok, gulir ke halaman itu, lalu gulir ke bloknya.
    let pIndex = -1;
    for (let i = 0; i < pages.value.length; i++) {
        if (pages.value[i].some((b) => b.uid === uid)) {
            pIndex = i;
            break;
        }
    }
    if (pIndex === -1) return;
    selectedUid.value = uid;
    pageCanvas.value?.scrollToPage(pIndex + 1, false);
    nextTick(() => {
        const nodes = canvasEl.value
            ? Array.from(canvasEl.value.querySelectorAll(`[data-block-uid="${uid}"]`))
            : [];
        const el = nodes[nodes.length - 1];
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

function deletePage(pIndex) {
    const start = flatPageStart(pIndex);
    const count = flatPageBlockCount(pIndex);
    if (count === 0) return;
    pushHistory();
    canvasBlocks.value.splice(start, count);
    selectedUid.value = null;
}

function movePage(pIndex, dir) {
    const target = pIndex + dir;
    if (target < 0 || target >= pages.value.length) return;

    // Susun ulang urutan halaman.
    const order = pages.value.map((_, i) => i);
    const [moved] = order.splice(pIndex, 1);
    order.splice(dir < 0 ? target : target + 1, 0, moved);

    // Bangun ulang urutan blok flat mengikuti urutan halaman baru.
    // Dedup uid agar blok daftar yang terpecah tidak menggandakan diri.
    const seen = new Set();
    const newOrder = [];
    for (const p of order) {
        for (const chunk of pages.value[p] || []) {
            if (seen.has(chunk.uid)) continue;
            seen.add(chunk.uid);
            const block = canvasBlocks.value.find((b) => b.uid === chunk.uid);
            if (block) newOrder.push(block);
        }
    }

    pushHistory();
    canvasBlocks.value = newOrder;
}

// ---- Image File Manager ----
function triggerImageUpload() {
    if (!selectedBlock.value || selectedBlock.value.type !== 'image') return;
    imageManagerOpen.value = true;
}

function onImageSelect(item) {
    if (selectedBlock.value && item?.url) {
        selectedBlock.value.content = item.url;
    }
    imageManagerOpen.value = false;
}

// ---- Font kustom (TTF/OTF/WOFF) ----
function triggerFontUpload() {
    fontFileInput.value?.click();
}

async function onFontFileChange(e) {
    const files = Array.from(e.target.files || []);
    e.target.value = '';
    if (!files.length) return;
    if (!(await spendCredits(files.length * creditPricing.value.font, 'font_upload'))) return;
    for (const file of files) {
        const font = await addCustomFont(file);
        customFonts.value = listCustomFonts();
        // Terapkan langsung sebagai font dokumen (bisa diganti lagi di daftar).
        fontChoice.value = font.family;
        customFont.value = '';
        showToast(`Font "${font.family}" ditambahkan.`);
    }
}

// ---- Editor blok kode ----
function openCodeEditor(uid) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (!b) return;
    selectedUid.value = uid;
    codeEditingUid.value = uid;
    codeDraft.value = b.content || '';
    codeModalOpen.value = true;
}

function saveCode() {
    if (codeEditingUid.value) {
        const b = canvasBlocks.value.find((x) => x.uid === codeEditingUid.value);
        if (b) b.content = codeDraft.value;
    }
    closeCodeEditor();
}

function closeCodeEditor() {
    codeModalOpen.value = false;
    codeEditingUid.value = null;
}

// Toast notifikasi (top center) untuk status simpan.
function showToast(message) {
    toast(message);
}

function save() {
    lastEdited.value = Date.now();
    saveDirty = false;
    saveProjectSettings();
    showToast('Tersimpan');
    // TODO: simpan blok halaman ke API backend.
}

// ---- Setup project & penyimpanan lokal ----
const storageKey = computed(() => `tulisin:project:${projectId.value}`);

// Auto-save lokal (debounce): simpan hanya setelah pengguna berhenti mengetik
// selama AUTOSAVE_DELAY. Tidak ada request jaringan — murni localStorage,
// sehingga tidak membebani server/API.
const AUTOSAVE_DELAY = 2000;
let saveTimer = null;
let saveDirty = false;

function scheduleSave() {
    saveDirty = true;
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(flushSave, AUTOSAVE_DELAY);
}

// Tulis perubahan tertunda ke localStorage (dipanggil oleh debounce atau saat keluar).
function flushSave() {
    if (saveTimer) {
        clearTimeout(saveTimer);
        saveTimer = null;
    }
    if (!saveDirty) return;
    saveDirty = false;
    saveProjectSettings();
    showToast('Tersimpan otomatis');
}

const docVersion = ref(0); // versi optimistik dari server; 0 = belum pernah tersimpan.
let lastSentHash = ''; // dirty check: konten yang terakhir terkirim ke server.

function projectPayload() {
    return {
        name: projectName.value,
        category: projectCategory.value,
        format: pageFormat.value,
        orientation: pageOrientation.value,
        margins: pageMargins.value,
        lastEdited: lastEdited.value,
        font: fontChoice.value,
        customFont: customFont.value,
        customFontData: customFonts.value.find((f) => f.family === effectiveFontFamily.value) || null,
        fontSize: pageFontSize.value,
        lineHeight: pageLineHeight.value,
        pageNumberPosition: pageNumberPosition.value,
        frontMatterStyle: frontMatterStyle.value,
        bodyStyle: bodyStyle.value,
        bodyStart: bodyStart.value,
        citationStyle: citationStyle.value,
        citedReferences: citedReferences.value,
        hiddenTocUids: hiddenTocUids.value,
        blocks: canvasBlocks.value,

        // Data render lengkap agar halaman share tampil identik dengan preview builder
        // (pagination, penomoran, caption, daftar isi/tabel/gambar/pustaka).
        pages: pages.value,
        numberingMap: numberingMap.value,
        captionNumbers: captionNumbers.value,
        tocEntries: tocEntries.value,
        tableEntries: tableEntries.value,
        figureEntries: figureEntries.value,
        referenceEntries: referenceEntries.value,
        pageBoxStyle: pageBoxStyle.value,
        contentHeightPx: contentHeightPx.value,
        pageNumberClass: pageNumberClass.value,
        pageNumberLabels: pages.value.map((_, i) => ({ isCover: isCoverPage(i), label: pageNumberLabel(i) })),
    };
}

function applyProjectData(data) {
    if (!data || typeof data !== 'object') return;
    if (typeof data.name === 'string') projectName.value = data.name;
    if (typeof data.category === 'string') projectCategory.value = data.category;
    if (data.format && pageSizes[data.format]) pageFormat.value = data.format;
    if (data.orientation === 'landscape' || data.orientation === 'portrait') pageOrientation.value = data.orientation;
    if (data.margins && typeof data.margins === 'object') {
        pageMargins.value = {
            top: Number(data.margins.top) || 2.54,
            right: Number(data.margins.right) || 2.54,
            bottom: Number(data.margins.bottom) || 2.54,
            left: Number(data.margins.left) || 2.54,
        };
    }
    if (typeof data.lastEdited === 'number') lastEdited.value = data.lastEdited;
    if (typeof data.font === 'string') fontChoice.value = data.font;
    if (typeof data.customFont === 'string') customFont.value = data.customFont;
    if (typeof data.fontSize === 'number') pageFontSize.value = data.fontSize;
    if (typeof data.lineHeight === 'number') pageLineHeight.value = data.lineHeight;
    if (typeof data.pageNumberPosition === 'string') pageNumberPosition.value = data.pageNumberPosition;
    if (typeof data.frontMatterStyle === 'string') frontMatterStyle.value = data.frontMatterStyle;
    if (typeof data.bodyStyle === 'string') bodyStyle.value = data.bodyStyle;
    if (typeof data.bodyStart === 'number') bodyStart.value = data.bodyStart;
    if (typeof data.citationStyle === 'string') citationStyle.value = data.citationStyle;
    if (Array.isArray(data.citedReferences)) citedReferences.value = data.citedReferences;
    if (Array.isArray(data.hiddenTocUids)) hiddenTocUids.value = data.hiddenTocUids.filter((x) => typeof x === 'string');
    if (Array.isArray(data.blocks)) canvasBlocks.value = data.blocks;
    if (typeof data.version === 'number') docVersion.value = data.version;
}

function saveProjectSettings() {
    const data = projectPayload();
    try {
        localStorage.setItem(storageKey.value, JSON.stringify({ ...data, version: docVersion.value }));
    } catch (e) {
        // abaikan jika localStorage tidak tersedia / penuh
    }
    // Perbarui indeks project (metadata ringan + pratinjau) untuk halaman daftar.
    touchProject(projectId.value, {
        name: projectName.value,
        category: projectCategory.value,
        lastEdited: lastEdited.value,
        blocks: canvasBlocks.value,
    });
    persistProjectToServer(data);
}

// Simpan payload dokumen ke PostgreSQL (JSONB) dengan optimistic locking:
// hanya kirim bila konten berubah (dirty check) dan versinya cocok dengan server.
// Request diserialkan agar tidak ada dua simpan bersamaan yang saling menimpa versi.
let saveInFlight = false;
let savePending = false;

async function persistProjectToServer(data) {
    if (!projectId.value) return;

    let payloadStr;
    try {
        payloadStr = JSON.stringify(data);
    } catch {
        return;
    }
    // Dirty check: jangan kirim ulang konten yang sama dengan kiriman terakhir.
    if (payloadStr === lastSentHash) return;

    // Hindari request paralel; antrikan perubahan yang datang saat simpan berjalan.
    if (saveInFlight) {
        savePending = true;
        return;
    }

    saveInFlight = true;
    try {
        await doPersist(payloadStr, data);
    } finally {
        saveInFlight = false;
        if (savePending) {
            savePending = false;
            scheduleSave();
        }
    }
}

async function doPersist(payloadStr, data) {
    try {
        const res = await request(`/api/projects/${encodeURIComponent(projectId.value)}`, {
            method: 'PUT',
            body: JSON.stringify({ payload: data, version: docVersion.value }),
        });

        if (res.ok) {
            lastSentHash = payloadStr;
            if (typeof res.data?.version === 'number') docVersion.value = res.data.version;
            return;
        }

        if (res.status === 409) {
            // Versi server lebih baru: ikuti versi terbaru lalu kirim ulang editan
            // lokal (last-write-wins) agar perubahan user tidak hilang.
            if (typeof res.data?.version === 'number') docVersion.value = res.data.version;
            lastSentHash = '';

            const retry = await request(`/api/projects/${encodeURIComponent(projectId.value)}`, {
                method: 'PUT',
                body: JSON.stringify({ payload: data, version: docVersion.value }),
            });

            if (retry.ok) {
                lastSentHash = payloadStr;
                if (typeof retry.data?.version === 'number') docVersion.value = retry.data.version;
            } else {
                showToast('Gagal menyimpan. Perubahan tetap aman di lokal, coba lagi.');
            }
        }
        // Error selain 409 dibiarkan; data tetap aman di localStorage.
    } catch {
        // Gagal terhubung ke server; localStorage tetap jadi cadangan.
    }
}

function loadProjectSettings() {
    isLoading = true;
    try {
        const raw = localStorage.getItem(storageKey.value);
        if (!raw) return false;
        const data = JSON.parse(raw);
        if (!data || typeof data !== 'object') return false;
        applyProjectData(data);
        return true;
    } catch (e) {
        return false;
    } finally {
        nextTick(() => {
            isLoading = false;
        });
    }
}

// Muat dokumen langsung dari server (GET /api/projects/{uuid}) saat cache
// lokal kosong. Dipakai sebagai fallback setelah loadProjectSettings() gagal.
async function loadProjectFromServer() {
    if (!projectId.value) return false;

    let data;
    try {
        data = await getJson(`/api/projects/${encodeURIComponent(projectId.value)}`);
    } catch {
        return false;
    }

    const payload = data?.payload && typeof data.payload === 'object' ? data.payload : null;
    if (!payload) return false;

    isLoading = true;
    applyProjectData(payload);
    if (typeof data.version === 'number') docVersion.value = data.version;

    // Cache ke localStorage agar pembukaan berikutnya cepat & offline-safe.
    try {
        localStorage.setItem(storageKey.value, JSON.stringify({ ...payload, version: docVersion.value }));
    } catch {
        // abaikan jika localStorage tidak tersedia / penuh
    }

    nextTick(() => {
        isLoading = false;
    });
    return true;
}

function openSetup(mode = 'setup') {
    setupMode.value = mode;
    draftName.value = projectName.value;
    draftCategory.value = projectCategory.value;
    draftFormat.value = pageFormat.value;
    draftOrientation.value = pageOrientation.value;
    draftMargins.value = { ...pageMargins.value };
    setupOpen.value = true;
}

// Dibuka dari tombol "Edit Project" di header saat proyek sudah ada.
function openEditProject() {
    openSetup('edit');
}

function cancelSetup() {
    setupOpen.value = false;
}

function confirmSetup() {
    projectName.value = draftName.value.trim() || 'Proyek Tanpa Judul';
    projectCategory.value = draftCategory.value;
    pageFormat.value = draftFormat.value;
    pageOrientation.value = draftOrientation.value;
    pageMargins.value = { ...draftMargins.value };
    setupOpen.value = false;
    saveProjectSettings();
}

function toggleDownload() {
    downloadOpen.value = !downloadOpen.value;
    closePageSettings();
    closePageMenu();
    closeBlockMenu();
}

// Escape karakter HTML untuk teks polos (judul, nomor bab).
function escHtml(s) {
    return String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

// Ubah satu blok menjadi HTML untuk dokumen Word (.doc).
function blockToWordHtml(b) {
    if (b.type === 'pageBreak') return '<br style="page-break-before:always" />';
    const text = (b.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    switch (b.type) {
        case 'chapter':
            return `<h1>${escHtml(numberingMap.value[b.uid] || '')} ${escHtml(text)}</h1>`;
        case 'abstract':
            return `<h2>${escHtml(b.pageTitle || 'ABSTRAK')}</h2>${b.content || ''}`;
        case 'blankPage':
            return `<h2>${escHtml(b.pageTitle || 'HALAMAN')}</h2>${b.content || ''}`;
        case 'toc':
            return '<h2>DAFTAR ISI</h2>';
        case 'listTables':
            return '<h2>DAFTAR TABEL</h2>';
        case 'listFigures':
            return '<h2>DAFTAR GAMBAR</h2>';
        case 'references':
            return '<h2>DAFTAR PUSTAKA</h2>';
        case 'quote':
            return `<blockquote>${b.content || ''}</blockquote>`;
        case 'table':
            return '<p><em>[Tabel]</em></p>';
        case 'image':
            return '<p><em>[Gambar]</em></p>';
        case 'formula':
            return '<p><em>[Rumus]</em></p>';
        case 'code':
            return `<pre style="font-family:Consolas,'Courier New',monospace;font-size:10pt;background:#f5f5f5;padding:8pt;white-space:pre-wrap">${escHtml(b.content || '')}</pre>`;
        default:
            if (/^h\d+$/.test(b.type)) return `<h2>${escHtml(text)}</h2>`;
            return `<p>${b.content || ''}</p>`;
    }
}

// Nama file unduhan sesuai format: "[judul] - Nama Per Bagian".
function downloadFileName(sectionName, ext) {
    const title = (projectName.value || 'Proyek Tanpa Judul').trim();
    const part = sectionName && sectionName !== 'Semua' ? sectionName : 'Semua';
    return `${title} - ${part}`.replace(/[\\/:*?"<>|]/g, '-').trim() + `.${ext}`;
}

// Picu unduhan blob ke disk pengguna.
function triggerDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// Ekspor dokumen (sesuai scope) sebagai file Word (.doc) yang bisa dibuka di MS Word.
function exportWord(scope, sectionName) {
    const list = blocksForScope(scope);
    const body = list.map((b) => blockToWordHtml(b)).join('\n');
    const html = [
        '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">',
        '<head><meta charset="utf-8"><title>',
        escHtml(projectName.value || 'project'),
        '</title>',
        '<style>body{font-family:Calibri,Arial,sans-serif;font-size:12pt;line-height:1.4}',
        'h1{font-size:16pt;font-weight:bold;text-align:center;margin:0 0 12pt}',
        'h2{font-size:14pt;font-weight:bold;margin:0 0 8pt}',
        'p{margin:0 0 6pt}blockquote{margin:0 0 6pt 16pt}</style>',
        '</head><body>',
        body,
        '</body></html>',
    ].join('');
    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    triggerDownload(blob, downloadFileName(sectionName, 'doc'));
}

// Cache CSS dokumen: stylesheet tidak berubah antar-ekspor, jadi cukup
// dikumpulkan sekali saja (hindari fetch berulang yang memperlambat unduhan).
let documentCssCache = null;

// Kumpulkan seluruh CSS aktif (inline + stylesheet eksternal) agar PDF identik dengan preview.
async function gatherDocumentCss() {
    if (documentCssCache !== null) return documentCssCache;
    const parts = [];
    document.querySelectorAll('style').forEach((s) => parts.push(s.textContent || ''));
    const links = [...document.querySelectorAll('link[rel="stylesheet"]')];
    await Promise.all(links.map(async (l) => {
        try {
            const res = await fetch(l.href);
            if (res.ok) parts.push(await res.text());
        } catch (e) {
            /* abaikan stylesheet yang gagal dimuat */
        }
    }));
    documentCssCache = parts.join('\n');
    return documentCssCache;
}

// Ekspor PDF langsung (tanpa window.print) via renderer Chrome/Edge di backend.
async function exportPdf(scope, sectionName) {
    printScope.value = scope || 'all';
    await nextTick();
    const printEl = document.querySelector('.print-only');
    if (!printEl) {
        showToast('Dokumen belum siap dicetak.');
        return false;
    }
    const css = await gatherDocumentCss();
    const pageW = pageDimensions.value.width;
    const pageH = pageDimensions.value.minHeight;
    // CSS khusus halaman PDF: paksa tiap .print-page setinggi tepat satu halaman
    // (bukan min-height) dan klip overflow, agar tidak muncul halaman kosong kedua.
    // Sekaligus nonaktifkan placeholder (mis. "Tulis teks...") milik editor kosong.
    const printCss = [
        `@page{size:${pageW} ${pageH};margin:0}`,
        'html,body{margin:0;padding:0}',
        '.print-only{display:block;margin:0;padding:0}',
        `.print-page{height:${pageH};min-height:0;overflow:hidden;box-sizing:border-box;margin:0;break-inside:avoid;page-break-after:always}`,
        '.print-page:last-child{page-break-after:auto}',
        '.print-page .editor:empty::before, .print-page .section-title.editable-title:empty::before{content:none !important}',
    ].join('\n');
    const doc = [
        '<!doctype html><html><head><meta charset="utf-8">',
        '<style>',
        css,
        '</style>',
        '<style>',
        printCss,
        '</style>',
        '</head><body>',
        printEl.outerHTML,
        '</body></html>',
    ].join('');

    try {
        // Pastikan cookie CSRF tersedia, lalu sertakan tokennya — endpoint POST
        // stateful (Sanctum) memvalidasi CSRF, jadi tanpa header ini akan 419.
        await ensureCsrf();
        const csrfToken = (document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1];
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (csrfToken) headers['X-XSRF-TOKEN'] = decodeURIComponent(csrfToken);

        const res = await fetch('/api/export/pdf', {
            method: 'POST',
            credentials: 'include',
            headers,
            body: JSON.stringify({ html: doc, project: projectId.value || null, format: 'pdf' }),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => null);
            throw new Error(data?.error || 'Gagal membuat PDF.');
        }
        const blob = await res.blob();
        triggerDownload(blob, downloadFileName(sectionName, 'pdf'));
        return true;
    } catch (err) {
        showToast(err.message || 'Gagal membuat PDF.');
        return false;
    }
}

async function downloadProject(opt) {
    if (exporting.value) return; // cegah unduhan ganda saat ekspor berjalan
    const cost = Number(opt.cost) || 0;
    const scope = opt.scope || 'all';
    const sectionName = opt.label || 'Semua';

    // Cek saldo dulu (tanpa memotong) agar tidak mengunduh lalu gagal bayar.
    if (cost > 0 && totalCredits.value < cost) {
        showToast('Saldo koin tidak mencukupi.');
        return;
    }

    exporting.value = true;
    let ok = true;
    try {
        if (downloadFormat.value === 'word') {
            // Ekspor Word instan; beri jeda singkat agar animasi unduhan terlihat.
            await new Promise((r) => setTimeout(r, 700));
            exportWord(scope, sectionName);
        } else {
            ok = await exportPdf(scope, sectionName);
        }
    } finally {
        exporting.value = false;
    }

    // Jangan pernah memotong koin bila ekspor gagal.
    if (!ok) return;

    // Potong koin hanya setelah ekspor benar-benar berhasil.
    if (cost > 0) {
        const paid = await spendCredits(cost, 'download');
        if (!paid) return;
    }

    // Tutup modal hanya jika unduhan sukses dan pembayaran koin berhasil.
    downloadOpen.value = false;
}

// ---- Grid & ruler ----
const gridStyle = {
    backgroundImage: 'radial-gradient(circle, rgba(163,163,163,0.4) 1px, transparent 1px)',
    backgroundSize: '20px 20px',
};

const hRulerStyle = {
    backgroundImage:
        'repeating-linear-gradient(to right, #d4d4d4 0 1px, transparent 1px 10px), repeating-linear-gradient(to right, #9ca3af 0 1px, transparent 1px 50px)',
    backgroundSize: '10px 100%, 50px 100%',
    backgroundPosition: 'bottom, bottom',
    backgroundRepeat: 'repeat, repeat',
};

const vRulerStyle = {
    backgroundImage:
        'repeating-linear-gradient(to bottom, #d4d4d4 0 1px, transparent 1px 10px), repeating-linear-gradient(to bottom, #9ca3af 0 1px, transparent 1px 50px)',
    backgroundSize: '100% 10px, 100% 50px',
    backgroundPosition: 'left, left',
    backgroundRepeat: 'repeat, repeat',
};

// Tampilkan panduan margin + grid di canvas (toggle).
const showGuides = ref(false);

// Kotak area konten (di dalam margin) sebagai panduan batas margin.
const contentAreaStyle = computed(() => ({
    top: `${cmToPx(pageMargins.value.top)}px`,
    right: `${cmToPx(pageMargins.value.right)}px`,
    bottom: `${cmToPx(pageMargins.value.bottom)}px`,
    left: `${cmToPx(pageMargins.value.left)}px`,
}));

const pageGridLines =
    'linear-gradient(to right, rgba(128,128,128,0.18) 1px, transparent 1px), linear-gradient(to bottom, rgba(128,128,128,0.18) 1px, transparent 1px)';

const horizontalMarks = computed(() => {
    const marks = [];
    for (let px = 0; px <= 1400; px += 100) marks.push({ px, label: `${px}` });
    return marks;
});
</script>

<template>
    <div v-if="!workspaceView" class="app-shell flex h-screen flex-col bg-white text-neutral-900 print:h-auto print:bg-white dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Header builder -->
        <HeaderBuilder
            :project-name="projectName"
            :project-id="projectId"
            :last-edited-label="lastEditedLabel"
            :total-credits="totalCredits"
            v-model:show-guides="showGuides"
            @open-blocks="blocksOpen = true"
            @open-setup="openEditProject"
            @open-preview="openPreview"
            @print="printDocument"
            @toggle-download="toggleDownload"
            @open-inspector="inspectorOpen = true"
            @open-agent="openAgent"
            @open-share="openShare"
        />


        <!-- Body: palet blok + canvas + pengaturan -->
        <div class="flex min-h-0 flex-1">
            <div v-if="blocksOpen" class="fixed inset-0 z-30 bg-black/50 print:hidden lg:hidden" @click="blocksOpen = false"></div>
            <div v-if="inspectorOpen" class="fixed inset-0 z-30 bg-black/50 print:hidden xl:hidden" @click="inspectorOpen = false"></div>

            <!-- Palet blok konten -->
            <BlockPalette
                :groups="groupedBlockTypes"
                :sections="DOCUMENT_SECTIONS"
                :workspace-count="workspaceReferenceCount"
                v-model:open="blocksOpen"
                v-model:search="blockSearch"
                @dragstart="onPaletteDragStart"
                @insert="insertAfterSelected"
                @insert-section="insertSection"
                @workspace-open="openWorkspace"
            />


            <!-- Canvas -->
            <PageCanvas
                ref="pageCanvas"
                :canvas-blocks="canvasBlocks"
                :pages="pages"
                :selected-uid="selectedUid"
                :drop-index="dropIndex"
                :page-box-style="pageBoxStyle"
                :mirror-style="mirrorStyle"
                :content-height-px="contentHeightPx"
                :page-height-px="pageHeightPx"
                :page-dimensions="pageDimensions"
                :grid-style="gridStyle"
                :h-ruler-style="hRulerStyle"
                :v-ruler-style="vRulerStyle"
                :horizontal-marks="horizontalMarks"
                :show-guides="showGuides"
                :content-area-style="contentAreaStyle"
                :page-grid-lines="pageGridLines"
                :numbering-map="numberingMap"
                :toc-entries="tocEntries"
                :table-entries="tableEntries"
                :figure-entries="figureEntries"
                :reference-entries="referenceEntries"
                :citation-style="citationStyle"
                :caption-numbers="captionNumbers"
                :page-number-position="pageNumberPosition"
                :page-number-class="pageNumberClass"
                :font-options="fontOptions"
                :selected-block="selectedBlock"
                :set-canvas-el="setCanvasEl"
                :set-measure-ref="setMeasureRef"
                :is-drop-indicator-before="isDropIndicatorBefore"
                :is-cover-page="isCoverPage"
                :page-number-label="pageNumberLabel"
                v-model:current-page="currentPage"
                v-model:page-jump="pageJump"
                @select="selectedUid = $event"
                @update-content="updateContent"
                @update-indent="updateIndent"
                @update-page-title="updatePageTitle"
                @update-width="setWidth"
                @set-block-font-size="setBlockFontSize"
                @remove-block-by-uid="removeBlockByUid"
                @block-dragstart="onBlockDragStart"
                @dragend="onDragEnd"
                @contextmenu-block="openBlockMenu"
                @open-page-settings="openPageSettings"
                @open-page-menu="openPageMenu"
                @dragover="onDragOver"
                @drop="onDrop"
                @move-page="movePage"
                @delete-page="deletePage"
                @set-font-family="setBlockFont"
                @set-font-size="setBlockFontSize"
                @edit-code="openCodeEditor"
            />


            <!-- Pengaturan -->
            <InspectorPanel
                :selected-block="selectedBlock"
                :document-tabs="documentTabs"
                :toc-entry-by-uid="tocEntryByUid"
                :numbering-map="numberingMap"
                :font-select-options="fontSelectOptions"
                :line-height-options="lineHeightOptions"
                :page-format-options="pageFormatOptions"
                :block-line-height-options="blockLineHeightOptions"
                :caption-position-options="captionPositionOptions"
                :citation-style-options="citationStyleOptions"
                :align-options="alignOptions"
                :current-chapter="currentChapter"
                :canvas-summary="canvasSummary"
                :references="allReferences"
                :block-ai-prompts="blockAiPrompts"
                :ai-gen-loading="aiGenLoading"
                :word-count="wordCount"
                :is-text-block="isTextBlock"
                :is-heading-block="isHeadingBlock"
                :can-style-text="canStyleText"
                :caption-numbers="captionNumbers"
                :type-label="typeLabel"
                :type-icon="typeIcon"
                :block-preview="blockPreview"
                v-model:open="inspectorOpen"
                v-model:tab="inspectorTab"
                v-model:font-choice="fontChoice"
                v-model:custom-font="customFont"
                v-model:page-font-size="pageFontSize"
                v-model:page-line-height="pageLineHeight"
                v-model:page-format="pageFormat"
                v-model:page-margins="pageMargins"
                v-model:citation-style="citationStyle"
                v-model:ai-gen-input="aiGenInput"
                v-model:ai-gen-output="aiGenOutput"
                @toggle-toc="toggleTocEntry"
                @scroll-to-block="scrollToBlock"
                @deselect-block="deselectBlock"
                @set-block-line-height="setBlockLineHeight"
                @set-custom-number="setCustomNumber"
                @insert-inline-citation="insertInlineCitation"
                @open-citation-browser="openCitationBrowser"
                @open-workspace="openWorkspace"
                @set-show-caption="setShowCaption"
                @set-caption="setCaption"
                @set-caption-position="setCaptionPosition"
                @trigger-image-upload="triggerImageUpload"
                @trigger-font-upload="triggerFontUpload"
                @set-width="setWidth"
                @set-align="setAlign"
                @set-columns="setColumns"
                @update-indent="updateIndent"
                @set-first-line-indent="setFirstLineIndent"
                @set-spacing="setSpacing"
                @set-text-color="setTextColor"
                @move-block-by="moveBlockBy"
                @remove-block="removeBlock"
                @generate-block-content="generateBlockContent"
                @insert-generated-content="insertGeneratedContent"
            />

        </div>
    </div>

    <!-- Mode baca Tulisin Workspace (read-only) -->
    <WorkspaceViewer v-if="workspaceView" :reference="workspaceReference" />

    <!-- Popover & context menu (halaman & blok) -->
    <ContextMenus
        :page-settings-pos="pageSettingsPos"
        :page-number-position-options="pageNumberPositionOptions"
        :front-matter-style-options="frontMatterStyleOptions"
        :body-style-options="bodyStyleOptions"
        :block-menu-block="blockMenuBlock"
        :block-menu-type-label="blockMenuTypeLabel"
        v-model:page-settings-open="pageSettingsOpen"
        v-model:page-menu="pageMenu"
        v-model:block-menu="blockMenu"
        v-model:page-number-position="pageNumberPosition"
        v-model:front-matter-style="frontMatterStyle"
        v-model:body-style="bodyStyle"
        v-model:body-start="bodyStart"
        @close-page-settings="closePageSettings"
        @close-page-menu="closePageMenu"
        @close-block-menu="closeBlockMenu"
        @duplicate-page="duplicateFromMenu"
        @open-settings-from-menu="openSettingsFromMenu"
        @plagiarism="openPlagiarismCheck"
        @turnitin="openTurnitinOptimizer"
        @history="openAiHistory"
        @delete-page="deletePageFromMenu"
        @upload-image="blockMenuUploadImage"
        @delete-block="blockMenuDelete"
        @paraphrase-block="paraphraseBlock"
    />

    <!-- Modal setup project (nama, format, orientasi, margin) -->
    <SetupModal
        :page-format-options="pageFormatOptions"
        :page-orientation-options="pageOrientationOptions"
        :category-options="projectCategoryOptions"
        v-model:open="setupOpen"
        v-model:mode="setupMode"
        v-model:draft-name="draftName"
        v-model:draft-category="draftCategory"
        v-model:draft-format="draftFormat"
        v-model:draft-orientation="draftOrientation"
        v-model:draft-margins="draftMargins"
        @confirm="confirmSetup"
        @cancel="cancelSetup"
    />

    <!-- Konfirmasi hapus blok (saat blok memiliki isi) -->
    <DeleteConfirmModal
        v-model:open="deleteConfirmOpen"
        @confirm="confirmDeleteBlock"
        @cancel="cancelDeleteBlock"
    />

    <!-- Pratinjau dokumen (read-only, salin dinonaktifkan) -->
    <PreviewModal
        :pages="pages"
        :page-box-style="pageBoxStyle"
        :page-height-px="contentHeightPx"
        :caption-numbers="captionNumbers"
        :content-height-px="contentHeightPx"
        :numbering-map="numberingMap"
        :toc-entries="tocEntries"
        :table-entries="tableEntries"
        :figure-entries="figureEntries"
        :reference-entries="referenceEntries"
        :citation-style="citationStyle"
        :page-number-position="pageNumberPosition"
        :page-number-class="pageNumberClass"
        :is-cover-page="isCoverPage"
        :page-number-label="pageNumberLabel"
        v-model:open="previewOpen"
    />

    <!-- Modal screening plagiarism -->
    <PlagiarismModal
        :loading="plagiarismLoading"
        :result="plagiarismResult"
        v-model:open="plagiarismOpen"
        @close="closePlagiarism"
        @goto="gotoPlagiarismMatch"
        @apply="applyPlagiarismFix"
        @keep="keepPlagiarismMatch"
    />

    <!-- Modal Turnitin AI Optimizer -->
    <TurnitinModal
        :loading="turnitinLoading"
        :result="turnitinResult"
        v-model:open="turnitinOpen"
        @close="closeTurnitin"
        @goto="gotoTurnitinMatch"
        @apply="applyTurnitinFix"
        @keep="keepTurnitinMatch"
    />

    <!-- Modal riwayat hasil AI (analisis & capture) -->
    <AiHistoryModal
        :loading="aiHistoryLoading"
        :list="aiHistoryList"
        v-model:open="aiHistoryOpen"
        @close="aiHistoryOpen = false"
        @revert="applyHistoryRevert"
        @delete="deleteAiResult"
    />

    <!-- Modal browser referensi sitasi -->
    <CitationBrowserModal
        :references="filteredReferences"
        :style-options="citationStyleOptions"
        :preview="citationPreview"
        v-model:open="citationBrowserOpen"
        v-model:search="citationSearch"
        v-model:citation-style="citationStyle"
        @close="closeCitationBrowser"
        @select="selectReferenceFromBrowser"
    />

    <!-- Modal download (PDF / Word) -->
    <DownloadModal
        :scopes="downloadScopes"
        :exporting="exporting"
        v-model:open="downloadOpen"
        v-model:format="downloadFormat"
        @download="downloadProject"
    />

    <!-- Modal Agent AI Canvas (membantu di dalam canvas) -->
    <AgentCanvasModal
        :summary="canvasSummary"
        :is-empty="agentEmpty"
        :block-count="contentBlocks.length"
        :page-count="pages.length"
        :project-uuid="projectId"
        :block-types="agentBlockTypes"
        v-model:open="agentModalOpen"
        @close="agentModalOpen = false"
        @apply="applyAgentToCanvas"
    />

    <!-- Modal editor blok kode -->
    <CodeBlockModal
        v-model:open="codeModalOpen"
        v-model:code="codeDraft"
        @save="saveCode"
        @close="closeCodeEditor"
    />

    <!-- Modal manajer gambar -->
    <ImageFileManager
        v-model:open="imageManagerOpen"
        @select="onImageSelect"
        @close="imageManagerOpen = false"
    />

    <!-- Modal bagikan dokumen (public view) -->
    <ShareModal
        v-model:open="shareOpen"
        :name="projectName"
        :payload="sharePayload"
        :project-id="projectId"
        @close="shareOpen = false"
    />

    <!-- Print view: hanya halaman dokumen (bersih, tanpa UI builder) -->
    <PrintView
        :print-pages="printPages"
        :page-box-style="pageBoxStyle"
        :caption-numbers="captionNumbers"
        :content-height-px="contentHeightPx"
        :numbering-map="numberingMap"
        :toc-entries="tocEntries"
        :table-entries="tableEntries"
        :figure-entries="figureEntries"
        :reference-entries="referenceEntries"
        :citation-style="citationStyle"
        :page-number-position="pageNumberPosition"
        :page-number-class="pageNumberClass"
        :is-cover-page="isCoverPage"
        :page-number-label="pageNumberLabel"
    />

    <!-- Input file tersembunyi untuk unggah font custom (TTF/OTF/WOFF) -->
    <input
        ref="fontFileInput"
        type="file"
        accept=".ttf,.otf,.woff,.woff2,font/ttf,font/otf,font/woff,font/woff2"
        class="hidden"
        @change="onFontFileChange"
    />
</template>

<style>
.print-only {
    display: none;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.15s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }

    html,
    body {
        background: #fff !important;
    }

    .app-shell {
        display: none !important;
    }

    .print-only {
        display: block !important;
    }

    .print-page {
        position: relative;
        box-sizing: border-box;
        break-inside: avoid;
        page-break-after: always;
        background: #fff;
    }

    .print-page:last-child {
        page-break-after: auto;
    }
}
</style>