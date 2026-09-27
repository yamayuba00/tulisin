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
    Rocket,
    X,
    Search,
    ChevronUp,
    ChevronDown,
    Replace,
    GripHorizontal,
    Star,
    MessageSquare,
    Loader2,
} from 'lucide-vue-next';
import HeaderBuilder from './components/HeaderBuilder.vue';
import DownloadModal from './components/DownloadModal.vue';
import BlockPalette from './components/BlockPalette.vue';
import InspectorPanel from './components/InspectorPanel.vue';
import PageCanvas from './components/PageCanvas.vue';
import PrintView from './components/PrintView.vue';
import SetupModal from './components/SetupModal.vue';
import DeleteConfirmModal from '../../components/DeleteConfirmModal.vue';
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
import { listCustomFonts, addCustomFont, registerFontFace } from '../../utils/fontManager';
import { CSL_STYLES, formatCitation, authorYearLabel, parseCSLItem, cslFormatter } from '../../utils/csl-formatter';
import { listReferences as listWorkspaceReferences, syncReferences as syncWorkspaceReferences } from '../../utils/workspaceLibrary';
import { PROJECT_CATEGORY_OPTIONS, DEFAULT_PROJECT_CATEGORY } from '../../utils/projectCategories';
import { touchProject } from '../../utils/projectIndex';
import { DOCUMENT_SECTIONS, buildSectionBlocks, findSection } from '../../utils/sections';
import { getJson, request, ensureCsrf, requestAiGenerate } from '../../utils/http';
import { creditPricing, loadCreditPricing } from '../../utils/creditPricing';
import { buildTemplateBlocks } from '../../utils/templates';
import { renderMarkdown } from '../../utils/markdown';
import { toast } from '../../utils/toast';
import { useAuth } from '../../utils/auth';
const { currentUser } = useAuth();

// Fungsi generate HTML template cover standar akademik otomatis (center align),
// membaca data dari currentUser (nama, NIM) + UserProfile (universitas, kota).
// Format standar: SKRIPSI/JUDUL/syarat gelar/logo/disusun oleh/program studi/fakultas/kota/tahun.
function generateCoverHtml(titleText, logoUrl = null) {
    const name = String(currentUser.value?.name || '').trim() || '[isi nama lengkap]';
    const nim = String(currentUser.value?.profile?.nim || '').trim();
    const university = String(currentUser.value?.profile?.university || '').trim();
    const city = (currentUser.value?.profile?.city || 'Bekasi').trim();
    const year = new Date().getFullYear();
    const faculty = (currentUser.value?.profile?.faculty || 'Fakultas Teknik').trim();
    const studyProgram = (currentUser.value?.profile?.major || 'Teknik Informatika').trim();
    
    const logoHtml = logoUrl ? `<img src="${logoUrl}" alt="Logo" style="max-width:120px;margin:1em 0">` : '';
    const nimLine = nim ? `<p>NIM: <strong>${nim}</strong></p>` : '<p>NIM: [isi NIM]</p>';
    
    return `
<div class="cover-content" style="display:flex;flex-direction:column;height:100%;justify-content:center;align-items:center;text-align:center;font-family:${fontChoice.value || 'Times New Roman'};">
    ${titleText ? `<h1 style="margin-bottom:.5em;text-transform:uppercase;font-size:18pt;line-height:1.3">${escHtml(titleText)}</h1>` : ''}
    <p style="margin:.5em 0;">SKRIPSI</p>
    <p style="margin:.75em 0;">Diajukan untuk memenuhi salah satu syarat<br>memperoleh Gelar Sarjana Komputer</p>
    ${logoHtml}
    <p style="margin:.75em 0;">Disusun Oleh:</p>
    <p style="margin:.5em 0;"><strong>${escHtml(name)}</strong></p>
    ${nimLine}
    <div style="margin-top:2em;">
        <p style="margin:.25em 0;"><strong>${escHtml(studyProgram)}</strong></p>
        <p style="margin:.25em 0;"><strong>${escHtml(faculty)}</strong></p>
        <p style="margin:.25em 0;"><strong>${escHtml(university || 'UNIVERSITAS')}${university && city ? ', ' : ''}${escHtml(city)}</strong></p>
        <p style="margin:.25em 0;"><strong>${year}</strong></p>
    </div>
</div>
    `.replace(/\s+/g, (m) => m.length > 1 ? ' ' : m);
}

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
const selectedUids = ref([]); // Multi-selection: list uid blok yang terseleksi
let lastSelectedUid = null;  // untuk shift-click range selection
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
const customFonts = ref([]);
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
const imageSelectTarget = ref('block'); // 'block' | 'watermark'

// ---- Bagikan dokumen (public view) ----
const shareOpen = ref(false);
// Payload share sama dengan payload yang disimpan ke DB (termasuk data render
// pagination/numbering/caption agar halaman public tampil identik dengan preview).
const sharePayload = computed(() => projectPayload());

function openShare() {
    shareOpen.value = true;
}

// ---- Publikasikan project ke Lists Project (wajib sertakan testimoni) ----
const publishOpen = ref(false);
const publishDescription = ref('');
const publishing = ref(false);
const publishRating = ref(0);
const publishComment = ref('');
const MAX_COMMENT = 150;

const canPublish = computed(() =>
    !publishing.value &&
    publishRating.value >= 1 &&
    publishRating.value <= 5 &&
    publishComment.value.trim().length > 0,
);

function openPublish() {
    publishDescription.value = '';
    publishRating.value = 0;
    publishComment.value = '';
    publishOpen.value = true;
}

function closePublish() {
    if (publishing.value) return;
    publishOpen.value = false;
}

function setPublishRating(value) {
    if (!publishing.value) publishRating.value = value;
}

async function confirmPublish() {
    if (!projectId.value) {
        showToast('Simpan project dulu sebelum dipublikasikan.');
        return;
    }
    if (!canPublish.value) {
        showToast('Rating dan komentar wajib diisi untuk mempublikasikan.', 'warning');
        return;
    }
    publishing.value = true;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(projectId.value)}/publish`, {
            method: 'POST',
            body: JSON.stringify({
                description: publishDescription.value.trim(),
                rating: publishRating.value,
                comment: publishComment.value.trim(),
            }),
        });
        if (res.ok) {
            publishOpen.value = false;
            showToast('Project berhasil dipublikasikan. Testimoni kamu juga ikut terkirim.', 'success');
        } else {
            showToast(res.data?.error || 'Gagal mempublikasikan project.');
        }
    } catch (e) {
        showToast(e.message || 'Gagal mempublikasikan project.');
    } finally {
        publishing.value = false;
    }
}

// ---- Pencarian & penggantian teks di dalam canvas (Ctrl/Cmd + F) ----
const findOpen = ref(false);
const findQuery = ref('');
const replaceQuery = ref('');
const findIndex = ref(0);
const findInputEl = ref(null);
const replaceInputEl = ref(null);
let findHighlightTimer = null;
const findMarkEls = [];
const findPanelEl = ref(null);
const findPanelPos = ref(null); // {x, y} px; null = posisi default (tengah atas)
let findDrag = null;

const findPanelStyle = computed(() => {
    if (!findPanelPos.value) {
        return { left: '50%', top: '20px', transform: 'translateX(-50%)' };
    }
    return { left: `${findPanelPos.value.x}px`, top: `${findPanelPos.value.y}px` };
});

function onFindDragStart(e) {
    if (e.button !== 0) return;
    const el = findPanelEl.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    findDrag = { startX: e.clientX, startY: e.clientY, left: rect.left, top: rect.top };
    document.addEventListener('mousemove', onFindDragMove);
    document.addEventListener('mouseup', onFindDragEnd);
    e.preventDefault();
}

function onFindDragMove(e) {
    if (!findDrag) return;
    const width = findPanelEl.value?.offsetWidth || 0;
    const height = findPanelEl.value?.offsetHeight || 0;
    const left = Math.min(Math.max(0, findDrag.left + (e.clientX - findDrag.startX)), Math.max(0, window.innerWidth - width));
    const top = Math.min(Math.max(0, findDrag.top + (e.clientY - findDrag.startY)), Math.max(0, window.innerHeight - height));
    findPanelPos.value = { x: left, y: top };
}

function onFindDragEnd() {
    findDrag = null;
    document.removeEventListener('mousemove', onFindDragMove);
    document.removeEventListener('mouseup', onFindDragEnd);
}

// Ambil teks polos dari HTML konten blok (gabungan persis text-node, tanpa tag).
// Pakai DOM agar entitas HTML (mis. &nbsp;) ikut ter-decode sehingga offset
// teks polos selalu cocok dengan text-node DOM saat menyorot/mengganti.
function parseBlockContent(html) {
    const container = document.createElement('div');
    container.innerHTML = html || '';
    const nodes = [];
    const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
    let n;
    while ((n = walker.nextNode())) nodes.push(n);
    return { container, nodes, plain: nodes.map((x) => x.nodeValue).join('') };
}

// Pecah teks menjadi kalimat (dengan offset asli di teks polos).
function splitSentences(text) {
    const out = [];
    if (!text || !text.trim()) return out;
    const re = /[^.!?\n]+[.!?\n]*/g;
    let m;
    while ((m = re.exec(text)) !== null) {
        const raw = m[0];
        const trimmed = raw.trim();
        if (trimmed) out.push({ text: trimmed, start: m.index, end: m.index + raw.length });
    }
    if (!out.length) out.push({ text: text.trim(), start: 0, end: text.length });
    return out;
}

// Daftar kecocokan berbasis KALIMAT (bukan blok). Setiap kalimat yang memuat
// kata kunci dihitung satu kecocokan sehingga jumlahnya presisi & tidak bikin bingung.
const findMatches = computed(() => {
    const q = findQuery.value.trim();
    if (!q) return [];
    const ql = q.toLowerCase();
    const results = [];
    for (const b of canvasBlocks.value) {
        if (NON_TEXT_BLOCK_TYPES.has(b.type)) continue;
        const { plain } = parseBlockContent(b.content);
        if (!plain) continue;
        for (const s of splitSentences(plain)) {
            if (s.text.toLowerCase().includes(ql)) {
                results.push({ uid: b.uid, sentence: s.text, start: s.start, end: s.end });
            }
        }
    }
    return results;
});

const currentFindMatch = computed(() => findMatches.value[findIndex.value] || null);

function clearFindHighlight() {
    if (findHighlightTimer) {
        clearTimeout(findHighlightTimer);
        findHighlightTimer = null;
    }
    if (findMarkEls.length) {
        for (const mark of findMarkEls) {
            const parent = mark.parentNode;
            if (parent) {
                while (mark.firstChild) parent.insertBefore(mark.firstChild, mark);
                parent.removeChild(mark);
            }
        }
        findMarkEls.length = 0;
    }
}

function blockEditorEl(uid) {
    const nodes = canvasEl.value ? Array.from(canvasEl.value.querySelectorAll(`[data-block-uid="${uid}"]`)) : [];
    const el = nodes[nodes.length - 1];
    return el ? el.querySelector('.editor') : null;
}

function rangeForOffsets(root, start, end) {
    const nodes = [];
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let n;
    while ((n = walker.nextNode())) nodes.push(n);
    let pos = 0;
    let startNode = null;
    let startOffset = 0;
    let endNode = null;
    let endOffset = 0;
    for (const node of nodes) {
        const len = node.nodeValue.length;
        if (startNode === null && pos + len >= start) {
            startNode = node;
            startOffset = start - pos;
        }
        if (pos + len >= end) {
            endNode = node;
            endOffset = end - pos;
            break;
        }
        pos += len;
    }
    if (!startNode || !endNode) return null;
    const range = document.createRange();
    range.setStart(startNode, Math.max(0, Math.min(startOffset, startNode.nodeValue.length)));
    range.setEnd(endNode, Math.max(0, Math.min(endOffset, endNode.nodeValue.length)));
    return range;
}

function wrapRangeInMark(root, start, end) {
    const range = rangeForOffsets(root, start, end);
    if (!range) return;
    const mark = document.createElement('mark');
    mark.className = 'find-match-mark';
    try {
        const frag = range.extractContents();
        mark.appendChild(frag);
        range.insertNode(mark);
        findMarkEls.push(mark);
    } catch {
        // Range melintasi batas elemen yang rumit — lewati saja.
    }
}

function highlightFindMatch(match) {
    clearFindHighlight();
    nextTick(() => {
        // Tunggu sebentar agar blok benar-benar dirender oleh virtualisasi.
        findHighlightTimer = setTimeout(() => {
            const editor = blockEditorEl(match.uid);
            if (!editor) return;
            const q = findQuery.value.trim();
            if (!q) return;
            const b = canvasBlocks.value.find((x) => x.uid === match.uid);
            if (!b) return;
            // Sorot hanya kata/frasa yang persis dicari (bukan seluruh kalimat).
            const { plain } = parseBlockContent(b.content);
            const occurrences = findOccurrencesInPlain(plain, q).filter(
                (o) => o.start >= match.start && o.end <= match.end,
            );
            // Bungkus dari belakang agar offset sebelumnya tidak berubah.
            for (let i = occurrences.length - 1; i >= 0; i--) {
                wrapRangeInMark(editor, occurrences[i].start, occurrences[i].end);
            }
        }, 120);
    });
}

function gotoFindMatch(i) {
    if (!findMatches.value.length) return;
    findIndex.value = i;
    const m = findMatches.value[i];
    scrollToBlock(m.uid);
    highlightFindMatch(m);
}

function findNext() {
    if (!findMatches.value.length) return;
    gotoFindMatch((findIndex.value + 1) % findMatches.value.length);
}

function findPrev() {
    if (!findMatches.value.length) return;
    gotoFindMatch((findIndex.value - 1 + findMatches.value.length) % findMatches.value.length);
}

function openFind() {
    findOpen.value = true;
    findIndex.value = 0;
    nextTick(() => {
        findInputEl.value?.focus();
        findInputEl.value?.select();
    });
}

function closeFind() {
    findOpen.value = false;
    findQuery.value = '';
    replaceQuery.value = '';
    findIndex.value = 0;
    findPanelPos.value = null;
    onFindDragEnd();
    clearFindHighlight();
}

watch(findQuery, () => {
    if (!findOpen.value) return;
    findIndex.value = 0;
    if (findMatches.value.length) {
        gotoFindMatch(0);
    } else {
        clearFindHighlight();
    }
});

// ---- Penggantian (replace) berbasis kalimat ----

function findOccurrencesInPlain(plain, query) {
    const ql = query.toLowerCase();
    const lower = plain.toLowerCase();
    const out = [];
    let idx = lower.indexOf(ql);
    while (idx !== -1) {
        out.push({ start: idx, end: idx + query.length });
        idx = lower.indexOf(ql, idx + query.length);
    }
    return out;
}

function replaceNodeRange(nodes, start, end, replacement) {
    let pos = 0;
    for (let i = 0; i < nodes.length; i++) {
        const node = nodes[i];
        const len = node.nodeValue.length;
        const nStart = pos;
        const nEnd = pos + len;
        if (nEnd <= start || nStart >= end) {
            pos = nEnd;
            continue;
        }
        const ls = Math.max(0, start - nStart);
        const le = Math.min(len, end - nStart);
        node.nodeValue = node.nodeValue.slice(0, ls) + replacement + node.nodeValue.slice(le);
        // Bila kata kunci melintasi beberapa text-node (ada tag di tengahnya).
        if (end > nEnd) {
            let remaining = end - nEnd;
            for (let j = i + 1; j < nodes.length && remaining > 0; j++) {
                const nn = nodes[j];
                const take = Math.min(nn.nodeValue.length, remaining);
                nn.nodeValue = nn.nodeValue.slice(take);
                remaining -= take;
            }
        }
        return;
    }
}

function replaceBlockRanges(uid, ranges, replacement) {
    const b = canvasBlocks.value.find((x) => x.uid === uid);
    if (!b || !ranges.length) return;
    const { container, nodes } = parseBlockContent(b.content);
    // Ganti dari belakang agar offset sebelumnya tidak bergeser.
    for (let i = ranges.length - 1; i >= 0; i--) {
        replaceNodeRange(nodes, ranges[i].start, ranges[i].end, replacement);
    }
    b.content = container.innerHTML;
}

function replaceCurrent() {
    const m = currentFindMatch.value;
    if (!m) return;
    const b = canvasBlocks.value.find((x) => x.uid === m.uid);
    if (!b) return;
    const { plain } = parseBlockContent(b.content);
    const inSentence = findOccurrencesInPlain(plain, findQuery.value.trim()).filter(
        (o) => o.start >= m.start && o.end <= m.end,
    );
    if (!inSentence.length) return;
    pushHistory();
    replaceBlockRanges(m.uid, inSentence, replaceQuery.value);
    clearFindHighlight();
    nextTick(() => {
        if (findMatches.value.length) {
            gotoFindMatch(Math.min(findIndex.value, findMatches.value.length - 1));
        } else {
            clearFindHighlight();
        }
    });
}

function replaceAll() {
    if (!findMatches.value.length) return;
    const q = findQuery.value.trim();
    const replacement = replaceQuery.value;
    pushHistory();
    for (const b of canvasBlocks.value) {
        if (NON_TEXT_BLOCK_TYPES.has(b.type)) continue;
        const { plain } = parseBlockContent(b.content);
        if (!plain) continue;
        const ranges = findOccurrencesInPlain(plain, q);
        if (ranges.length) replaceBlockRanges(b.uid, ranges, replacement);
    }
    clearFindHighlight();
    nextTick(() => {
        if (findMatches.value.length) gotoFindMatch(0);
    });
}

// ---- Sitasi & Daftar Pustaka (dari Tulisin Workspace) ----
const citationStyle = ref('APA');

const citationStyleOptions = CSL_STYLES.map((s) => ({ value: s, label: s }));

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

// Tempel beberapa sitasi sekaligus dari Agent AI Canvas (RAG): sitasi
// ditempel di posisi blok terpilih/kursor dan otomatis dicatat ke Daftar
// Pustaka agar posisinya tidak perlu diatur manual.
function insertAgentCitations(payload) {
    const refs = Array.isArray(payload?.refs) ? payload.refs : [];
    if (!refs.length) return;
    const el = document.activeElement;
    const atCaret = el && el.isContentEditable;
    const parts = [];

    for (const raw of refs) {
        // Cocokkan ke referensi Workspace yang sudah tersimpan agar gaya
        // sitasi mengikuti citation style dokumen dan ikut ke Daftar Pustaka.
        const match = allReferences.value.find((r) => {
            const doi = String(r.DOI || r.doi || '').toLowerCase();
            const title = String(r.title || '').toLowerCase();
            return (raw.doi && doi === String(raw.doi).toLowerCase()) || (raw.title && title === String(raw.title).toLowerCase());
        });
        if (match) {
            parts.push(citeReference(match));
        } else if (raw.label) {
            // Belum tersimpan: pakai label ringkas (Penulis, Tahun) tanpa
            // mengarang data sumber lain.
            parts.push(`(${raw.label})`);
        }
    }

    if (!parts.length) {
        showToast('Belum ada sitasi yang bisa ditempel. Simpan referensinya dulu.');
        return;
    }

    const html = parts.join(' ');
    if (atCaret) {
        document.execCommand('insertHTML', false, html);
        el.dispatchEvent(new Event('input', { bubbles: true }));
    } else {
        const b = canvasBlocks.value.find((x) => x.uid === selectedUid.value);
        if (b) {
            b.content = (b.content || '') + html;
        } else {
            // Belum ada blok terpilih: buat paragraf baru di akhir dokumen.
            pushHistory();
            const uid = crypto.randomUUID();
            canvasBlocks.value.push({
                uid,
                type: 'paragraph',
                content: html,
                indent: 0,
                align: 'justify',
                width: 100,
                spacing: 24,
                fontFamily: '',
                fontSize: 0,
                lineHeight: 0,
                color: '',
                caption: '',
                captionPosition: 'below',
                showCaption: true,
                customNumber: '',
                pageTitle: '',
            });
            selectedUid.value = uid;
        }
    }
    showToast(`${parts.length} sitasi ditempel ke canvas dan dicatat di Daftar Pustaka.`);
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
        // Jaga konsistensi: pilihan tunggal selalu ada di daftar multi.
        if (!selectedUids.value.includes(val)) selectedUids.value = [val];
        lastSelectedUid = val;
    } else {
        selectedUids.value = [];
        lastSelectedUid = null;
    }
});

// Pilih blok: klik biasa = satu blok; Ctrl/Cmd+klik = tambah/kurangi satu
// blok; Shift+klik = rentang dari blok terakhir ke blok ini. Perlu karena
// tiap paragraf adalah contenteditable terpisah sehingga seleksi teks biru
// browser tidak bisa melintasi dua paragraf sekaligus.
function handleBlockSelect(uid, e) {
    if (!uid) return;
    if (e && e.shiftKey && lastSelectedUid && lastSelectedUid !== uid) {
        const order = contentBlocks.value.map((b) => b.uid);
        const a = order.indexOf(lastSelectedUid);
        const b = order.indexOf(uid);
        if (a !== -1 && b !== -1) {
            const range = order.slice(Math.min(a, b), Math.max(a, b) + 1);
            selectedUids.value = [...new Set([...selectedUids.value, ...range])];
            selectedUid.value = uid;
            lastSelectedUid = uid;
            return;
        }
    }
    if (e && (e.ctrlKey || e.metaKey)) {
        if (selectedUids.value.includes(uid)) {
            selectedUids.value = selectedUids.value.filter((x) => x !== uid);
            if (selectedUid.value === uid) {
                selectedUid.value = selectedUids.value[selectedUids.value.length - 1] || null;
            }
        } else {
            selectedUids.value = [...selectedUids.value, uid];
            selectedUid.value = uid;
        }
        lastSelectedUid = uid;
        return;
    }
    selectedUid.value = uid;
}

function clearMultiSelection() {
    selectedUid.value = null;
}

// Blok terpilih berurutan sesuai urutan dokumen (untuk gabung/hapus massal).
const selectedBlocksOrdered = computed(() => {
    const set = new Set(selectedUids.value);
    return canvasBlocks.value.filter((b) => set.has(b.uid));
});

// Tipe yang bisa digabung menjadi satu blok teks (paragraf/kutipan).
const MERGEABLE_TEXT_TYPES = new Set(['paragraph', 'quote']);

const canMergeSelected = computed(() =>
    selectedBlocksOrdered.value.length > 1 &&
    selectedBlocksOrdered.value.every((b) => MERGEABLE_TEXT_TYPES.has(b.type)),
);

// Gabungkan blok teks terpilih menjadi satu blok agar seleksi biru bisa
// mencakup seluruh teks sekaligus (satu contenteditable).
function mergeSelectedBlocks() {
    const ordered = selectedBlocksOrdered.value;
    if (ordered.length < 2) return;
    pushHistory();
    const kept = ordered[0];
    kept.content = ordered
        .map((b) => String(b.content || '').trim())
        .filter(Boolean)
        .join(' ');
    const removeSet = new Set(ordered.slice(1).map((b) => b.uid));
    canvasBlocks.value = canvasBlocks.value.filter((b) => !removeSet.has(b.uid));
    selectedUid.value = kept.uid;
    showToast(`${ordered.length} paragraf digabung menjadi satu.`);
}

// Hapus semua blok yang terpilih sekaligus.
function removeSelectedBlocks() {
    const ordered = selectedBlocksOrdered.value;
    if (!ordered.length) return;
    pushHistory();
    const removeSet = new Set(ordered.map((b) => b.uid));
    canvasBlocks.value = canvasBlocks.value.filter((b) => !removeSet.has(b.uid));
    selectedUid.value = null;
    showToast(`${ordered.length} blok dihapus.`);
}

// Penanda saat memuat data dari server/localStorage agar tidak memicu simpan/timestamp
// ulang. Bersifat reaktif supaya bisa menampilkan skeleton "memuat" di canvas.
const isLoading = ref(false);

// Catat waktu edit terakhir & simpan otomatis saat isi canvas berubah.
watch(canvasBlocks, () => {
    if (isLoading.value) return;
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
const frontMatterPosition = ref('bottom-center');
const bodyPosition = ref('bottom-center');
const frontMatterStyle = ref('roman');
const bodyStyle = ref('decimal');
const bodyStart = ref(1);
const pageSettingsOpen = ref(false);
const pageSettingsPos = ref({ x: 0, y: 0 });

// ---- Watermark ----
const watermarkEnabled = ref(false);
const watermarkType = ref('text'); // 'text' | 'image'
const watermarkText = ref('RAHASIA');
const watermarkFontSize = ref(48); // pt
const watermarkColor = ref('#b0b0b0');
const watermarkOpacity = ref(0.15); // 0..1
const watermarkRotation = ref(-30); // derajat (negatif = miring kiri)
const watermarkImage = ref(''); // URL gambar
const watermarkImageWidth = ref(300); // px (lebar gambar watermark)

// Objek watermark yang diteruskan ke komponen render (canvas, preview, print, share).
const watermarkSettings = computed(() => ({
    enabled: watermarkEnabled.value,
    type: watermarkType.value,
    text: watermarkText.value,
    fontSize: watermarkFontSize.value,
    color: watermarkColor.value,
    opacity: watermarkOpacity.value,
    rotation: watermarkRotation.value,
    image: watermarkImage.value,
    imageWidth: watermarkImageWidth.value,
}));

// Simpan otomatis saat pengaturan dokumen berubah.
watch(
    [fontChoice, customFont, pageFontSize, pageLineHeight, pageFormat, pageOrientation, pageMargins, frontMatterPosition, bodyPosition, frontMatterStyle, bodyStyle, bodyStart, citationStyle, citedReferences, watermarkEnabled, watermarkType, watermarkText, watermarkFontSize, watermarkColor, watermarkOpacity, watermarkRotation, watermarkImage, watermarkImageWidth],
    () => {
        if (isLoading.value) return;
        scheduleSave();
    },
    { deep: true },
);
const pageMenu = ref({ open: false, x: 0, y: 0, pIndex: 0 });
const blockMenu = ref({ open: false, x: 0, y: 0, uid: null });

// Kelas posisi untuk sebuah pilihan posisi nomor halaman.
function pageNumberPositionClass(position) {
    switch (position) {
        case 'bottom-left': return 'bottom-3 left-10';
        case 'bottom-right': return 'bottom-3 right-10';
        case 'top-center': return 'top-3 left-0 right-0 text-center';
        case 'top-left': return 'top-3 left-10';
        case 'top-right': return 'top-3 right-10';
        default: return 'bottom-3 left-0 right-0 text-center';
    }
}

// Kelas nomor halaman per halaman (front matter vs isi bisa beda posisi).
function pageNumberClassFor(pIndex) {
    const pos = pIndex < firstBodyPageIndex.value ? frontMatterPosition.value : bodyPosition.value;
    return pos === 'none' ? '' : pageNumberPositionClass(pos);
}

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

        // Paragraf/kutipan yang melebihi sisa ruang halaman dipecah per karakter
        // agar mengisi halaman sampai batas ruler, lalu lanjut di halaman berikutnya
        // (bukan melompatkan seluruh blok ke halaman baru yang menyisakan ruang kosong).
        // Potongan selalu mundur ke batas kata agar tidak memotong tengah kata/kalimat.
        if (splittableTextTypes.includes(block.type) && !forceBreak && h > 0 && acc + h > contentHeightPx.value) {
            const text = htmlToPlainText(block.content);
            const total = text.length;
            if (total > 0) {
                const cpp = total / h; // karakter per piksel (rata-rata)
                const lineH = pageFontSize.value * 96 / 72 * pageLineHeight.value;
                let start = 0;
                let idx = 0;
                while (start < total) {
                    let avail = contentHeightPx.value - acc;
                    // Sisa kurang dari 1 baris: tutup halaman agar tidak membuat remah.
                    if (avail < lineH) {
                        if (current.length) {
                            result.push(current);
                            current = [];
                            acc = 0;
                        }
                        avail = contentHeightPx.value;
                    }
                    // Buffer satu baris agar potongan tidak meluber melewati ruler.
                    const usable = Math.max(lineH, avail - lineH);
                    let end = Math.min(total, start + Math.max(1, Math.floor(usable * cpp)));

                    // Jangan potong di tengah kata: mundurkan ke spasi/titik terdekat
                    // (maksimal 40 karakter ke belakang) agar kalimat tidak terbelah aneh.
                    if (end < total) {
                        const windowStart = Math.max(start + 1, end - 40);
                        const windowText = text.slice(windowStart, end);
                        const boundary = windowText.search(/[\s.]+[^\s.]*$/);
                        if (boundary >= 0) {
                            const adjusted = windowStart + boundary + windowText.slice(boundary).match(/^[\s.]+/)[0].length;
                            if (adjusted > start) end = adjusted;
                        }
                    }

                    current.push({
                        ...block,
                        chunkKey: `${block.uid}#${start}`,
                        sliceStart: start,
                        sliceEnd: end,
                    });
                    acc += (end - start) / cpp;
                    start = end;
                    idx += 1;
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
            // Cegah judul yatim: bila blok terakhir di halaman berjalan adalah
            // heading dan blok berikutnya adalah isi bagiannya, pindahkan heading
            // tersebut ikut ke halaman baru (seperti "keep with next" di Word).
            const last = current[current.length - 1];
            if (last && isHeadingType(last.type) && !isPageBreakType(block.type)) {
                const moved = current.pop();
                acc -= blockHeights[moved.uid] || 0;
                if (current.length) {
                    result.push(current);
                    current = [moved];
                    acc = blockHeights[moved.uid] || 0;
                } else {
                    current = [moved];
                }
            } else {
                result.push(current);
                current = [];
                acc = 0;
            }
        }

        current.push(block);
        acc += h;
    }
    if (current.length) result.push(current);
    pages.value = result;

    // Pengaman: blok yang baru ditambahkan (mis. hasil generate AI) bisa belum
    // terukur (tinggi 0) karena mirror-nya belum ter-render saat pengukuran
    // berjalan. Jadwalkan ulang agar blok tidak menumpuk di satu halaman lalu
    // tembus melebihi ukuran halaman.
    const hasUnmeasured = canvasBlocks.value.some(
        (b) => !blockHeights[b.uid] && String(b.content || '').trim(),
    );
    if (hasUnmeasured && remeasureRetry < 3) {
        remeasureRetry += 1;
        refreshPages();
    } else {
        remeasureRetry = 0;
        nextTick(restoreCaret);
    }
}

// ---- Pelacakan kursor: pertahankan fokus saat blok dipecah antar halaman ----
const focusedBlockUid = ref(null);
const focusedCaretOffset = ref(0);

function caretOffsetIn(el) {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return 0;
    const range = sel.getRangeAt(0);
    const pre = document.createRange();
    pre.selectNodeContents(el);
    pre.setEnd(range.startContainer, range.startOffset);
    return pre.toString().length;
}

// Rekam blok yang sedang difokus + offset kursor (relatif terhadap teks penuh blok).
function trackCaret() {
    const el = document.activeElement;
    if (!el || !el.isContentEditable) {
        focusedBlockUid.value = null;
        return;
    }
    const host = el.closest ? el.closest('[data-block-uid]') : null;
    if (!host) {
        focusedBlockUid.value = null;
        return;
    }
    focusedBlockUid.value = host.getAttribute('data-block-uid');
    const sliceStart = Number(host.getAttribute('data-slice-start') || 0);
    focusedCaretOffset.value = sliceStart + caretOffsetIn(el);
}

function setCaretAt(el, offset) {
    if (!el) return;
    el.focus();
    const sel = window.getSelection();
    if (!sel) return;
    const range = document.createRange();
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    let remaining = offset;
    let node;
    while ((node = walker.nextNode())) {
        if (node.length >= remaining) {
            range.setStart(node, remaining);
            range.collapse(true);
            sel.removeAllRanges();
            sel.addRange(range);
            return;
        }
        remaining -= node.length;
    }
    range.selectNodeContents(el);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
}

// Setelah pagination memecah blok, arahkan kembali fokus ke chunk yang memuat kursor.
function restoreCaret() {
    const uid = focusedBlockUid.value;
    if (!uid) return;
    const offset = focusedCaretOffset.value;
    const block = canvasBlocks.value.find((b) => b.uid === uid);
    if (!block) return;
    if (splittableTextTypes.includes(block.type)) {
        for (const page of pages.value) {
            for (const b of page) {
                if (b.uid === uid && b.sliceStart != null && b.sliceStart <= offset && offset < b.sliceEnd) {
                    const el = document.querySelector(`[data-block-uid="${uid}"][data-slice-start="${b.sliceStart}"] [contenteditable="true"]`);
                    setCaretAt(el, offset - b.sliceStart);
                    return;
                }
            }
        }
    }
    const el = document.querySelector(`[data-block-uid="${uid}"] [contenteditable="true"]`);
    if (el) el.focus();
}

let refreshFrame = null;
let remeasureRetry = 0;
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

    // Ctrl/Cmd + F → buka pencarian teks di dalam canvas (bukan find browser).
    if ((e.ctrlKey || e.metaKey) && (e.key === 'f' || e.key === 'F')) {
        e.preventDefault();
        openFind();
        return;
    }

    // Escape → tutup pencarian canvas bila sedang terbuka.
    if (e.key === 'Escape' && findOpen.value) {
        closeFind();
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
    // Jangan ganggu undo bawaan di kolom input & area tulis (contenteditable),
    // karena undo teks harus mengembalikan ketikan per karakter, bukan mengganti
    // seluruh snapshot blok (yang dulu membuat teks yang diketik ikut hilang).
    if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
        const inFormField = el && el.closest && (el.closest('input, textarea, select') || el.closest('[contenteditable="true"]'));
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
        const inFormField = el && el.closest && (el.closest('input, textarea, select') || el.closest('[contenteditable="true"]'));
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
    // Sinkronkan pustaka referensi Workspace dari server (per akun). Data
    // reaktif, jadi WorkspaceViewer & daftar sitasi akan ter-update otomatis.
    syncWorkspaceReferences();

    if (workspaceView.value) return;
    ensureBuilderQuery();
    try {
        customFonts.value = await listCustomFonts();
        customFonts.value.forEach(registerFontFace);
    } catch {
        customFonts.value = [];
    }
    refreshPages();
    loadCredits();
    loadCreditPricing();
    window.addEventListener('resize', onWindowResize);
    window.addEventListener('beforeunload', flushSave);
    document.addEventListener('keydown', onGlobalKeydown);
    document.addEventListener('selectionchange', trackCaret);

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
    document.removeEventListener('selectionchange', trackCaret);
    onFindDragEnd();
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
                page: pageIndex + 1, // halaman 1-based untuk link
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
// Blok teks mengalir (paragraf & kutipan) yang dipecah per karakter antar halaman.
const splittableTextTypes = ['paragraph', 'quote'];

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

// Ambil teks polos dari HTML blok (dipakai untuk memecah paragraf panjang antar halaman).
function htmlToPlainText(html) {
    const doc = new DOMParser().parseFromString(html || '', 'text/html');
    return doc.body.textContent || '';
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

// Sisipkan blok baru ke canvas (dengan uid unik dan formatting default).
// Khusus cover: generate HTML otomatis dari template standar akademik.
function insertBlock(type, index) {
    const block = {
        uid: crypto.randomUUID(),
        type: type.id,
        content: '',
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
    
    if (type.id === 'cover') {
        // Template cover otomatis menggunakan data user + judul dokumen
        block.content = generateCoverHtml(projectName.value || null);
    } else if (type.id === 'table') {
        block.content = defaultContent('table');
    } else if (type.id === 'bullet' || type.id === 'number') {
        block.content = '<li><br></li>';
    } else if (type.id === 'formula') {
        block.content = 'x^2 + y^2 = r^2';
    } else if (type.id === 'code') {
        block.content = '// Tempel kode kamu di sini\nprint("Hello, World!");';
    }
    
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

function escapeHtmlText(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Gaya sel tabel disamakan dengan TableBlock.vue agar konsisten di canvas.
const AGENT_TABLE_CELL_STYLE = 'border: 1px solid #d4d4d4; padding: 6px 8px; vertical-align: top;';

// Ubah tabel markdown (baris yang diawali `|`) menjadi HTML tabel canvas.
function markdownTableToHtml(lines) {
    const rows = [];
    for (const raw of lines) {
        let s = raw.trim();
        if (s.startsWith('|')) s = s.slice(1);
        if (s.endsWith('|')) s = s.slice(0, -1);
        const cells = s.split('|').map((c) => c.trim());
        if (cells.length) rows.push(cells);
    }
    if (!rows.length) return '';

    const isSeparator = (cells) => cells.length > 0 && cells.every((c) => /^:?-{3,}:?$/.test(c));
    const html = ['<table style="border-collapse: collapse; width: 100%; table-layout: fixed;">'];
    rows.forEach((cells, i) => {
        if (i === 1 && isSeparator(cells)) return; // lewati baris pemisah `---|---`
        const isHeader = i === 0;
        const tds = cells.map((c) => {
            const text = escapeHtmlText(c) || '<br>';
            const inner = isHeader ? `<strong>${text}</strong>` : text;
            return `<td style="${AGENT_TABLE_CELL_STYLE}">${inner}</td>`;
        }).join('');
        html.push(`<tr>${tds}</tr>`);
    });
    html.push('</table>');
    return html.join('');
}

// Ambil isi fenced block ```canvas ... ``` bila ada; selain itu pakai seluruh teks.
function extractCanvasFence(text) {
    const src = String(text || '').replace(/\r\n/g, '\n');
    const m = src.match(/```canvas\s*\n([\s\S]*?)```/i);
    return m ? m[1].trim() : src;
}

// Ubah markdown hasil Agent AI menjadi spec blok canvas.
// Konvensi: `#` = chapter, `##` = h1, `###` = h2, ... `###########` = h10.
function parseAgentToBlocks(text) {
    const lines = extractCanvasFence(text).split('\n');
    const blocks = [];
    let para = [];
    let list = null;   // { type: 'bullet'|'number', items: [] }
    let quote = [];    // baris kutipan `>`
    let table = [];    // baris tabel markdown `|`
    let code = null;   // { lines: [] } untuk fenced code block
    // Penanda halaman khusus yang berdiri sendiri (`@cover` / `@abstract` tanpa
    // isi inline): hanya baris isi TUNGGAL berikutnya yang menjadi blok
    // abstract. Baris setelahnya kembali menjadi paragraf normal agar
    // generate bab biasa tidak ikut berubah menjadi ABSTRACT semua.
    let pendingPageType = null; // 'abstract' | null (cover tidak pakai ini lagi)

    const flushPara = () => {
        if (para.length) {
            // Satu paragraf = satu blok. Pagination yang memecah blok ini antar
            // halaman (per karakter) agar mengisi halaman sampai batas ruler.
            blocks.push({ type: 'paragraph', content: renderMarkdown(para.join(' ')) });
            para = [];
        }
    };
    const flushPendingPage = () => {
        pendingPageType = null;
    };
    const flushList = () => {
        if (list && list.items.length) {
            blocks.push({ type: list.type, content: list.items.map((i) => `<li>${renderMarkdown(i)}</li>`).join('') });
            list = null;
        }
    };
    const flushQuote = () => {
        if (quote.length) {
            blocks.push({ type: 'quote', content: renderMarkdown(quote.join(' ')) });
            quote = [];
        }
    };
    const flushTable = () => {
        if (table.length) {
            const html = markdownTableToHtml(table);
            if (html) blocks.push({ type: 'table', content: html });
            table = [];
        }
    };
    const flushCode = () => {
        if (code !== null) {
            blocks.push({ type: 'code', content: code.lines.join('\n') });
            code = null;
        }
    };

    for (const raw of lines) {
        const line = raw.trim();

        // Fenced code block ``` ... ```
        if (/^```/.test(line)) {
            flushPara(); flushList(); flushQuote(); flushTable();
            if (code === null) {
                code = { lines: [] };
            } else {
                flushCode();
            }
            continue;
        }
        if (code !== null) {
            code.lines.push(raw);
            continue;
        }

        // Baris kosong: tutup paragraf/list/table yang terbuka.
        if (!line) {
            flushPara(); flushList(); flushQuote(); flushTable();
            continue;
        }

        // Markdown table (baris diawali `|`)
        if (/^\|/.test(line)) {
            flushPara(); flushList(); flushQuote();
            table.push(line);
            continue;
        }

        // Divider --- / *** / ___
        if (/^(-{3,}|\*{3,}|_{3,})$/.test(line)) {
            flushPara(); flushList(); flushQuote(); flushTable();
            blocks.push({ type: 'divider', content: '' });
            continue;
        }

        // Halaman khusus (cover / halaman kosong / abstrak / daftar isi / daftar
        // pustaka): `@cover`, `@page`, `@abstract`, `@toc`, `@references`.
        // Hanya penanda ini yang menjadi blok Page; baris lain tetap
        // paragraf normal seperti sebelumnya. Cover sekarang otomatis generate
        // HTML template standar akademik dari user data (nama, NIM, universitas).
        const pageMark = line.match(/^@(cover|page|abstract|toc|references)\b\s*(.*)$/i);
        if (pageMark) {
            flushPara(); flushList(); flushQuote(); flushTable();
            const kind = pageMark[1].toLowerCase();
            const content = stripInlineMarkdown(pageMark[2].trim());
            if (kind === 'cover') {
                // @cover atau @cover Judul -> generate template akademik otomatis
                blocks.push({ type: 'cover', content: generateCoverHtml(content || null) });
            } else if (kind === 'abstract') {
                if (content) blocks.push({ type: 'abstract', content: renderMarkdown(content) });
                else pendingPageType = 'abstract';
            } else if (kind === 'toc') {
                blocks.push({ type: 'toc', content: '' });
            } else if (kind === 'references') {
                blocks.push({ type: 'references', content: '' });
            } else {
                blocks.push({ type: 'blankPage', pageTitle: content, content: '' });
            }
            continue;
        }

        // Satu baris isi tepat setelah `@abstract` tanpa isi inline.
        if (pendingPageType) {
            flushPara(); flushList(); flushQuote(); flushTable();
            const t = pendingPageType;
            pendingPageType = null;
            blocks.push({ type: t, content: renderMarkdown(line) });
            continue;
        }

        // Gambar ![caption](url)
        const img = line.match(/^!\[([^\]]*)\]\(([^)]+)\)$/);
        if (img) {
            flushPara(); flushList(); flushQuote(); flushTable();
            blocks.push({ type: 'image', content: img[2].trim(), caption: img[1].trim() });
            continue;
        }

        // Kutipan >
        if (/^>\s?/.test(line)) {
            flushPara(); flushList(); flushTable();
            quote.push(line.replace(/^>\s?/, ''));
            continue;
        }

        // Heading: `#` = chapter, `##`..`###########` = h1..h10.
        // Pengecualian: "DAFTAR PUSTAKA" selalu menjadi blok Daftar Pustaka
        // (bukan BAB) dan "DAFTAR ISI" menjadi blok Daftar Isi.
        const heading = line.match(/^(#{1,11})\s+(.*)$/);
        if (heading) {
            flushPara(); flushList(); flushQuote(); flushTable();
            const level = heading[1].length;
            let content = stripInlineMarkdown(heading[2].trim());
            const normHead = content.toLowerCase().replace(/[^a-z\s]/g, '').replace(/\s+/g, ' ').trim();
            if (normHead === 'daftar pustaka' || normHead === 'references' || normHead === 'bibliography') {
                blocks.push({ type: 'references', content: '' });
                continue;
            }
            if (normHead === 'daftar isi' || normHead === 'table of contents') {
                blocks.push({ type: 'toc', content: '' });
                continue;
            }
            if (level === 1) {
                // Nomor bab sudah otomatis; buang awalan "BAB ..." dari AI agar tidak dobel.
                content = content.replace(/^BAB\s+[IVXLCDM0-9]+\s*[:.·\-–—]?\s*/i, '');
                blocks.push({ type: 'chapter', content });
            } else {
                blocks.push({ type: `h${level - 1}`, content });
            }
            continue;
        }

        // Bullet list
        const bullet = line.match(/^[-*]\s+(.*)$/);
        if (bullet) {
            flushPara(); flushQuote(); flushTable();
            if (!list || list.type !== 'bullet') {
                flushList();
                list = { type: 'bullet', items: [] };
            }
            list.items.push(bullet[1].trim());
            continue;
        }

        // Number list
        const number = line.match(/^\d+[.)]\s+(.*)$/);
        if (number) {
            flushPara(); flushQuote(); flushTable();
            if (!list || list.type !== 'number') {
                flushList();
                list = { type: 'number', items: [] };
            }
            list.items.push(number[1].trim());
            continue;
        }

        // Paragraf biasa — jika masih dalam list aktif (belum ada baris kosong),
        // jadikan lanjutan item list terakhir agar penomoran tidak pecah jadi 1..1..1.
        if (list && list.items.length) {
            list.items[list.items.length - 1] += ' ' + line;
            continue;
        }
        // Pengaman teks polos: baris yang tepat "Daftar Pustaka"/"Daftar Isi"
        // (tanpa #) tetap menjadi blok Page yang benar, bukan paragraf.
        const normLine = line.toLowerCase().replace(/[^a-z\s]/g, '').replace(/\s+/g, ' ').trim();
        if (normLine === 'daftar pustaka' || normLine === 'references' || normLine === 'bibliography') {
            flushPara(); flushList(); flushQuote(); flushTable();
            blocks.push({ type: 'references', content: '' });
            continue;
        }
        if (normLine === 'daftar isi' || normLine === 'table of contents') {
            flushPara(); flushList(); flushQuote(); flushTable();
            blocks.push({ type: 'toc', content: '' });
            continue;
        }
        flushList(); flushQuote(); flushTable();
        para.push(line);
    }

    flushPara();
    flushList();
    flushQuote();
    flushTable();
    flushCode();
    flushPendingPage();

    return blocks;
}

function applyAgentToCanvas(payload) {
    const isObj = typeof payload === 'object' && payload !== null;
    const text = isObj ? (payload.text || '') : String(payload || '');
    const mode = isObj && payload.mode ? payload.mode : 'after';

    const specs = parseAgentToBlocks(text);
    if (!specs.length) return;
    const blocks = buildTemplateBlocks({ blocks: specs });

    // Paragraf hasil AI otomatis memakai indentasi baris pertama (format dokumen).
    blocks.forEach((b) => {
        if (b.type === 'paragraph') b.firstLineIndent = true;
    });

    // Mode 'pick' (default chat): setiap bagian (bab) disisipkan satu per satu.
    // Bab yang sudah ada di canvas digantikan, sisanya ditambahkan.
    if (mode === 'pick') {
        agentStructureSections.value = groupBlocksBySection(blocks);
        agentStructureTarget.value = payload.target || '';
        agentStructureOpen.value = true;
        return;
    }

    let index;
    let replace = false;

    if (mode === 'replace' && selectedUid.value) {
        index = canvasBlocks.value.findIndex((b) => b.uid === selectedUid.value);
        replace = index !== -1;
        if (!replace) index = canvasBlocks.value.length;
    } else if (selectedUid.value) {
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
    if (replace) {
        canvasBlocks.value.splice(index, 1, ...blocks);
    } else {
        canvasBlocks.value.splice(index, 0, ...blocks);
    }
    selectedUid.value = blocks[0].uid;
    showToast(replace ? 'Blok terpilih diganti dengan konten agent.' : 'Konten agent ditambahkan ke canvas.');
    agentModalOpen.value = false;
}

// ---- Struktur balasan agent: sisipkan per bagian (bab) ----
// Hasil agent dipecah menjadi beberapa bagian; bagian yang cocok dengan heading
// di canvas digantikan (bukan ditumpuk), sisanya ditambahkan sebagai bab baru.
const agentStructureOpen = ref(false);
const agentStructureSections = ref([]);
// UID tujuan penulisan dari Agent AI (bab/abstrak yang dituju user),
// dipakai untuk menandai baris yang cocok di panel struktur.
const agentStructureTarget = ref('');

function groupBlocksBySection(blocks) {
    const sections = [];
    let current = null;
    for (const b of blocks) {
        // Blok halaman khusus (cover/abstract/blankPage/daftar isi/daftar
        // pustaka) selalu menjadi seksi sendiri agar tidak tercampur dan
        // Daftar Pustaka tidak digantikan dengan paragraf/bab.
        if (b.type === 'cover' || b.type === 'abstract' || b.type === 'blankPage' || b.type === 'toc' || b.type === 'references') {
            sections.push({ blocks: [b] });
            current = null;
            continue;
        }
        if (b.type === 'chapter' || current === null) {
            current = { blocks: [] };
            sections.push(current);
        }
        current.blocks.push(b);
    }
    return sections;
}

function sectionMatches(section) {
    const first = section.blocks[0];
    if (!first) return { match: false, index: -1, end: -1 };

    // Halaman khusus (cover/abstract/blankPage/daftar isi/daftar pustaka):
    // cari halaman dengan tipe yang sama.
    if (first.type === 'cover' || first.type === 'abstract' || first.type === 'blankPage' || first.type === 'toc' || first.type === 'references') {
        const idx = canvasBlocks.value.findIndex((b) => b.type === first.type);
        if (idx === -1) return { match: false, index: -1, end: -1 };
        return { match: true, index: idx, end: idx + 1 };
    }

    if (first.type !== 'chapter') return { match: false, index: -1, end: -1 };

    const title = normalizeTitle(first.content);
    const chapters = [];
    canvasBlocks.value.forEach((b, i) => {
        if (b.type === 'chapter') chapters.push({ uid: b.uid, index: i, title: normalizeTitle(b.content) });
    });

    const exact = chapters.find((c) => c.title !== '' && c.title === title);
    if (exact) {
        const next = chapters.find((c) => c.index > exact.index);
        return { match: true, index: exact.index, end: next ? next.index : canvasBlocks.value.length };
    }

    // Fallback: kemiripan judul (mengandung salah satu kata kunci utama).
    const words = title.split(' ').filter((w) => w.length > 3);
    const similar = chapters.find((c) => words.length && words.some((w) => c.title.includes(w)));
    if (similar) {
        const next = chapters.find((c) => c.index > similar.index);
        return { match: true, index: similar.index, end: next ? next.index : canvasBlocks.value.length };
    }

    return { match: false, index: -1, end: -1 };
}

function normalizeTitle(text) {
    return String(text || '')
        .toLowerCase()
        .replace(/^(bab|bagian)\s+[ivxlcdm0-9]+\s*[:.\-]?\s*/i, '')
        .replace(/[^a-z0-9\s]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function structureRows() {
    // Samakan tujuan penulisan dengan judul bagian (tanpa nomor/awalan BAB)
    // agar baris yang dituju user bisa ditandai di panel.
    const targetOpt = agentTargets.value.find((t) => t.id === agentStructureTarget.value);
    const targetNorm = targetOpt && targetOpt.kind !== 'new' ? normalizeTitle(targetOpt.label) : '';
    return agentStructureSections.value.map((section, i) => {
        const first = section.blocks[0] || {};
        const isPageSection = first.type === 'cover' || first.type === 'abstract' || first.type === 'blankPage' || first.type === 'toc' || first.type === 'references';
        const title = isPageSection
            ? (first.type === 'cover' ? 'Cover' : first.type === 'abstract' ? 'Abstrak' : first.type === 'toc' ? 'Daftar Isi' : first.type === 'references' ? 'Daftar Pustaka' : (first.pageTitle || 'Halaman baru').trim() || 'Halaman baru')
            : String(first.content || 'Bagian').trim();
        const info = sectionMatches(section);
        const norm = normalizeTitle(title);
        return {
            index: i,
            title,
            count: section.blocks.length,
            matched: info.match,
            action: info.match ? 'replace' : 'add',
            index0: info.index,
            end0: info.end,
            targeted: targetNorm !== '' && norm !== '' && (norm === targetNorm || norm.includes(targetNorm) || targetNorm.includes(norm)),
        };
    });
}

// Terapkan satu bagian ke canvas (satu per satu agar mudah dikontrol).
// Halaman khusus (cover/abstract/blankPage) selalu disisipkan pada halaman
// sendiri: satu pemecah halaman di depan (kecuali di posisi paling awal) dan
// satu di belakang (kecuali tidak ada blok sesudahnya), mengikuti standar blok Page.
function pageBreakBlock() {
    return { type: 'pageBreak', uid: crypto.randomUUID(), content: '' };
}

function wrapPageSection(blocks) {
    const out = [];
    const firstIsPageBreak = canvasBlocks.value.length === 0;
    if (!firstIsPageBreak) {
        const last = canvasBlocks.value[canvasBlocks.value.length - 1];
        if (!last || last.type !== 'pageBreak') out.push(pageBreakBlock());
    }
    out.push(...blocks);
    out.push(pageBreakBlock());
    return out;
}

function applyStructureSection(i) {
    const section = agentStructureSections.value[i];
    if (!section) return;
    const info = sectionMatches(section);
    const first = section.blocks[0] || {};
    const isPageSection = first.type === 'cover' || first.type === 'abstract' || first.type === 'blankPage';
    const title = isPageSection
        ? (first.type === 'cover' ? 'Cover' : first.type === 'abstract' ? 'Abstrak' : (first.pageTitle || 'Halaman').trim() || 'Halaman')
        : String(first.content || 'Bagian').trim();

    pushHistory();
    if (info.match) {
        if (isPageSection) {
            // Gantikan halaman lama (bersihkan pemecah halaman yang menempel).
            let start = info.index;
            let end = info.end;
            if (start > 0 && canvasBlocks.value[start - 1]?.type === 'pageBreak') start -= 1;
            if (end < canvasBlocks.value.length && canvasBlocks.value[end]?.type === 'pageBreak') end += 1;
            canvasBlocks.value.splice(start, end - start, ...wrapPageSection(section.blocks));
        } else {
            canvasBlocks.value.splice(info.index, info.end - info.index, ...section.blocks);
        }
    } else if (isPageSection) {
        // Tambahkan sebagai halaman terpisah di akhir dokumen.
        canvasBlocks.value.push(...wrapPageSection(section.blocks));
    } else {
        canvasBlocks.value.push(...section.blocks);
    }
    selectedUid.value = section.blocks[0].uid;
    showToast(info.match ? `Bagian "${title}" digantikan.` : `Bagian "${title}" ditambahkan.`);
    agentStructureSections.value.splice(i, 1);

    if (!agentStructureSections.value.length) {
        agentStructureOpen.value = false;
        agentStructureTarget.value = '';
        agentModalOpen.value = false;
    }
}

// Terapkan seluruh bagian sekaligus (dari bagian bawah agar indeks tetap valid).
function applyAllStructureSections() {
    const rows = structureRows().slice().sort((a, b) => b.index0 - a.index0);
    pushHistory();
    for (const row of rows) {
        const section = agentStructureSections.value[row.index];
        if (!section) continue;
        const first = section.blocks[0] || {};
        const isPageSection = first.type === 'cover' || first.type === 'abstract' || first.type === 'blankPage';
        const info = sectionMatches(section);
        if (info.match) {
            if (isPageSection) {
                let start = info.index;
                let end = info.end;
                if (start > 0 && canvasBlocks.value[start - 1]?.type === 'pageBreak') start -= 1;
                if (end < canvasBlocks.value.length && canvasBlocks.value[end]?.type === 'pageBreak') end += 1;
                canvasBlocks.value.splice(start, end - start, ...wrapPageSection(section.blocks));
            } else {
                canvasBlocks.value.splice(info.index, info.end - info.index, ...section.blocks);
            }
        } else if (isPageSection) {
            canvasBlocks.value.push(...wrapPageSection(section.blocks));
        } else {
            canvasBlocks.value.push(...section.blocks);
        }
    }
    agentStructureSections.value = [];
    agentStructureOpen.value = false;
    agentStructureTarget.value = '';
    agentModalOpen.value = false;
    showToast('Seluruh bagian diterapkan ke canvas.');
}

function closeAgentStructure() {
    agentStructureOpen.value = false;
    agentStructureSections.value = [];
    agentStructureTarget.value = '';
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

// Potong saldo kredit untuk suatu fitur. Biaya dihitung server-side dari reason.
// Return true bila berhasil.
async function spendCredits(reason, { quantity = 1, pages = 0 } = {}) {
    try {
        const res = await request('/api/wallet/spend', {
            method: 'POST',
            body: JSON.stringify({ reason, quantity, pages }),
        });
        if (res.ok) {
            totalCredits.value = res.data.balance ?? totalCredits.value;
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
// Ringkasan isi canvas untuk dikirim ke backend AI. Versi penuh hanya dipakai
// saat canvas masih kecil; untuk dokumen besar dipakai ringkasan struktur agar
// prompt tidak membengkak dan AI tidak timeout.
const canvasSummary = computed(() => {
    const total = contentBlocks.value.length;
    if (total === 0) return 'Canvas masih kosong.';

    // Di bawah 40 blok: kirim daftar ringkas (aman & hemat).
    if (total <= 40) {
        return contentBlocks.value
            .map((b, i) => `${i + 1}. [${typeLabel(b.type)}] ${blockPreview(b) || '(kosong)'}`)
            .join('\n');
    }

    // Dokumen besar: struktur (heading/bagian) + bagian yang sedang aktif saja.
    return [
        `Dokumen memiliki ${total} blok. Ringkasan struktur:`,
        documentStructure.value,
        'Bagian yang sedang dikerjakan:',
        activeBlockContext.value,
    ].filter(Boolean).join('\n\n');
});

// Agent AI Canvas: kondisi kosong + daftar komponen blok (sidebar kiri).
const agentEmpty = computed(() => contentBlocks.value.length === 0);
const agentBlockTypes = computed(() => blockTypes.map(({ id, label }) => ({ id, label })));

// Daftar tujuan penulisan untuk Agent AI Canvas (parameter thinking: user
// sedang bertanya untuk bagian mana). Diisi dari struktur dokumen yang ada.
const agentTargets = computed(() => {
    const list = [];
    for (const b of contentBlocks.value) {
        if (b.type === 'abstract') {
            list.push({ id: b.uid, label: 'Abstrak', kind: 'abstract' });
        } else if (b.type === 'chapter') {
            const num = numberingMap.value[b.uid] || '';
            const text = blockPlainText(b) || 'Bab tanpa judul';
            list.push({ id: b.uid, label: `${num ? num + ' ' : ''}${text}`.trim(), kind: 'chapter' });
        }
    }
    list.push({ id: 'new', label: 'Bagian baru', kind: 'new' });
    return list;
});

// Teks penuh isi canvas (judul, bab, abstrak, paragraf) untuk ekstraksi kata
// kunci pada fitur "Cari dari isi dokumen" di Agent AI Canvas. Memakai teks
// utuh (bukan pratinjau 24 karakter) agar kata kunci lebih kaya.
const agentCanvasText = computed(() =>
    contentBlocks.value
        .map((b) => {
            const raw = String(b.content || b.caption || '').replace(/<[^>]*>/g, ' ');
            return [b.pageTitle, raw].filter(Boolean).join(' ');
        })
        .filter((t) => t.trim())
        .join('\n'),
);

// Tujuan bawaan saat modal dibuka: ikuti posisi blok yang sedang dipilih
// (blok abstrak, atau bab terdekat di atasnya), agar user langsung tahu
// sedang bertanya untuk bagian mana.
const agentDefaultTarget = computed(() => {
    const b = selectedBlock.value;
    if (!b) return '';
    if (b.type === 'abstract') return b.uid;
    const all = contentBlocks.value;
    const idx = all.findIndex((x) => x.uid === b.uid);
    for (let i = idx; i >= 0; i--) {
        if (all[i].type === 'chapter') return all[i].uid;
    }
    return '';
});

function openAgent() {
    closePageSettings();
    closePageMenu();
    closeBlockMenu();
    agentModalOpen.value = true;
}

const aiGenInput = ref('');
const aiGenOutput = ref('');
const aiGenLoading = ref(false);

// Blok unik pada halaman aktif (currentPage bersifat 1-based).
const currentPageBlocks = computed(() => {
    const idx = currentPage.value - 1;
    return idx >= 0 && idx < pages.value.length ? flatPageBlocks(idx) : [];
});

// Ringkasan struktur seluruh dokumen (hanya heading/bagian) agar AI memahami
// posisi & alur dokumen tanpa mengirim seluruh isi (hemat token & fokus).
const documentStructure = computed(() => {
    const lines = contentBlocks.value
        .filter((b) => isHeadingType(b.type) || ['abstract', 'toc', 'listTables', 'listFigures', 'references'].includes(b.type))
        .map((b) => {
            const num = numberingMap.value[b.uid] || '';
            const text = blockPlainText(b) || blockPreview(b);
            return `${num ? num + ' ' : ''}[${typeLabel(b.type)}] ${text || '(kosong)'}`;
        });
    return lines.length ? lines.join('\n') : 'Struktur dokumen masih kosong.';
});

// Konteks halaman aktif (blok apa saja yang ada di halaman yang sedang dilihat).
const currentPageContext = computed(() => {
    const blocks = currentPageBlocks.value;
    if (!blocks.length) return 'Halaman ini masih kosong.';
    return blocks.map((b, i) => {
        const num = numberingMap.value[b.uid] || '';
        const text = blockPlainText(b) || blockPreview(b);
        return `${i + 1}. ${num ? num + ' ' : ''}[${typeLabel(b.type)}] ${text || '(kosong)'}`;
    }).join('\n');
});

// Konteks blok yang sedang dipilih (bab terdekat + blok aktif + blok berikutnya).
const activeBlockContext = computed(() => {
    const b = selectedBlock.value;
    if (!b) return currentPageContext.value;
    const all = contentBlocks.value;
    const idx = all.findIndex((x) => x.uid === b.uid);
    const parts = [];
    for (let i = idx - 1; i >= 0; i--) {
        if (isHeadingType(all[i].type)) {
            parts.push(`Bagian: ${numberingMap.value[all[i].uid] || ''} ${blockPlainText(all[i]) || typeLabel(all[i].type)}`);
            break;
        }
    }
    parts.push(`Blok aktif [${typeLabel(b.type)}]: ${blockPlainText(b) || '(kosong)'}`);
    if (idx >= 0 && all[idx + 1]) {
        parts.push(`Blok setelahnya [${typeLabel(all[idx + 1].type)}]: ${blockPlainText(all[idx + 1]) || '(kosong)'}`);
    }
    return parts.join('\n');
});

const blockAiPrompts = computed(() => {
    const t = selectedBlock.value?.type;
    if (isHeadingType(t)) {
        return ['Tuliskan poin penting untuk bagian ini', 'Kembangkan judul menjadi paragraf pengantar'];
    }
    if (t === 'paragraph') {
        return ['Perbaiki tata bahasa paragraf ini', 'Kembangkan paragraf ini menjadi lebih lengkap'];
    }
    if (t === 'abstract') return ['Tulis abstrak 200 kata'];
    if (t === 'quote') return ['Buatkan kutipan singkat terkait topik'];
    return ['Tuliskan isi untuk bagian ini'];
});

// Aksi cepat pada tab AI (level halaman) yang menyesuaikan isi halaman aktif.
const pageAiPrompts = computed(() => {
    const blocks = currentPageBlocks.value;
    if (!blocks.length) {
        return ['Tulis paragraf pembuka untuk halaman ini', 'Buatkan poin-poin utama untuk bagian ini'];
    }
    const lastParagraph = blocks.slice().reverse().find((b) => b.type === 'paragraph' && blockPlainText(b));
    if (lastParagraph) {
        return ['Lanjutkan paragraf terakhir di halaman ini', 'Tulis paragraf baru yang masih satu topik', 'Ringkas isi halaman ini'];
    }
    return ['Tulis paragraf untuk bagian ini', 'Kembangkan judul ini menjadi paragraf', 'Buat kalimat pembuka untuk bagian ini'];
});

// Referensi Workspace dalam bentuk ringkas untuk konteks sitasi AI
// (label disalin apa adanya agar AI tidak mengubah ejaan/tahun).
function agentReferencePayload() {
    return allReferences.value.slice(0, 50).map((r) => {
        const authors = Array.isArray(r.author)
            ? r.author.map((a) => a?.family).filter(Boolean).join(', ')
            : String(r.author || '');
        const year = r.issued?.['date-parts']?.[0]?.[0] || r.year || '';
        const title = String(r.title || '').trim();
        const label = [authors && `${authors} (${year})`, title].filter(Boolean).join('. ');
        const doi = r.DOI || r.doi || '';
        const link = r.URL || r.url || (doi ? `https://doi.org/${doi}` : '') || r._fileUrl || '';
        // id (ref_id) dikirim agar server bisa mencocokkan hasil pencarian
        // relevansi (RAG) ke referensi ini, tanpa mengirim seluruh isi/abstrak.
        return { id: String(r.id || ''), label, link: String(link || '').trim() };
    }).filter((r) => r.label);
}

// Dipanggil saat referensi baru disimpan lewat panel "Cari referensi" di
// Agent Canvas. Sinkronkan ulang pustaka Workspace agar referensi tersebut
// langsung tersedia sebagai sumber terverifikasi untuk AI.
function onReferenceSaved() {
    syncWorkspaceReferences();
    showToast('Referensi berhasil disimpan ke Workspace.');
}

// Generate konten blok: pakai agent canvas agar hasil terstruktur (heading/
// paragraf/list) lalu langsung menggantikan blok terpilih dengan format yang sesuai.
async function generateBlockContent() {
    const prompt = aiGenInput.value.trim();
    const block = selectedBlock.value;
    if (!prompt || !block) return;
    if (!(await spendCredits('ai_generate'))) return;
    aiGenLoading.value = true;
    try {
        const res = await requestAiGenerate({
            agent: 'canvas',
            message: prompt,
            context: [documentStructure.value, activeBlockContext.value].filter(Boolean).join('\n\n'),
            uuid: projectId.value,
            blockTypes: [block.type],
            references: agentReferencePayload(),
        });
        if (res.ok) {
            applyAgentToCanvas({ text: res.data?.reply || '', mode: 'replace' });
        } else {
            showToast(res.data?.error || 'Gagal menghubungi AI.');
        }
    } catch {
        showToast('Gagal menghubungi AI. Coba lagi.');
    } finally {
        aiGenLoading.value = false;
        aiGenInput.value = '';
    }
}

// Generate paragraf untuk halaman aktif (dari tab AI, tanpa blok terpilih).
async function generatePageContent() {
    const prompt = aiGenInput.value.trim();
    if (!prompt) return;
    if (!(await spendCredits('ai_generate'))) return;
    aiGenLoading.value = true;
    try {
        const res = await requestAiGenerate({
            agent: 'copilot',
            message: prompt,
            context: [documentStructure.value, currentPageContext.value].filter(Boolean).join('\n\n'),
            uuid: projectId.value,
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

// Sisipkan hasil generate halaman (markdown) sebagai blok baru di halaman aktif.
function insertPageContent() {
    if (!aiGenOutput.value) return;
    applyAgentToCanvas({ text: aiGenOutput.value, mode: 'after' });
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
const plagiarismConfirmOpen = ref(false);
const plagiarismPendingBlockUid = ref(null);

// Tipe blok yang tidak punya teks tulis user (dilewati saat mengumpulkan teks).
const NON_TEXT_BLOCK_TYPES = new Set([
    'image', 'spacer', 'divider', 'pageBreak', 'formula', 'table',
    'toc', 'listTables', 'listFigures', 'references',
]);

function blockPlainText(b) {
    return (b?.content || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function openPlagiarismCheck(blockUid = null) {
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

    // Konfirmasi dulu sebelum memotong koin (harga diatur admin).
    plagiarismPendingBlockUid.value = blockUid;
    plagiarismConfirmOpen.value = true;
}

function cancelPlagiarismCheck() {
    plagiarismConfirmOpen.value = false;
    plagiarismPendingBlockUid.value = null;
}

async function confirmPlagiarismCheck() {
    const blockUid = plagiarismPendingBlockUid.value;
    plagiarismConfirmOpen.value = false;
    plagiarismPendingBlockUid.value = null;
    if (!(await spendCredits('plagiarism_check'))) return;
    await runPlagiarismCheck(blockUid);
}

async function runPlagiarismCheck(blockUid = null) {
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
        const res = await requestAiGenerate({
            agent: 'plagiarism',
            message: 'Cek kemiripan teks berikut dan berikan saran parafrase agar di bawah 20%.',
            context: text || 'Tidak ada teks.',
            uuid: projectId.value,
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
    if (!(await spendCredits('plagiarism_paraphrase'))) return;
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
const turnitinConfirmOpen = ref(false);
const turnitinScreeningOpen = ref(false);
const turnitinScreeningProgress = ref(0);
const turnitinScreeningStatus = ref('');
const turnitinScreeningPages = ref(0);

function openTurnitinOptimizer() {
    closePageMenu();

    const textBlocks = canvasBlocks.value.filter(
        (b) => !NON_TEXT_BLOCK_TYPES.has(b.type) && blockPlainText(b),
    );

    if (!textBlocks.length) {
        showToast('Tidak ada teks untuk dioptimasi.');
        return;
    }

    // Konfirmasi dulu sebelum memotong koin (harga diatur admin).
    turnitinConfirmOpen.value = true;
}

function cancelTurnitinCheck() {
    turnitinConfirmOpen.value = false;
}

async function confirmTurnitinCheck() {
    turnitinConfirmOpen.value = false;
    if (!(await spendCredits('turnitin_optimize'))) return;
    await runTurnitinCheck();
}

// Durasi animasi pemindaian bergantung jumlah halaman & jumlah karakter.
function estimateTurnitinDuration(totalChars, totalPages) {
    const perChar = Math.min(3500, totalChars / 300);
    const duration = 1500 + totalPages * 400 + perChar;
    return Math.min(15000, Math.max(3000, duration));
}

// Animasi screening halaman: dari halaman pertama sampai terakhir, tanpa bisa
// berpindah/dibatalkan oleh user selama proses berlangsung.
function runTurnitinScreening(totalPages, totalChars) {
    turnitinScreeningOpen.value = true;
    turnitinScreeningProgress.value = 0;
    turnitinScreeningPages.value = totalPages;
    turnitinScreeningStatus.value = `Menyiapkan pemindaian ${totalPages} halaman…`;

    const duration = estimateTurnitinDuration(totalChars, totalPages);
    const started = Date.now();

    return new Promise((resolve) => {
        const tick = () => {
            const elapsed = Date.now() - started;
            const p = Math.min(100, Math.round((elapsed / duration) * 100));
            turnitinScreeningProgress.value = p;
            const current = Math.max(1, Math.min(totalPages, Math.ceil((p / 100) * totalPages)));
            turnitinScreeningStatus.value = `Memindai halaman ${current} dari ${totalPages}…`;
            if (p >= 100) {
                turnitinScreeningStatus.value = 'Pemindaian selesai. Menyiapkan hasil…';
                resolve();
                return;
            }
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });
}

async function runTurnitinCheck() {
    const textBlocks = canvasBlocks.value.filter(
        (b) => !NON_TEXT_BLOCK_TYPES.has(b.type) && blockPlainText(b),
    );

    if (!textBlocks.length) {
        showToast('Tidak ada teks untuk dioptimasi.');
        return;
    }

    turnitinLoading.value = true;
    turnitinResult.value = null;

    const text = textBlocks
        .map((b) => blockPlainText(b))
        .join('\n\n');

    const totalPages = Math.max(1, pages.value.length);
    const totalChars = text.length;

    // Jalankan animasi pemindaian + request AI secara paralel; hasil baru
    // ditampilkan setelah keduanya selesai (user tidak bisa berpindah selama ini).
    const screening = runTurnitinScreening(totalPages, totalChars);
    const aiRequest = requestAiGenerate({
        agent: 'turnitin',
        message: 'Periksa kemiripan teks ini dengan sumber lain, lalu tulis ulang kalimat yang mirip agar skor kemiripan turun.',
        context: text || 'Tidak ada teks.',
        uuid: projectId.value,
    }).catch(() => ({ ok: false, status: 0, data: { error: 'Gagal menghubungi AI. Coba lagi.' } }));

    const [res] = await Promise.all([aiRequest, screening]);

    turnitinScreeningOpen.value = false;
    turnitinScreeningProgress.value = 100;

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

    turnitinLoading.value = false;
    turnitinOpen.value = true;
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
    turnitinConfirmOpen.value = false;
    turnitinScreeningOpen.value = false;
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

async function loadAiResults() {
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

async function openAiHistory() {
    closePageMenu();
    aiHistoryOpen.value = true;
    await loadAiResults();
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

// Handle navigasi dari Daftar Isi: scroll ke bagian yang dipilih (heading/chapter).
function handleTocNavigate(uid) {
    if (!uid) return;
    nextTick(() => scrollToBlock(uid));
}

function blockHasContent(b) {
    if (!b) return false;
    if (b.type === 'spacer' || b.type === 'divider') return false;
    if (b.type === 'image') return Boolean(b.content && String(b.content).trim());
    const text = (b.content || b.caption || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return Boolean(text);
}

function requestDeleteBlock() {
    if (selectedBlocksOrdered.value.length > 1) {
        if (selectedBlocksOrdered.value.some((b) => blockHasContent(b))) {
            deleteConfirmOpen.value = true;
        } else {
            removeSelectedBlocks();
        }
        return;
    }
    if (!selectedBlock.value) return;
    if (blockHasContent(selectedBlock.value)) {
        deleteConfirmOpen.value = true;
    } else {
        removeBlock();
    }
}

function confirmDeleteBlock() {
    deleteConfirmOpen.value = false;
    if (selectedBlocksOrdered.value.length > 1) {
        removeSelectedBlocks();
        return;
    }
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
    selectedUids.value = selectedUids.value.filter((x) => x !== uid);
    if (selectedUid.value === uid) selectedUid.value = selectedUids.value[selectedUids.value.length - 1] || null;
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
    imageSelectTarget.value = 'block';
    imageManagerOpen.value = true;
}

function triggerWatermarkImageUpload() {
    imageSelectTarget.value = 'watermark';
    imageManagerOpen.value = true;
}

function onImageSelect(item) {
    if (imageSelectTarget.value === 'watermark') {
        if (item?.url) watermarkImage.value = item.url;
    } else if (selectedBlock.value && item?.url) {
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
    if (!(await spendCredits('font_upload', { quantity: files.length }))) return;
    for (const file of files) {
        const font = await addCustomFont(file);
        registerFontFace(font);
        customFonts.value = await listCustomFonts();
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
function showToast(message, type = 'info') {
    toast(message, type);
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

// Ubah teks judul bab menjadi UPPERCASE tanpa merusak struktur HTML di dalamnya.
function uppercaseHtmlText(html) {
    if (!html) return String(html || '');
    const el = document.createElement('div');
    el.innerHTML = String(html);
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const n of nodes) {
        const upper = (n.nodeValue || '').toUpperCase();
        if (n.nodeValue !== upper) n.nodeValue = upper;
    }
    return el.innerHTML;
}

// Pastikan seluruh blok bab selalu tersimpan sebagai UPPERCASE.
function normalizeChapterBlocks(blocks) {
    if (!Array.isArray(blocks)) return blocks;
    return blocks.map((b) =>
        b && b.type === 'chapter' && b.content ? { ...b, content: uppercaseHtmlText(b.content) } : b,
    );
}

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
        pageNumberPosition: bodyPosition.value,
        frontMatterPosition: frontMatterPosition.value,
        bodyPosition: bodyPosition.value,
        frontMatterStyle: frontMatterStyle.value,
        bodyStyle: bodyStyle.value,
        bodyStart: bodyStart.value,
        watermarkEnabled: watermarkEnabled.value,
        watermarkType: watermarkType.value,
        watermarkText: watermarkText.value,
        watermarkFontSize: watermarkFontSize.value,
        watermarkColor: watermarkColor.value,
        watermarkOpacity: watermarkOpacity.value,
        watermarkRotation: watermarkRotation.value,
        watermarkImage: watermarkImage.value,
        watermarkImageWidth: watermarkImageWidth.value,
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
    if (typeof data.frontMatterPosition === 'string') frontMatterPosition.value = data.frontMatterPosition;
    else if (typeof data.pageNumberPosition === 'string') frontMatterPosition.value = data.pageNumberPosition;
    if (typeof data.bodyPosition === 'string') bodyPosition.value = data.bodyPosition;
    else if (typeof data.pageNumberPosition === 'string') bodyPosition.value = data.pageNumberPosition;
    if (typeof data.frontMatterStyle === 'string') frontMatterStyle.value = data.frontMatterStyle;
    if (typeof data.bodyStyle === 'string') bodyStyle.value = data.bodyStyle;
    if (typeof data.bodyStart === 'number') bodyStart.value = data.bodyStart;
    if (typeof data.watermarkEnabled === 'boolean') watermarkEnabled.value = data.watermarkEnabled;
    if (typeof data.watermarkType === 'string') watermarkType.value = data.watermarkType;
    if (typeof data.watermarkText === 'string') watermarkText.value = data.watermarkText;
    if (typeof data.watermarkFontSize === 'number') watermarkFontSize.value = data.watermarkFontSize;
    if (typeof data.watermarkColor === 'string') watermarkColor.value = data.watermarkColor;
    if (typeof data.watermarkOpacity === 'number') watermarkOpacity.value = data.watermarkOpacity;
    if (typeof data.watermarkRotation === 'number') watermarkRotation.value = data.watermarkRotation;
    if (typeof data.watermarkImage === 'string') watermarkImage.value = data.watermarkImage;
    if (typeof data.watermarkImageWidth === 'number') watermarkImageWidth.value = data.watermarkImageWidth;
    if (typeof data.citationStyle === 'string') citationStyle.value = data.citationStyle;
    if (Array.isArray(data.citedReferences)) citedReferences.value = data.citedReferences;
    if (Array.isArray(data.hiddenTocUids)) hiddenTocUids.value = data.hiddenTocUids.filter((x) => typeof x === 'string');
    if (Array.isArray(data.blocks)) canvasBlocks.value = normalizeChapterBlocks(data.blocks);
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
    isLoading.value = true;
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
            isLoading.value = false;
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

    isLoading.value = true;
    applyProjectData(payload);
    if (typeof data.version === 'number') docVersion.value = data.version;

    // Cache ke localStorage agar pembukaan berikutnya cepat & offline-safe.
    try {
        localStorage.setItem(storageKey.value, JSON.stringify({ ...payload, version: docVersion.value }));
    } catch {
        // abaikan jika localStorage tidak tersedia / penuh
    }

    nextTick(() => {
        isLoading.value = false;
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

// Kutip nama font bila mengandung spasi (mis. "Times New Roman") agar valid di CSS/Word.
function quoteFontFamily(name) {
    const n = String(name || '').trim();
    if (!n) return '';
    return /\s/.test(n) ? `"${n.replace(/"/g, '')}"` : n;
}

// Gaya inline dari properti formatting blok — selaras dengan CanvasBlock.vue.
function blockStyle(b) {
    const parts = [];
    const align = b.align || 'left';
    if (align === 'center') parts.push('text-align:center');
    else if (align === 'right') parts.push('text-align:right');
    else if (align === 'justify') parts.push('text-align:justify');
    else parts.push('text-align:left');
    if (b.indent) parts.push(`margin-left:${(Number(b.indent) || 0) * 1.5}em`);
    if (b.firstLineIndent) parts.push('text-indent:1.27cm');
    if (b.lineHeight) parts.push(`line-height:${b.lineHeight}`);
    if (b.fontFamily) parts.push(`font-family:${quoteFontFamily(b.fontFamily)}`);
    if (b.fontSize) parts.push(`font-size:${Number(b.fontSize)}pt`);
    if (b.color) parts.push(`color:${b.color}`);
    const cols = Number(b.columns) || 1;
    if (cols > 1) {
        parts.push(`column-count:${cols}`);
        parts.push('column-gap:1.5em');
    }
    return parts.join(';');
}

// Tag heading untuk tipe blok judul (chapter -> h1, h1..h10 -> h1..h6).
function headingTagOf(type) {
    if (type === 'chapter') return 'h1';
    const lvl = headingLevelOf(type);
    if (!lvl) return null;
    return `h${Math.min(6, Math.max(1, lvl))}`;
}

// Teks caption blok (tanpa tag) — selaras dengan ImageBlock/TableBlock.
function captionTextFor(b) {
    return (b.caption || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

// Label caption "Tabel 1.1 Judul" / "Gambar 1.2 Keterangan".
function captionLabelFor(b, kind) {
    if (b.showCaption === false) return '';
    const num = captionNumbers.value[b.uid] || '';
    const text = captionTextFor(b);
    const pieces = [kind];
    if (num) pieces.push(num);
    if (text) pieces.push(text);
    return pieces.length > 1 ? pieces.map(escHtml).join(' ') : '';
}

// Daftar bagian (Daftar Isi / Tabel / Gambar) sebagai tabel tanpa border,
// dengan nomor halaman rata kanan seperti layout cetak.
function renderSectionList(title, entries, kind, style) {
    const rows = entries
        .map((e) => {
            const label =
                kind === 'toc'
                    ? e.number ? `${escHtml(e.number)} ` : ''
                    : kind === 'table'
                        ? `Tabel ${escHtml(e.number || '')} `
                        : `Gambar ${escHtml(e.number || '')} `;
            const text = escHtml(e.text || '(Tanpa judul)');
            const indent = kind === 'toc' ? (Number(e.level) || 0) * 1.5 : 0;
            const page = escHtml(e.pageLabel || '');
            return (
                '<tr>' +
                `<td style="border:none;padding:1pt 0 1pt ${indent}em;vertical-align:baseline;text-align:left">${label}${text}</td>` +
                `<td style="border:none;text-align:right;vertical-align:baseline;white-space:nowrap">${page}</td>` +
                '</tr>'
            );
        })
        .join('');
    const body = rows
        ? `<table style="border-collapse:collapse;width:100%">${rows}</table>`
        : '<p style="text-align:center;color:#999999">(Kosong)</p>';
    return `<h2 style="text-align:center;${style}">${escHtml(title)}</h2>${body}`;
}

// Daftar Pustaka bernomor (1., 2., ...) dari entri referensi yang sudah disitasi.
function renderReferences(style) {
    const items = referenceEntries.value
        .map((html, i) => `<li style="margin-bottom:0.25em;${style}"><span style="font-weight:600">${i + 1}.</span> ${html}</li>`)
        .join('');
    const body = items
        ? `<ol style="margin-left:1.5em;padding-left:0;${style}">${items}</ol>`
        : '<p style="text-align:center;color:#999999">(Kosong)</p>';
    return `<h2 style="text-align:center;${style}">DAFTAR PUSTAKA</h2>${body}`;
}

// Render tabel asli (HTML) beserta caption-nya.
function renderTable(b, style) {
    let inner = b.content || '';
    if (!/^\s*<table/i.test(inner)) {
        inner = `<table style="border-collapse:collapse;width:100%">${inner}</table>`;
    }
    const caption = captionLabelFor(b, 'Tabel');
    const above = b.captionPosition === 'above' && caption
        ? `<p style="text-align:center;margin:0 0 4pt">${caption}</p>`
        : '';
    const below = b.captionPosition !== 'above' && caption
        ? `<p style="text-align:center;margin:4pt 0 0">${caption}</p>`
        : '';
    return `<div style="${style}">${above}${inner}${below}</div>`;
}

// Render gambar beserta caption-nya (mengikuti align/width blok).
function renderImage(b, style) {
    const src = (b.content || '').trim();
    const align = b.align || 'left';
    const ta = align === 'center' ? 'center' : align === 'right' ? 'right' : 'left';
    const widthPct = Number(b.width) || 60;
    const margin = ta === 'center' ? '0 auto' : ta === 'right' ? '0 0 0 auto' : '0';
    const img = src
        ? `<img src="${escHtml(src)}" style="width:${widthPct}%;max-width:100%;height:auto;display:block;margin:${margin}" alt="">`
        : '';
    const caption = captionLabelFor(b, 'Gambar');
    const above = b.captionPosition === 'above' && caption
        ? `<p style="text-align:center;margin:0 0 4pt">${caption}</p>`
        : '';
    const below = b.captionPosition !== 'above' && caption
        ? `<p style="text-align:center;margin:4pt 0 0">${caption}</p>`
        : '';
    return `<div style="text-align:${ta};${style}">${above}${img}${below}</div>`;
}

// Ubah satu blok menjadi HTML untuk dokumen Word, mempertahankan formatting canvas.
function blockToWordHtml(b) {
    const style = blockStyle(b);
    switch (b.type) {
        case 'pageBreak':
            return '<div style="page-break-before:always">&nbsp;</div>';
        case 'divider':
            return '<hr style="border:none;border-top:1px solid #d4d4d4;margin:0.5em 0">';
        case 'spacer':
            return `<div style="height:${Number(b.spacing) || 24}px"></div>`;
        case 'chapter': {
            const num = numberingMap.value[b.uid] || '';
            const head = num ? `${escHtml(num)} ` : '';
            return `<h1 style="${style}">${head}${b.content || ''}</h1>`;
        }
        case 'cover':
            return `<div style="text-align:center;${style}">${b.content || ''}</div>`;
        case 'abstract':
            return `<h2 style="text-align:center;${style}">${escHtml(b.pageTitle || 'ABSTRAK')}</h2>${b.content ? `<div style="${style}">${b.content}</div>` : ''}`;
        case 'blankPage':
            return `<h2 style="text-align:center;${style}">${escHtml(b.pageTitle || 'HALAMAN')}</h2>${b.content ? `<div style="${style}">${b.content}</div>` : ''}`;
        case 'toc':
            return renderSectionList('DAFTAR ISI', tocEntries.value, 'toc', style);
        case 'listTables':
            return renderSectionList('DAFTAR TABEL', tableEntries.value, 'table', style);
        case 'listFigures':
            return renderSectionList('DAFTAR GAMBAR', figureEntries.value, 'figure', style);
        case 'references':
            return renderReferences(style);
        case 'quote':
            return `<blockquote style="margin-left:1.5em;padding-left:1em;border-left:2px solid #d4d4d4;color:#525252;${style}">${b.content || ''}</blockquote>`;
        case 'bullet':
            return `<ul style="${style}">${b.content || ''}</ul>`;
        case 'number':
            return `<ol style="${style}">${b.content || ''}</ol>`;
        case 'table':
            return renderTable(b, style);
        case 'image':
            return renderImage(b, style);
        case 'formula':
            return `<p style="text-align:center;font-family:Consolas,'Courier New',monospace;${style}">${escHtml((b.content || '').trim())}</p>`;
        case 'code':
            return `<pre style="font-family:Consolas,'Courier New',monospace;font-size:10pt;background:#f5f5f5;padding:8pt;white-space:pre-wrap;${style}">${escHtml(b.content || '')}</pre>`;
        default: {
            const tag = headingTagOf(b.type);
            if (tag) {
                const num = numberingMap.value[b.uid] || '';
                const head = num ? `${escHtml(num)} ` : '';
                return `<${tag} style="${style}">${head}${b.content || ''}</${tag}>`;
            }
            return `<p style="${style}">${b.content || ''}</p>`;
        }
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

// Bungkus isi blok menjadi dokumen Word (.doc HTML) dengan pengaturan halaman & font dokumen.
function buildWordDocument(body) {
    const size = currentPageSize.value;
    const m = pageMargins.value;
    const font = quoteFontFamily(effectiveFontFamily.value) || 'Times New Roman';
    const fs = pageFontSize.value;
    const lh = pageLineHeight.value;
    const pageCss =
        `@page WordSection1{size:${size.widthMm}mm ${size.heightMm}mm;margin:${m.top}cm ${m.right}cm ${m.bottom}cm ${m.left}cm;}` +
        `@page WordSection2{size:${size.widthMm}mm ${size.heightMm}mm;margin:${m.top}cm ${m.right}cm ${m.bottom}cm ${m.left}cm;}`;
    return [
        '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">',
        '<head>',
        '<meta charset="utf-8">',
        '<title>', escHtml(projectName.value || 'project'), '</title>',
        '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->',
        '<style>',
        pageCss,
        'div.WordSection1{page:WordSection1;}',
        `body{font-family:${font};font-size:${fs}pt;line-height:${lh};color:#000000;}`,
        'p{margin:0 0 6pt;}',
        'h1{font-size:16pt;font-weight:bold;text-align:center;margin:0 0 12pt;}',
        'h2{font-size:14pt;font-weight:bold;margin:0 0 8pt;}',
        'h3{font-size:13pt;font-weight:bold;margin:0 0 6pt;}',
        'h4,h5,h6{font-size:12pt;font-weight:bold;margin:0 0 6pt;}',
        'blockquote{margin:0 0 6pt;}',
        'table{border-collapse:collapse;width:100%;}',
        'td,th{border:1px solid #d4d4d4;padding:4pt 6pt;vertical-align:top;}',
        'img{max-width:100%;}',
        'ol,ul{margin:0.25rem 0;padding-left:1.5rem;}',
        '</style>',
        '</head>',
        '<body><div class="WordSection1">',
        body,
        '</div></body></html>',
    ].join('\n');
}

// Ekspor dokumen (sesuai scope) sebagai file Word (.doc) yang bisa dibuka di MS Word.
function exportWord(scope, sectionName) {
    const list = blocksForScope(scope);
    const body = list.map((b) => blockToWordHtml(b)).join('\n');
    const html = buildWordDocument(body);
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
    const head = [
        '<meta charset="utf-8">',
        '<style>',
        css,
        '</style>',
        '<style>',
        printCss,
        '</style>',
    ].join('');

    const pages = Array.from(printEl.querySelectorAll('.print-page')).map((el) => el.outerHTML);
    if (!pages.length) {
        showToast('Dokumen belum siap dicetak.');
        return false;
    }

    try {
        // Pastikan cookie CSRF tersedia, lalu sertakan tokennya — endpoint POST
        // stateful (Sanctum) memvalidasi CSRF, jadi tanpa header ini akan 419.
        await ensureCsrf();
        const csrfToken = (document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1];
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (csrfToken) headers['X-XSRF-TOKEN'] = decodeURIComponent(csrfToken);

        // Antrikan render PDF (async) — server tidak memblokir & tidak menahan worker.
        const res = await fetch('/api/export/pdf', {
            method: 'POST',
            credentials: 'include',
            headers,
            body: JSON.stringify({ head, pages, project: projectId.value || null, format: 'pdf' }),
        });
        const queued = await res.json().catch(() => null);
        if (!res.ok) {
            throw new Error(queued?.error || 'Gagal membuat PDF.');
        }

        // Polling status sampai selesai / gagal.
        const token = queued.token;
        for (let i = 0; i < 200; i++) {
            await new Promise((r) => setTimeout(r, 1500));
            const stRes = await fetch(`/api/export/pdf/${encodeURIComponent(token)}`, {
                credentials: 'include',
                headers: { Accept: 'application/json' },
            });
            const st = await stRes.json().catch(() => null);
            if (!stRes.ok || !st) throw new Error(st?.error || 'Gagal memeriksa status PDF.');

            if (st.status === 'done') {
                const fileRes = await fetch(st.downloadUrl, { credentials: 'include' });
                if (!fileRes.ok) throw new Error('Gagal mengunduh PDF.');
                const blob = await fileRes.blob();
                triggerDownload(blob, downloadFileName(sectionName, 'pdf'));
                return true;
            }
            if (st.status === 'failed') throw new Error(st.error || 'Gagal membuat PDF.');
        }
        throw new Error('Waktu membuat PDF habis. Silakan coba lagi.');
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
        const paid = await spendCredits('download', { pages: Number(opt.pages) || 0 });
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
            :project-category="projectCategory"
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
            @open-publish="openPublish"
        />


        <!-- Body: palet blok + canvas + pengaturan -->
        <div class="flex min-h-0 flex-1">
            <div v-if="blocksOpen" class="fixed inset-0 z-30 bg-black/50 print:hidden lg:hidden" @click="blocksOpen = false"></div>
            <div v-if="inspectorOpen" class="fixed inset-0 z-30 bg-black/50 print:hidden xl:hidden" @click="inspectorOpen = false"></div>

            <!-- Palet blok konten -->
            <BlockPalette
                :groups="groupedBlockTypes"
                :sections="paletteSections"
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
                :selected-uids="selectedUids"
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
                :page-number-class-for="pageNumberClassFor"
                :watermark="watermarkSettings"
                :font-options="fontOptions"
                :selected-block="selectedBlock"
                :loading="isLoading"
                :set-canvas-el="setCanvasEl"
                :set-measure-ref="setMeasureRef"
                :is-drop-indicator-before="isDropIndicatorBefore"
                :is-cover-page="isCoverPage"
                :page-number-label="pageNumberLabel"
                v-model:current-page="currentPage"
                v-model:page-jump="pageJump"
                @select="handleBlockSelect"
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
                @toc-navigate="handleTocNavigate"
            />

            <!-- Bar aksi multi-blok: muncul saat >1 blok terpilih -->
            <div
                v-if="selectedUids.length > 1"
                class="pointer-events-none fixed bottom-6 left-1/2 z-[70] -translate-x-1/2 print:hidden"
            >
                <div class="pointer-events-auto flex items-center gap-2 rounded-full border border-neutral-200 bg-white/95 px-4 py-2 shadow-xl backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/95">
                    <span class="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                        {{ selectedUids.length }} blok dipilih
                    </span>
                    <span class="text-[11px] text-neutral-400 dark:text-neutral-500">Ctrl+klik / Shift+klik</span>
                    <button
                        v-if="canMergeSelected"
                        type="button"
                        class="cursor-pointer rounded-full bg-neutral-900 px-3 py-1 text-xs font-medium text-white transition-colors hover:bg-neutral-700 dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                        title="Gabungkan paragraf terpilih menjadi satu blok agar seleksi teks bisa sekaligus"
                        @click="mergeSelectedBlocks"
                    >
                        Gabungkan
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full border border-red-200 px-3 py-1 text-xs font-medium text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/50 dark:text-red-400 dark:hover:bg-red-950/30"
                        @click="requestDeleteBlock"
                    >
                        Hapus
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full border border-neutral-200 px-3 py-1 text-xs font-medium text-neutral-600 transition-colors hover:text-neutral-900 dark:border-neutral-700 dark:text-neutral-300 dark:hover:text-white"
                        @click="clearMultiSelection"
                    >
                        Batal
                    </button>
                </div>
            </div>

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
                :page-context="currentPageContext"
                :ai-history-list="aiHistoryList"
                :ai-history-loading="aiHistoryLoading"
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
                v-model:watermark-enabled="watermarkEnabled"
                v-model:watermark-type="watermarkType"
                v-model:watermark-text="watermarkText"
                v-model:watermark-font-size="watermarkFontSize"
                v-model:watermark-color="watermarkColor"
                v-model:watermark-opacity="watermarkOpacity"
                v-model:watermark-rotation="watermarkRotation"
                v-model:watermark-image="watermarkImage"
                v-model:watermark-image-width="watermarkImageWidth"
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
                @trigger-watermark-image-upload="triggerWatermarkImageUpload"
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
                @generate-page-content="generatePageContent"
                @insert-page-content="insertPageContent"
                @run-plagiarism="openPlagiarismCheck"
                @run-turnitin="openTurnitinOptimizer"
                @load-ai-history="loadAiResults"
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
        v-model:front-matter-position="frontMatterPosition"
        v-model:body-position="bodyPosition"
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
        title="Hapus blok?"
        message="Blok ini masih memiliki isi. Konten yang sudah ditulis akan ikut terhapus."
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
        :page-number-class-for="pageNumberClassFor"
        :watermark="watermarkSettings"
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

    <!-- Konfirmasi sebelum memotong koin plagiarism -->
    <div v-if="plagiarismConfirmOpen" class="fixed inset-0 z-[95] flex items-center justify-center p-4 print:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="cancelPlagiarismCheck"></div>
        <div class="relative z-10 w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-5 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <h2 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Konfirmasi Pengecekan Plagiarisme</h2>
            <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                Pemeriksaan ini akan memotong <span class="font-semibold text-neutral-900 dark:text-white">{{ creditPricing.ai_plagiarism }} koin</span> dari saldo kamu.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-900"
                    @click="cancelPlagiarismCheck"
                >Batal</button>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                    @click="confirmPlagiarismCheck"
                >Lanjutkan</button>
            </div>
        </div>
    </div>

    <!-- Konfirmasi sebelum memotong koin Turnitin -->
    <div v-if="turnitinConfirmOpen" class="fixed inset-0 z-[95] flex items-center justify-center p-4 print:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="cancelTurnitinCheck"></div>
        <div class="relative z-10 w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-5 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <h2 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Konfirmasi Optimasi Turnitin</h2>
            <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                Optimasi ini akan memotong <span class="font-semibold text-neutral-900 dark:text-white">{{ creditPricing.ai_turnitin }} koin</span> dari saldo kamu dan menjalankan pemindaian seluruh halaman dokumen.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-900"
                    @click="cancelTurnitinCheck"
                >Batal</button>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                    @click="confirmTurnitinCheck"
                >Lanjutkan</button>
            </div>
        </div>
    </div>

    <!-- Overlay screening Turnitin (animasi dari halaman awal sampai akhir, tidak bisa dibatalkan) -->
    <div v-if="turnitinScreeningOpen" class="fixed inset-0 z-[96] flex items-center justify-center bg-neutral-950/85 backdrop-blur-sm print:hidden">
        <div class="flex w-full max-w-md flex-col items-center gap-6 px-6 py-8 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10">
                <span class="inline-block h-8 w-8 animate-spin rounded-full border-[3px] border-white/25 border-t-white"></span>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white">Memindai Turnitin…</h2>
                <p class="mt-1 text-sm text-neutral-300">{{ turnitinScreeningStatus }}</p>
            </div>

            <div class="w-full">
                <div class="h-2 w-full overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-emerald-400 transition-all duration-200" :style="{ width: turnitinScreeningProgress + '%' }"></div>
                </div>
                <div class="mt-2 flex justify-between text-xs text-neutral-400">
                    <span>Halaman 1</span>
                    <span class="font-medium text-white">{{ turnitinScreeningProgress }}%</span>
                    <span>Halaman {{ turnitinScreeningPages }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div
                    v-for="i in 5"
                    :key="i"
                    class="h-16 w-12 rounded-md border border-white/15 bg-white/5 transition-colors duration-300"
                    :class="{ 'bg-emerald-400/30 border-emerald-400/60': (turnitinScreeningProgress / 20) >= i }"
                ></div>
            </div>

            <p class="text-xs text-neutral-500">Jangan tutup atau berpindah halaman selama proses pemindaian berlangsung.</p>
        </div>
    </div>

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
        :has-selection="!!selectedUid"
        :spend-credits="spendCredits"
        :references="allReferences"
        :targets="agentTargets"
        :default-target="agentDefaultTarget"
        :canvas-text="agentCanvasText"
        v-model:open="agentModalOpen"
        @close="agentModalOpen = false"
        @apply="applyAgentToCanvas"
        @reference-saved="onReferenceSaved"
        @insert-citations="insertAgentCitations"
    />

    <!-- Panel struktur: sisipkan hasil agent per bagian (bab) -->
    <div
        v-if="agentStructureOpen"
        class="fixed inset-0 z-[85] flex items-center justify-center p-4 print:hidden"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-black/50" @click="closeAgentStructure"></div>
        <div class="relative z-10 flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="border-b border-neutral-200 px-5 py-3.5 dark:border-neutral-800">
                <h2 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Terapkan ke Canvas</h2>
                <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                    Hasil agent dipecah per bagian. Bab yang sudah ada digantikan, yang belum ada ditambahkan.
                </p>
            </div>

            <div class="flex-1 space-y-2 overflow-y-auto p-4">
                <div
                    v-for="row in structureRows()"
                    :key="row.index"
                    class="flex items-start gap-3 rounded-lg border p-3"
                    :class="row.targeted
                        ? 'border-neutral-900 bg-neutral-50 dark:border-white dark:bg-neutral-900/60'
                        : 'border-neutral-200 dark:border-neutral-800'"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                            {{ row.title }}
                            <span
                                v-if="row.targeted"
                                class="ml-1.5 rounded-md bg-neutral-900 px-1.5 py-0.5 text-[10px] font-medium text-white dark:bg-white dark:text-neutral-950"
                            >tujuan</span>
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ row.count }} blok ·
                            <span :class="row.matched ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                                {{ row.matched ? 'akan digantikan' : 'akan ditambahkan' }}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 cursor-pointer rounded-lg border border-neutral-900 px-3 py-1.5 text-xs font-medium text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                        @click="applyStructureSection(row.index)"
                    >
                        {{ row.matched ? 'Gantikan' : 'Tambahkan' }}
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="closeAgentStructure"
                >
                    Batal
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                    @click="applyAllStructureSections"
                >
                    Terapkan Semua
                </button>
            </div>
        </div>
    </div>

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

    <!-- Modal konfirmasi publikasi project ke Lists Project -->
    <div v-if="publishOpen" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="closePublish"></div>
        <div class="relative z-10 w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                        <Rocket class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold">Publikasikan Project</h2>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Project akan tampil di Lists Project.</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    aria-label="Tutup"
                    @click="closePublish"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <p class="mt-4 text-sm text-neutral-600 dark:text-neutral-300">
                Project <span class="font-semibold">{{ projectName || 'Proyek Tanpa Judul' }}</span> akan dipublikasikan dan dapat dilihat (read-only) oleh pengguna lain. Lanjutkan?
            </p>

            <label class="mt-4 block">
                <span class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Deskripsi singkat (opsional)</span>
                <textarea
                    v-model="publishDescription"
                    rows="2"
                    maxlength="500"
                    placeholder="Tuliskan ringkasan singkat tentang project ini…"
                    class="w-full resize-none rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                ></textarea>
            </label>

            <!-- Testimoni wajib: rating & komentar (terinsert otomatis) -->
            <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                <div class="flex items-center gap-1.5 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                    <MessageSquare class="h-4 w-4" />
                    Testimoni (wajib)
                </div>
                <p class="mt-0.5 text-xs text-emerald-600/90 dark:text-emerald-400/80">
                    Rating dan komentarmu otomatis terkirim sebagai testimoni yang tampil di beranda.
                </p>

                <div class="mt-3">
                    <p class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Rating</p>
                    <div class="mt-1.5 flex gap-1">
                        <button
                            v-for="i in 5"
                            :key="i"
                            type="button"
                            class="cursor-pointer transition-transform hover:scale-110 disabled:cursor-not-allowed"
                            :aria-label="`Berikan ${i} bintang`"
                            :disabled="publishing"
                            @click="setPublishRating(i)"
                        >
                            <Star
                                class="h-7 w-7 transition-colors"
                                :class="i <= publishRating ? 'fill-amber-400 text-amber-400' : 'fill-transparent text-neutral-300 dark:text-neutral-600'"
                            />
                        </button>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Komentar / ulasan</label>
                    <textarea
                        v-model="publishComment"
                        rows="3"
                        :maxlength="MAX_COMMENT"
                        placeholder="Tulis pengalaman menggunakan platform ini…"
                        :disabled="publishing"
                        class="mt-1 w-full resize-none rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm outline-none transition-colors focus:border-emerald-500 dark:border-emerald-900/60 dark:bg-neutral-950 dark:focus:border-emerald-400"
                    ></textarea>
                    <p class="mt-1 text-right text-[11px] text-emerald-600/80 dark:text-emerald-400/70">{{ MAX_COMMENT - publishComment.length }} karakter tersisa</p>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="closePublish"
                >
                    Batal
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!canPublish || publishing"
                    @click="confirmPublish"
                >
                    <Loader2 v-if="publishing" class="h-4 w-4 animate-spin" />
                    <Star v-else class="h-4 w-4" />
                    {{ publishing ? 'Memublikasikan…' : 'Publish + Testimoni' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Pencarian & penggantian teks dalam canvas (muncul saat Ctrl/Cmd + F) -->
    <div
        v-if="findOpen"
        ref="findPanelEl"
        class="fixed z-[60] w-[min(640px,calc(100vw-2rem))] overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg print:hidden dark:border-neutral-800 dark:bg-neutral-900"
        :style="findPanelStyle"
    >
        <!-- Baris pencarian -->
        <div class="flex items-center gap-1.5 px-3 py-2">
            <button
                type="button"
                class="flex h-7 w-7 shrink-0 cursor-grab items-center justify-center rounded-md text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-600 active:cursor-grabbing dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                aria-label="Pindahkan panel pencarian"
                @mousedown="onFindDragStart"
            >
                <GripHorizontal class="h-4 w-4" />
            </button>
            <Search class="h-4 w-4 shrink-0 text-neutral-400" />
            <input
                ref="findInputEl"
                v-model="findQuery"
                type="text"
                placeholder="Cari di canvas…"
                class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-neutral-400"
                @keydown.enter.prevent="findNext"
                @keydown.shift.enter.prevent="findPrev"
                @keydown.esc="closeFind"
            />
            <span class="shrink-0 text-xs tabular-nums text-neutral-400">
                {{ findMatches.length ? `${findIndex + 1}/${findMatches.length}` : '0/0' }}
            </span>
            <button
                type="button"
                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                aria-label="Hasil sebelumnya"
                :disabled="!findMatches.length"
                @click="findPrev"
            >
                <ChevronUp class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                aria-label="Hasil berikutnya"
                :disabled="!findMatches.length"
                @click="findNext"
            >
                <ChevronDown class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                aria-label="Tutup pencarian"
                @click="closeFind"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Baris penggantian -->
        <div class="flex items-center gap-1.5 border-t border-neutral-100 px-3 py-2 dark:border-neutral-800">
            <Replace class="h-4 w-4 shrink-0 text-neutral-400" />
            <input
                ref="replaceInputEl"
                v-model="replaceQuery"
                type="text"
                placeholder="Ganti dengan…"
                class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-neutral-400"
                @keydown.enter.prevent="replaceCurrent"
                @keydown.esc="closeFind"
            />
            <button
                type="button"
                class="shrink-0 rounded-md bg-neutral-100 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-200 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                :disabled="!findMatches.length"
                @click="replaceCurrent"
            >
                Ganti
            </button>
            <button
                type="button"
                class="shrink-0 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!findMatches.length"
                @click="replaceAll"
            >
                Ganti Semua
            </button>
        </div>

        <!-- Konteks kalimat aktif -->
        <div
            v-if="currentFindMatch"
            class="border-t border-neutral-100 px-3 py-2 text-xs text-neutral-500 dark:border-neutral-800"
        >
            <span class="font-medium text-neutral-600 dark:text-neutral-300">Kalimat:</span>
            {{ currentFindMatch.sentence }}
        </div>
    </div>

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
        :page-number-class-for="pageNumberClassFor"
        :watermark="watermarkSettings"
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

/* Sorotan saat menavigasi hasil pencarian teks di canvas (Ctrl+F). */
.find-flash {
    outline: 2px solid rgba(250, 204, 21, 0.95);
    outline-offset: 2px;
    animation: find-flash-pulse 1.8s ease-out;
}

/* Sorotan kalimat yang sedang aktif pada hasil pencarian. */
.find-match-mark {
    background-color: rgba(250, 204, 21, 0.45);
    color: inherit;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgba(250, 204, 21, 0.7);
}

@keyframes find-flash-pulse {
    0%,
    100% {
        background-color: transparent;
    }
    20% {
        background-color: rgba(250, 204, 21, 0.22);
    }
    60% {
        background-color: rgba(250, 204, 21, 0.08);
    }
}
</style>