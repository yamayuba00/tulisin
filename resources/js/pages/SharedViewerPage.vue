<script setup>
import { ref, computed, onMounted, nextTick, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import { FileQuestion, Loader2, Link2, ListTree } from 'lucide-vue-next';
import { getJson } from '../utils/http';
import { cslFormatter } from '../utils/csl-formatter';
import CanvasBlock from '../components/CanvasBlock.vue';
import TableBlock from '../components/TableBlock.vue';
import ImageBlock from '../components/ImageBlock.vue';
import FormulaBlock from '../components/FormulaBlock.vue';

const route = useRoute();

const loading = ref(true);
const error = ref('');
const title = ref('');
const payload = ref(null);

const shareId = computed(() => (Array.isArray(route.query.shared) ? route.query.shared[0] : route.query.shared) || '');
const stateParam = computed(() => (Array.isArray(route.query.state) ? route.query.state[0] : route.query.state) || '');
const notCopy = computed(() => (Array.isArray(route.query.notcopy) ? route.query.notcopy[0] : route.query.notcopy) !== 'false');
const viewOnly = computed(() => (Array.isArray(route.query.view) ? route.query.view[0] : route.query.view) !== 'false');

const blocks = computed(() => (payload.value && Array.isArray(payload.value.blocks)) ? payload.value.blocks : []);
const docName = computed(() => title.value || payload.value?.name || 'Dokumen Tanpa Judul');
const citationStyle = computed(() => payload.value?.citationStyle || 'APA');
const pageNumberPosition = computed(() => payload.value?.pageNumberPosition || 'bottom-center');
const frontMatterStyle = computed(() => payload.value?.frontMatterStyle || 'roman');
const bodyStyle = computed(() => payload.value?.bodyStyle || 'decimal');
const bodyStart = computed(() => Number(payload.value?.bodyStart) || 1);

// Daftarkan font kustom (data URL) dari payload agar halaman publik menampilkan
// jenis huruf yang sama dengan builder.
function registerCustomFont(data) {
    if (!data || typeof data.family !== 'string' || !data.dataUrl) return;
    const id = `custom-font-${data.family}`;
    if (document.getElementById(id)) return;
    const style = document.createElement('style');
    style.id = id;
    style.textContent =
        `@font-face { font-family: '${data.family.replace(/'/g, '')}'; ` +
        `src: url('${data.dataUrl}') format('${data.format || 'truetype'}'); ` +
        'font-weight: normal; font-style: normal; }';
    document.head.appendChild(style);
}

onMounted(async () => {
    if (!shareId.value) {
        error.value = 'Link tidak valid.';
        loading.value = false;
        return;
    }
    try {
        const qs = stateParam.value ? `?state=${encodeURIComponent(stateParam.value)}` : '';
        const url = `/api/shared/${encodeURIComponent(shareId.value)}${qs}`;
        const data = await withTimeout(getJson(url), 20000);
        title.value = data.name || '';
        payload.value = data.payload || null;
        registerCustomFont(payload.value?.customFontData);
        updatePageScale();
        await nextTick();
        await nextFrame();
        paginate();
        // Pass kedua setelah gambar/rumus selesai dimuat agar tinggi blok akurat.
        setTimeout(paginate, 500);
    } catch (e) {
        error.value = e?.message || 'Dokumen tidak ditemukan atau sudah tidak dibagikan.';
    } finally {
        loading.value = false;
    }
});

function withTimeout(promise, ms) {
    return Promise.race([
        promise,
        new Promise((_, reject) => setTimeout(() => reject(new Error('Waktu memuat habis. Silakan muat ulang halaman.')), ms)),
    ]);
}
function nextFrame() {
    return new Promise((resolve) => requestAnimationFrame(resolve));
}

// Proteksi salin saat notcopy=true.
function applyProtection() {
    if (notCopy.value) document.documentElement.classList.add('no-copy');
}
onMounted(() => {
    applyProtection();
    window.addEventListener('resize', updatePageScale);
});

function stripHtml(html) {
    return String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

// Gambar di object storage memakai URL auth; untuk publik arahkan ke endpoint publik.
function resolveImage(src) {
    return String(src || '').replace('/api/media/files/', '/api/media/public/');
}
function resolvedImageBlock(block) {
    if (block && block.type === 'image' && block.content) {
        return { ...block, content: resolveImage(block.content) };
    }
    return block;
}

// ---- Penomoran otomatis (Bab, 1, 1.1, dst.) ----
const headingTypes = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7', 'h8', 'h9', 'h10'];
function headingLevelOf(type) {
    if (type === 'chapter') return 0;
    const i = headingTypes.indexOf(type);
    return i >= 0 ? i + 1 : null;
}
function isHeadingType(type) {
    return type === 'chapter' || headingTypes.includes(type);
}

const frontMatterTitles = {
    abstract: 'ABSTRAK',
    toc: 'DAFTAR ISI',
    listTables: 'DAFTAR TABEL',
    listFigures: 'DAFTAR GAMBAR',
    references: 'DAFTAR PUSTAKA',
};
function sectionTitleForToc(b) {
    if (b.type === 'blankPage') return (b.pageTitle || '').trim() || 'HALAMAN';
    return frontMatterTitles[b.type] || '';
}

const numberingMap = computed(() => {
    const map = {};
    const counters = new Array(11).fill(0);
    const romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    for (const block of blocks.value) {
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

const captionNumbers = computed(() => {
    const map = {};
    let chapterIndex = 0;
    let tableIndex = 0;
    let figureIndex = 0;
    for (const block of blocks.value) {
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

const referenceEntries = computed(() => {
    const cited = payload.value?.citedReferences || [];
    return cslFormatter(cited, citationStyle.value, { mode: 'bibliography' })
        .map((t) => t.replace(/^\[\d+\]\s*/, ''));
});

// ---- Style halaman ----
const mmToPx = (mm) => (Number(mm) || 0) * 96 / 25.4;
const cmToPx = (cm) => (Number(cm) || 0) * 96 / 2.54;
const pageSizes = { A4: { widthMm: 210, heightMm: 297 }, A5: { widthMm: 148, heightMm: 210 } };

function pageSizeInfo() {
    const base = pageSizes[payload.value?.format] || pageSizes.A4;
    const landscape = payload.value?.orientation === 'landscape';
    return {
        widthMm: landscape ? base.heightMm : base.widthMm,
        heightMm: landscape ? base.widthMm : base.heightMm,
    };
}

const pageBoxStyle = computed(() => {
    if (payload.value?.pageBoxStyle) return payload.value.pageBoxStyle;
    const size = pageSizeInfo();
    const m = payload.value?.margins || { top: 2.54, right: 2.54, bottom: 2.54, left: 2.54 };
    return {
        width: `${size.widthMm}mm`,
        height: `${size.heightMm}mm`,
        paddingTop: `${cmToPx(m.top)}px`,
        paddingRight: `${cmToPx(m.right)}px`,
        paddingBottom: `${cmToPx(m.bottom)}px`,
        paddingLeft: `${cmToPx(m.left)}px`,
        fontFamily: payload.value?.customFont || payload.value?.font || 'Times New Roman',
        fontSize: `${payload.value?.fontSize || 12}pt`,
        lineHeight: payload.value?.lineHeight || 1.5,
        boxSizing: 'border-box',
        overflow: 'hidden',
    };
});

const contentHeightPx = computed(() => {
    if (typeof payload.value?.contentHeightPx === 'number') return payload.value.contentHeightPx;
    const size = pageSizeInfo();
    const m = payload.value?.margins || { top: 2.54, bottom: 2.54 };
    return mmToPx(size.heightMm) - cmToPx(m.top) - cmToPx(m.bottom);
});

// ---- Skala halaman responsif (agar muat lebar layar kecil) ----
const pageWidthPx = computed(() => mmToPx(pageSizeInfo().widthMm));
const pageHeightPx = computed(() => mmToPx(pageSizeInfo().heightMm));
const pageScale = ref(1);
const viewportW = ref(0);

function updatePageScale() {
    viewportW.value = window.innerWidth || 0;
    const w = pageWidthPx.value || 1;
    // Sisa 24px untuk padding kanan-kiri di layar kecil.
    const avail = Math.max(200, viewportW.value - 24);
    pageScale.value = Math.min(1, avail / w);
}

function pageStyleScaled() {
    return {
        ...pageBoxStyle.value,
        width: `${pageWidthPx.value}px`,
        height: `${pageHeightPx.value}px`,
        transformOrigin: 'top left',
        transform: `scale(${pageScale.value})`,
    };
}

const pageNumberClass = computed(() => payload.value?.pageNumberClass || 'bottom-3 left-0 right-0 text-center');

// Style kontainer pengukur (sama dengan area konten satu halaman) untuk pagination.
const measureStyle = computed(() => {
    const s = pageBoxStyle.value;
    return {
        width: s.width,
        paddingTop: s.paddingTop,
        paddingRight: s.paddingRight,
        paddingBottom: s.paddingBottom,
        paddingLeft: s.paddingLeft,
        boxSizing: 'border-box',
        fontFamily: s.fontFamily,
        fontSize: s.fontSize,
        lineHeight: s.lineHeight,
    };
});

// ---- Pagination (identik dengan preview builder) ----
const measureEls = {};
const blockHeights = {};
const computedPages = ref([]);

function setMeasureRef(uid, el) {
    if (el) measureEls[uid] = el;
    else delete measureEls[uid];
}

function isPageBreakType(type) {
    return ['cover', 'abstract', 'toc', 'listTables', 'listFigures', 'references', 'chapter', 'pageBreak'].includes(type);
}

const splittableListTypes = ['toc', 'listTables', 'listFigures', 'references', 'bullet', 'number'];
const sectionListTypes = ['toc', 'listTables', 'listFigures', 'references'];

function countTopLevelListItems(html) {
    if (!html) return 0;
    const doc = new DOMParser().parseFromString(html, 'text/html');
    let count = 0;
    for (const child of doc.body.children) {
        if (child.tagName === 'LI') count += 1;
    }
    return count;
}

const listTitleHeight = computed(() => {
    const fontSizePx = (Number(payload.value?.fontSize) || 12) * 96 / 72;
    return fontSizePx * 1.25 * (Number(payload.value?.lineHeight) || 1.5) + 16;
});

const listEntryCounts = computed(() => {
    const strip = (html) => (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const hidden = payload.value?.hiddenTocUids || [];
    return {
        toc: blocks.value.filter((b) => (isHeadingType(b.type) || frontMatterTitles[b.type] || b.type === 'blankPage') && !hidden.includes(b.uid)).length,
        listTables: blocks.value.filter((b) => b.type === 'table' && b.showCaption !== false && strip(b.caption)).length,
        listFigures: blocks.value.filter((b) => b.type === 'image' && b.showCaption !== false && strip(b.caption)).length,
        references: referenceEntries.value.length,
    };
});

function paginate() {
    if (!blocks.value.length) {
        computedPages.value = [[]];
        return;
    }
    for (const uid of Object.keys(measureEls)) {
        const el = measureEls[uid];
        if (el) blockHeights[uid] = el.offsetHeight;
    }
    const result = [];
    let current = [];
    let acc = 0;
    const contentH = contentHeightPx.value;

    for (const block of blocks.value) {
        const h = blockHeights[block.uid] || 0;
        const forceBreak = isPageBreakType(block.type);

        const isItemList = block.type === 'bullet' || block.type === 'number';
        const needsSplit = splittableListTypes.includes(block.type) && (
            isItemList ? acc + h > contentH : h > contentH
        );

        if (needsSplit) {
            const total = isItemList
                ? countTopLevelListItems(block.content)
                : (listEntryCounts.value[block.type] || 0);
            if (total > 0) {
                const titleH = sectionListTypes.includes(block.type) ? listTitleHeight.value : 0;
                const entryH = Math.max(1, (h - titleH) / total);
                if (!isItemList && current.length) {
                    result.push(current);
                    current = [];
                    acc = 0;
                }
                let start = 0;
                let idx = 0;
                while (start < total) {
                    const chunkTitle = idx === 0 ? titleH : 0;
                    let avail = contentH - acc - chunkTitle;
                    if (avail < entryH) {
                        if (current.length) {
                            result.push(current);
                            current = [];
                            acc = 0;
                        }
                        avail = contentH - chunkTitle;
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
        } else if (current.length && acc + h > contentH) {
            result.push(current);
            current = [];
            acc = 0;
        }
        current.push(block);
        acc += h;
    }

    if (current.length) result.push(current);
    computedPages.value = result;
}

// ---- Nomor halaman & daftar isi (dihitung ulang setelah pagination) ----
function isCoverPage(pIndex) {
    const page = computedPages.value[pIndex];
    return !!page && page.some((b) => b.type === 'cover');
}

const firstBodyPageIndex = computed(() => {
    for (let i = 0; i < computedPages.value.length; i++) {
        if (computedPages.value[i].some((b) => b.type === 'chapter')) return i;
    }
    return computedPages.value.length;
});

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
        let n = 0;
        for (let i = 0; i <= pIndex; i++) {
            if (!isCoverPage(i)) n++;
        }
        return frontMatterStyle.value === 'roman' ? toRoman(n) : String(n);
    }
    const n = pIndex - firstBodyPageIndex.value + bodyStart.value;
    return bodyStyle.value === 'roman' ? toRoman(n) : String(n);
}

const tocEntries = computed(() => {
    const hidden = payload.value?.hiddenTocUids || [];
    return blocks.value
        .filter((b) => isHeadingType(b.type) || frontMatterTitles[b.type] || b.type === 'blankPage')
        .map((b) => {
            const pageIndex = computedPages.value.findIndex((p) => p.some((x) => x.uid === b.uid));
            const isFront = Boolean(frontMatterTitles[b.type]) || b.type === 'blankPage';
            return {
                uid: b.uid,
                type: b.type,
                level: isFront ? 0 : (headingLevelOf(b.type) ?? 0),
                number: isFront ? '' : (numberingMap.value[b.uid] || ''),
                text: isFront ? sectionTitleForToc(b) : stripHtml(b.content),
                pageLabel: pageIndex >= 0 ? pageNumberLabel(pageIndex) : '',
                hidden: hidden.includes(b.uid),
            };
        })
        .filter((e) => !e.hidden);
});

// Kerangka navigasi dokumen (Doc Lists): semua bagian, tanpa filter "sembunyikan".
const docList = computed(() => blocks.value
    .filter((b) => isHeadingType(b.type) || frontMatterTitles[b.type] || b.type === 'blankPage')
    .map((b) => {
        const pageIndex = computedPages.value.findIndex((p) => p.some((x) => x.uid === b.uid));
        const isFront = Boolean(frontMatterTitles[b.type]) || b.type === 'blankPage';
        return {
            uid: b.uid,
            type: b.type,
            level: isFront ? 0 : (headingLevelOf(b.type) ?? 0),
            number: isFront ? '' : (numberingMap.value[b.uid] || ''),
            text: isFront ? sectionTitleForToc(b) : stripHtml(b.content),
            pageLabel: pageIndex >= 0 ? pageNumberLabel(pageIndex) : '',
        };
    }),
);

const pageEls = {};
function setPageRef(i, el) {
    if (el) pageEls[i] = el;
    else delete pageEls[i];
}

function scrollToBlock(uid) {
    const pIndex = computedPages.value.findIndex((p) => p.some((x) => x.uid === uid));
    if (pIndex < 0) return;
    const el = pageEls[pIndex];
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const tableEntries = computed(() => blocks.value
    .filter((b) => b.type === 'table' && b.showCaption !== false)
    .map((b) => {
        const pageIndex = computedPages.value.findIndex((p) => p.some((x) => x.uid === b.uid));
        return {
            uid: b.uid,
            number: captionNumbers.value[b.uid] || '',
            text: stripHtml(b.caption),
            pageLabel: pageIndex >= 0 ? pageNumberLabel(pageIndex) : '',
        };
    })
    .filter((e) => e.text),
);

const figureEntries = computed(() => blocks.value
    .filter((b) => b.type === 'image' && b.showCaption !== false)
    .map((b) => {
        const pageIndex = computedPages.value.findIndex((p) => p.some((x) => x.uid === b.uid));
        return {
            uid: b.uid,
            number: captionNumbers.value[b.uid] || '',
            text: stripHtml(b.caption),
            pageLabel: pageIndex >= 0 ? pageNumberLabel(pageIndex) : '',
        };
    })
    .filter((e) => e.text),
);

const pageLabels = computed(() =>
    computedPages.value.map((p, i) => ({ isCover: isCoverPage(i), label: pageNumberLabel(i) })),
);

let remeasureTimer = null;
onBeforeUnmount(() => {
    if (remeasureTimer) clearTimeout(remeasureTimer);
    window.removeEventListener('resize', updatePageScale);
});
</script>

<template>
    <div
        class="min-h-screen bg-neutral-200/70 text-neutral-900"
        :class="{ 'no-copy': notCopy }"
        @contextmenu.prevent="notCopy"
    >
        <!-- Mirror pengukuran tersembunyi untuk pagination -->
        <div
            class="pointer-events-none invisible absolute left-[-9999px] top-0"
            :style="measureStyle"
            aria-hidden="true"
        >
            <div
                v-for="block in blocks"
                :key="'m' + block.uid"
                :ref="(el) => setMeasureRef(block.uid, el)"
            >
                <TableBlock
                    v-if="block.type === 'table'"
                    :block="block"
                    :measure="true"
                    :caption-prefix="captionNumbers[block.uid] || ''"
                />
                <ImageBlock
                    v-else-if="block.type === 'image'"
                    :block="resolvedImageBlock(block)"
                    :measure="true"
                    :caption-prefix="captionNumbers[block.uid] || ''"
                    :max-height="contentHeightPx"
                />
                <FormulaBlock v-else-if="block.type === 'formula'" :block="block" :measure="true" />
                <div v-else-if="block.type === 'pageBreak'" class="h-0" aria-hidden="true"></div>
                <CanvasBlock
                    v-else
                    :block="block"
                    :measure="true"
                    :toc-entries="tocEntries"
                    :table-entries="tableEntries"
                    :figure-entries="figureEntries"
                    :reference-entries="referenceEntries"
                    :citation-style="citationStyle"
                />
            </div>
        </div>

        <div class="mx-auto flex w-full max-w-[320mm] items-start justify-center gap-6 px-4 py-8">
            <!-- Konten dokumen -->
            <div class="flex min-w-0 flex-1 flex-col items-center">
                <!-- Status loading / error -->
                <div v-if="loading" class="flex items-center justify-center py-24 text-neutral-500">
                    <Loader2 class="h-5 w-5 animate-spin" />
                    <span class="ml-2">Memuat dokumen…</span>
                </div>

                <div v-else-if="error" class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <FileQuestion class="mx-auto h-10 w-10 text-neutral-400" />
                    <p class="mt-3 font-semibold">Dokumen tidak tersedia</p>
                    <p class="mt-1 text-sm text-neutral-500">{{ error }}</p>
                </div>

                <template v-else>
                    <div class="mb-4 w-full max-w-[210mm] rounded-lg border border-neutral-200 bg-white px-5 py-3 shadow-sm">
                        <h1 class="truncate text-center text-base font-semibold">{{ docName }}</h1>
                        <p class="mt-0.5 text-center text-xs text-neutral-500">
                            {{ viewOnly ? 'Mode baca' : 'Pratinjau' }}
                            <span v-if="notCopy"> · salin dinonaktifkan</span>
                        </p>
                    </div>

                    <!-- Halaman dokumen (identik dengan preview builder) -->
                    <div
                        v-for="(page, i) in computedPages"
                        :key="'page-' + i"
                        :ref="(el) => setPageRef(i, el)"
                        class="relative mb-6 shrink-0"
                        :style="{ width: `${pageWidthPx * pageScale}px`, height: `${pageHeightPx * pageScale}px` }"
                    >
                        <div
                            class="absolute left-0 top-0 rounded-sm border border-neutral-200 bg-white shadow-md"
                            :style="pageStyleScaled()"
                        >
                            <div
                                v-if="page.length === 0"
                                class="flex min-h-[60vh] items-center justify-center text-center text-sm text-neutral-400"
                            >
                                Halaman kosong
                            </div>

                        <template v-for="block in page" :key="block.chunkKey || block.uid">
                            <TableBlock
                                v-if="block.type === 'table'"
                                :block="block"
                                :measure="true"
                                :caption-prefix="captionNumbers[block.uid] || ''"
                            />
                            <ImageBlock
                                v-else-if="block.type === 'image'"
                                :block="resolvedImageBlock(block)"
                                :measure="true"
                                :caption-prefix="captionNumbers[block.uid] || ''"
                                :max-height="contentHeightPx"
                            />
                            <FormulaBlock v-else-if="block.type === 'formula'" :block="block" :measure="true" />
                            <div v-else-if="block.type === 'pageBreak'" class="h-0" aria-hidden="true"></div>
                            <CanvasBlock
                                v-else
                                :block="block"
                                :measure="true"
                                :prefix="numberingMap[block.uid] || ''"
                                :toc-entries="tocEntries"
                                :table-entries="tableEntries"
                                :figure-entries="figureEntries"
                                :reference-entries="referenceEntries"
                                :citation-style="citationStyle"
                                :entry-slice="block.sliceStart == null ? null : [block.sliceStart, block.sliceEnd]"
                            />
                        </template>

                        <span
                            v-if="pageNumberPosition !== 'none' && !(pageLabels[i] && pageLabels[i].isCover)"
                            class="pointer-events-none absolute text-xs text-neutral-500"
                            :class="pageNumberClass"
                        >{{ pageLabels[i] ? pageLabels[i].label : '' }}</span>
                        </div>
                    </div>

                    <p class="mt-2 flex items-center justify-center gap-1.5 text-center text-xs text-neutral-500">
                        <Link2 class="h-3.5 w-3.5" />
                        Dibagikan melalui Tulisin
                    </p>
                </template>
            </div>

            <!-- Doc Lists: kerangka navigasi -->
            <aside
                v-if="!loading && !error && docList.length"
                class="sticky top-6 hidden w-72 shrink-0 lg:block"
            >
                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center gap-2 border-b border-neutral-200 px-4 py-3">
                        <ListTree class="h-4 w-4 text-neutral-500" />
                        <span class="text-sm font-semibold">Doc Lists</span>
                    </div>
                    <nav class="max-h-[75vh] overflow-y-auto p-2">
                        <button
                            v-for="e in docList"
                            :key="e.uid"
                            type="button"
                            class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-neutral-100"
                            :style="{ paddingLeft: `${(e.level || 0) * 0.75 + 0.75}rem` }"
                            @click="scrollToBlock(e.uid)"
                        >
                            <span class="min-w-0 flex-1 truncate">
                                <span v-if="e.number" class="mr-1 font-medium">{{ e.number }}</span>
                                <span class="text-neutral-600">{{ e.text }}</span>
                            </span>
                            <span v-if="e.pageLabel" class="shrink-0 text-xs tabular-nums text-neutral-400">{{ e.pageLabel }}</span>
                        </button>
                    </nav>
                </div>
            </aside>
        </div>
    </div>
</template>

<style>
/* Proteksi salin saat notcopy=true */
html.no-copy,
html.no-copy body {
    -webkit-user-select: none;
    user-select: none;
}
html.no-copy img {
    -webkit-user-drag: none;
    user-drag: none;
    pointer-events: none;
}
</style>
