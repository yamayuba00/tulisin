<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Files, ChevronUp, ChevronDown, Trash2 } from 'lucide-vue-next';
import FormatterToolbar from '../../../components/FormatterToolbar.vue';
import CanvasBlock from '../../../components/CanvasBlock.vue';
import TableBlock from '../../../components/TableBlock.vue';
import ImageBlock from '../../../components/ImageBlock.vue';
import FormulaBlock from '../../../components/FormulaBlock.vue';

const props = defineProps({
    canvasBlocks: { type: Array, default: () => [] },
    pages: { type: Array, default: () => [] },
    selectedUid: { type: String, default: null },
    dropIndex: { type: Number, default: null },
    pageBoxStyle: { type: Object, default: () => ({}) },
    mirrorStyle: { type: Object, default: () => ({}) },
    contentHeightPx: { type: Number, default: 0 },
    pageDimensions: { type: Object, default: () => ({}) },
    pageHeightPx: { type: Number, default: 0 },
    gridStyle: { type: Object, default: () => ({}) },
    hRulerStyle: { type: Object, default: () => ({}) },
    vRulerStyle: { type: Object, default: () => ({}) },
    horizontalMarks: { type: Array, default: () => [] },
    showGuides: { type: Boolean, default: false },
    contentAreaStyle: { type: Object, default: () => ({}) },
    pageGridLines: { type: String, default: '' },
    numberingMap: { type: Object, default: () => ({}) },
    tocEntries: { type: Array, default: () => [] },
    tableEntries: { type: Array, default: () => [] },
    figureEntries: { type: Array, default: () => [] },
    referenceEntries: { type: Array, default: () => [] },
    citationStyle: { type: String, default: '' },
    captionNumbers: { type: Object, default: () => ({}) },
    pageNumberPosition: { type: String, default: 'bottom-center' },
    pageNumberClass: { type: String, default: '' },
    fontOptions: { type: Array, default: () => [] },
    selectedBlock: { type: Object, default: null },
    setCanvasEl: { type: Function, default: null },
    setMeasureRef: { type: Function, default: null },
    isDropIndicatorBefore: { type: Function, default: null },
    isCoverPage: { type: Function, default: null },
    pageNumberLabel: { type: Function, default: null },
});

const currentPage = defineModel('currentPage', { type: Number, default: 1 });
const pageJump = defineModel('pageJump', { type: Number, default: 1 });

const emit = defineEmits([
    'select',
    'update-content',
    'update-indent',
    'update-page-title',
    'update-width',
    'set-block-font-size',
    'remove-block-by-uid',
    'block-dragstart',
    'dragend',
    'contextmenu-block',
    'open-page-settings',
    'open-page-menu',
    'dragover',
    'drop',
    'move-page',
    'delete-page',
    'set-font-family',
    'set-font-size',
    'edit-code',
]);

const scrollEl = ref(null);

// ---- Virtualisasi halaman ----
// Setiap halaman punya tinggi tetap (A4/A5/...), jadi cukup render halaman yang
// berada di sekitar viewport (plus buffer). Halaman lain diganti spacer agar
// scrollbar & posisi tetap sama persis seperti render penuh.
const PAD = 32;     // padding kontainer (p-8 = 2rem)
const GAP = 24;     // jarak antar halaman (mb-6)
const BUFFER = 3;   // halaman yang dirender di luar viewport

const slot = computed(() => (props.pageHeightPx || 0) + GAP);

const viewTop = ref(0);
const viewHeight = ref(0);

function updateViewport() {
    if (scrollEl.value) {
        viewTop.value = scrollEl.value.scrollTop;
        viewHeight.value = scrollEl.value.clientHeight;
    }
}

const visibleRange = computed(() => {
    const len = props.pages.length;
    const s = slot.value;
    if (!len || s <= 0) return { first: 0, last: -1 };
    const first = Math.max(0, Math.floor((viewTop.value - PAD) / s) - BUFFER);
    let last = Math.min(len - 1, Math.ceil((viewTop.value + viewHeight.value - PAD) / s) + BUFFER);
    if (last < first) last = first;
    return { first, last };
});

const visiblePages = computed(() => {
    const { first, last } = visibleRange.value;
    const out = [];
    for (let i = first; i <= last; i++) out.push({ pIndex: i, page: props.pages[i] });
    return out;
});

const topSpacer = computed(() => PAD + visibleRange.value.first * slot.value);
const bottomSpacer = computed(() => {
    const len = props.pages.length;
    return Math.max(0, (len - 1 - visibleRange.value.last) * slot.value);
});

function onCanvasScroll() {
    updateViewport();
    const len = props.pages.length;
    const s = slot.value;
    if (!len || s <= 0) return;
    const idx = Math.round((viewTop.value - PAD) / s);
    currentPage.value = Math.max(1, Math.min(len, idx + 1));
}

// ---- Auto-scroll saat drag mendekati tepi viewport ----
// Dengan virtualisasi, blok di luar viewport tidak ada di DOM. Supaya bisa
// menjatuhkan blok ke posisi yang jauh, container harus menggulir sendiri
// ketika kursor drag berada di dekat tepi atas/bawah area scroll.
const AUTO_SCROLL_EDGE = 90; // px dari tepi atas/bawah
const AUTO_SCROLL_SPEED = 22; // px per frame

let autoScrollDir = 0; // -1 ke atas, 0 diam, 1 ke bawah
let autoScrollRaf = null;
let lastDragEvent = null;

function stopAutoScroll() {
    autoScrollDir = 0;
    if (autoScrollRaf != null) {
        cancelAnimationFrame(autoScrollRaf);
        autoScrollRaf = null;
    }
}

function tickAutoScroll() {
    autoScrollRaf = null;
    const el = scrollEl.value;
    if (!el || autoScrollDir === 0) return;
    const max = el.scrollHeight - el.clientHeight;
    el.scrollTop = Math.max(0, Math.min(max, el.scrollTop + autoScrollDir * AUTO_SCROLL_SPEED));
    onCanvasScroll();
    // Konten bergeser di bawah kursor yang diam; kirim ulang dragover dengan
    // koordinat terakhir agar indikator drop mengikuti blok yang kini di bawahnya.
    if (lastDragEvent) emit('dragover', lastDragEvent);
    if (autoScrollDir !== 0) autoScrollRaf = requestAnimationFrame(tickAutoScroll);
}

function onScrollDragOver(e) {
    // Ijinkan drop di seluruh area scroll (termasuk spacer) & update indikator.
    lastDragEvent = e;
    emit('dragover', e);
    if (!scrollEl.value) return;
    const rect = scrollEl.value.getBoundingClientRect();
    const y = e.clientY;
    let dir = 0;
    if (y < rect.top + AUTO_SCROLL_EDGE) dir = -1;
    else if (y > rect.bottom - AUTO_SCROLL_EDGE) dir = 1;
    autoScrollDir = dir;
    if (dir !== 0 && autoScrollRaf == null) autoScrollRaf = requestAnimationFrame(tickAutoScroll);
    else if (dir === 0) stopAutoScroll();
}

function onScrollDrop(e) {
    emit('drop', e);
    stopAutoScroll();
}

function onScrollDragEnd() {
    stopAutoScroll();
}

function onScrollDragLeave(e) {
    // Berhenti hanya saat benar-benar keluar dari area scroll (bukan antar child).
    if (scrollEl.value && !scrollEl.value.contains(e.relatedTarget)) stopAutoScroll();
}

function scrollToPage(n, smooth = true) {
    if (!props.pages.length) return;
    const idx = Math.min(props.pages.length - 1, Math.max(0, n - 1));
    const top = PAD + idx * slot.value;
    if (scrollEl.value) {
        if (smooth) scrollEl.value.scrollTo({ top, behavior: 'smooth' });
        else scrollEl.value.scrollTop = top;
    }
    currentPage.value = idx + 1;
    updateViewport();
}

function goToPage(n) {
    scrollToPage(n, true);
}

function jumpToPage() {
    goToPage(pageJump.value);
}

function onCanvasEl(el) {
    if (typeof props.setCanvasEl === 'function') props.setCanvasEl(el);
}

function onMeasureRef(uid, el) {
    if (typeof props.setMeasureRef === 'function') props.setMeasureRef(uid, el);
}

function dropIndicatorBefore(block) {
    return typeof props.isDropIndicatorBefore === 'function' ? props.isDropIndicatorBefore(block) : false;
}

function coverPage(pIndex) {
    return typeof props.isCoverPage === 'function' ? props.isCoverPage(pIndex) : false;
}

function pageNumberText(pIndex) {
    return typeof props.pageNumberLabel === 'function' ? props.pageNumberLabel(pIndex) : '';
}

function onWindowResize() {
    updateViewport();
}

onMounted(() => {
    updateViewport();
    window.addEventListener('resize', onWindowResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onWindowResize);
});

defineExpose({ scrollToPage });
</script>

<template>
    <main class="flex min-w-0 flex-1 flex-col print:block">
        <div class="print:hidden">
            <FormatterToolbar
                :block="selectedBlock"
                :font-options="fontOptions"
                @update:font-family="emit('set-font-family', selectedUid, $event)"
                @update:font-size="emit('set-font-size', selectedUid, $event)"
            />
        </div>
        <div
            ref="scrollEl"
            class="builder-scroll relative min-h-0 flex-1 overflow-auto bg-neutral-200/70 print:h-auto print:overflow-visible print:bg-white dark:bg-neutral-950"
            :style="gridStyle"
            @scroll="onCanvasScroll"
            @dragover="onScrollDragOver"
            @drop="onScrollDrop"
            @dragend="onScrollDragEnd"
            @dragleave="onScrollDragLeave"
        >
            <!-- Ruler horizontal -->
            <div
                class="pointer-events-none sticky top-0 z-20 hidden h-6 border-b border-neutral-300 bg-white/85 print:hidden dark:border-neutral-800 dark:bg-neutral-950/85 sm:block"
                :style="hRulerStyle"
            >
                <span
                    v-for="m in horizontalMarks"
                    :key="m.px"
                    class="absolute bottom-0 text-[9px] leading-none text-neutral-400"
                    :style="{ left: `${m.px + 3}px` }"
                >{{ m.label }}</span>
            </div>

            <div class="flex">
                <!-- Ruler vertikal -->
                <div
                    class="pointer-events-none sticky left-0 z-10 hidden w-6 shrink-0 self-stretch border-r border-neutral-300 bg-white/85 dark:border-neutral-800 dark:bg-neutral-950/85 sm:block"
                    :style="vRulerStyle"
                ></div>

                <!-- Halaman A4 -->
                <div :ref="onCanvasEl" class="relative min-w-0 flex-1" @contextmenu.prevent="emit('open-page-settings', $event)">
                    <!-- Mirror pengukuran (tersembunyi) untuk pagination -->
                    <div
                        class="pointer-events-none invisible absolute left-[-9999px] top-0"
                        :style="mirrorStyle"
                        aria-hidden="true"
                    >
                        <div
                            v-for="block in canvasBlocks"
                            :key="'m' + block.uid"
                            :ref="(el) => onMeasureRef(block.uid, el)"
                        >
                            <ImageBlock v-if="block.type === 'image'" :block="block" :measure="true" :max-height="contentHeightPx" :caption-prefix="captionNumbers[block.uid] || ''" />
                            <TableBlock v-else-if="block.type === 'table'" :block="block" :measure="true" :caption-prefix="captionNumbers[block.uid] || ''" />
                            <FormulaBlock v-else-if="block.type === 'formula'" :block="block" :measure="true" />
                            <div v-else-if="block.type === 'pageBreak'" class="h-0" aria-hidden="true"></div>
                            <CanvasBlock v-else :block="block" :measure="true" :toc-entries="tocEntries" :table-entries="tableEntries" :figure-entries="figureEntries" :reference-entries="referenceEntries" :citation-style="citationStyle" />
                        </div>
                    </div>

                    <!-- Spacer atas: posisi halaman pertama yang dirender -->
                    <div :style="{ height: topSpacer + 'px' }" aria-hidden="true"></div>

                    <div
                        v-for="item in visiblePages"
                        :key="item.pIndex"
                        class="mb-6 flex items-start justify-center px-2 sm:px-8"
                    >
                        <div
                            class="relative max-w-full shrink-0 rounded-sm border border-neutral-200 bg-white shadow-md print:border-0 print:bg-white print:shadow-none dark:border-neutral-800 dark:bg-neutral-900"
                            :style="pageBoxStyle"
                            @dragend="emit('dragend')"
                            @contextmenu.prevent.stop="emit('open-page-menu', $event, item.pIndex)"
                        >
                            <div
                                v-if="showGuides"
                                class="pointer-events-none absolute inset-0 overflow-hidden"
                                aria-hidden="true"
                            >
                                <div class="absolute border border-dashed border-neutral-400/70 dark:border-neutral-500/70" :style="contentAreaStyle"></div>
                                <div class="absolute" :style="{ ...contentAreaStyle, backgroundImage: pageGridLines, backgroundSize: '20px 20px' }"></div>
                            </div>
                            <div
                                v-if="item.page.length === 0"
                                class="flex min-h-[60vh] items-center justify-center text-center text-sm text-neutral-400 dark:text-neutral-500"
                            >
                                Seret blok H1, H2, paragraf, dan lainnya ke sini.
                            </div>

                            <template v-for="block in item.page" :key="block.chunkKey || block.uid">
                                <div
                                    v-if="dropIndicatorBefore(block)"
                                    aria-hidden="true"
                                    class="drop-indicator relative my-0.5 h-0.5 rounded-full bg-blue-600"
                                >
                                    <span class="absolute -left-2 -top-1 h-2.5 w-2.5 rounded-full border-2 border-blue-600 bg-white dark:bg-neutral-900"></span>
                                </div>
                                <TableBlock
                                    v-if="block.type === 'table'"
                                    :block="block"
                                    :selected="block.uid === selectedUid"
                                    :caption-prefix="captionNumbers[block.uid] || ''"
                                    @select="emit('select', block.uid)"
                                    @update:content="emit('update-content', block.uid, $event)"
                                    @update:indent="emit('update-indent', block.uid, $event)"
                                    @delete="emit('remove-block-by-uid', block.uid)"
                                    @dragstart="emit('block-dragstart', $event, block.uid)"
                                    @dragend="emit('dragend')"
                                />
                                <ImageBlock
                                    v-else-if="block.type === 'image'"
                                    :block="block"
                                    :selected="block.uid === selectedUid"
                                    :caption-prefix="captionNumbers[block.uid] || ''"
                                    :max-height="contentHeightPx"
                                    @select="emit('select', block.uid)"
                                    @update:width="emit('update-width', block.uid, $event)"
                                    @update:indent="emit('update-indent', block.uid, $event)"
                                    @contextmenu.prevent.stop="emit('contextmenu-block', $event, block.uid)"
                                    @dragstart="emit('block-dragstart', $event, block.uid)"
                                    @dragend="emit('dragend')"
                                />
                                <FormulaBlock
                                    v-else-if="block.type === 'formula'"
                                    :block="block"
                                    :selected="block.uid === selectedUid"
                                    @select="emit('select', block.uid)"
                                    @update:content="emit('update-content', block.uid, $event)"
                                    @update:font-size="emit('set-block-font-size', block.uid, $event)"
                                    @contextmenu.prevent.stop="emit('contextmenu-block', $event, block.uid)"
                                    @dragstart="emit('block-dragstart', $event, block.uid)"
                                    @dragend="emit('dragend')"
                                />
                                <div v-else-if="block.type === 'pageBreak'" class="h-0" aria-hidden="true"></div>
                                <CanvasBlock
                                    v-else
                                    :block="block"
                                    :selected="block.uid === selectedUid"
                                    :prefix="numberingMap[block.uid] || ''"
                                    :toc-entries="tocEntries"
                                    :table-entries="tableEntries"
                                    :figure-entries="figureEntries"
                                    :reference-entries="referenceEntries"
                                    :citation-style="citationStyle"
                                    :entry-slice="block.sliceStart == null ? null : [block.sliceStart, block.sliceEnd]"
                                    :min-height="0"
                                    @select="emit('select', block.uid)"
                                    @update:content="emit('update-content', block.uid, $event)"
                                    @update:indent="emit('update-indent', block.uid, $event)"
                                    @update:page-title="emit('update-page-title', block.uid, $event)"
                                    @contextmenu.prevent.stop="emit('contextmenu-block', $event, block.uid)"
                                    @dragstart="emit('block-dragstart', $event, block.uid)"
                                    @dragend="emit('dragend')"
                                    @edit-code="emit('edit-code', block.uid)"
                                />
                            </template>

                            <span
                                v-if="pageNumberPosition !== 'none' && !coverPage(item.pIndex)"
                                class="pointer-events-none absolute text-xs text-neutral-500 dark:text-neutral-400"
                                :class="pageNumberClass"
                            >{{ pageNumberText(item.pIndex) }}</span>
                        </div>

                        <div class="ml-3 hidden w-9 shrink-0 flex-col gap-1 pt-2 print:hidden sm:flex">
                            <button
                                type="button"
                                title="Pindah halaman ke atas"
                                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-neutral-200 bg-white text-neutral-600 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:text-white"
                                :disabled="item.pIndex === 0"
                                :class="{ 'opacity-40': item.pIndex === 0 }"
                                @click="emit('move-page', item.pIndex, -1)"
                            >
                                <ChevronUp class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                title="Pindah halaman ke bawah"
                                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-neutral-200 bg-white text-neutral-600 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:text-white"
                                :disabled="item.pIndex === pages.length - 1"
                                :class="{ 'opacity-40': item.pIndex === pages.length - 1 }"
                                @click="emit('move-page', item.pIndex, 1)"
                            >
                                <ChevronDown class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                title="Hapus halaman"
                                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-red-200 bg-white text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/50 dark:bg-neutral-900 dark:text-red-400 dark:hover:bg-red-950/30"
                                @click="emit('delete-page', item.pIndex)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Spacer bawah: sisa halaman yang tidak dirender -->
                    <div :style="{ height: bottomSpacer + 'px' }" aria-hidden="true"></div>

                    <div
                        v-if="dropIndex === canvasBlocks.length && canvasBlocks.length > 0"
                        class="relative mx-auto my-0.5 h-0.5 rounded-full bg-blue-600"
                        :style="{ width: pageDimensions.width }"
                    >
                        <span class="absolute -left-2 -top-1 h-2.5 w-2.5 rounded-full border-2 border-blue-600 bg-white dark:bg-neutral-900"></span>
                    </div>

                    <!-- Padding bawah kontainer -->
                    <div :style="{ height: PAD + 'px' }" aria-hidden="true"></div>
                </div>
            </div>
        </div>

        <!-- Informasi & navigasi halaman -->
        <div class="flex items-center justify-between gap-3 border-t border-neutral-200 bg-white px-4 py-2 print:hidden dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                <Files class="h-4 w-4" />
                <span>{{ pages.length }} halaman</span>
                <span class="text-neutral-300 dark:text-neutral-600">•</span>
                <span>Hal. {{ currentPage }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white"
                    :disabled="currentPage <= 1"
                    title="Halaman sebelumnya"
                    @click="goToPage(currentPage - 1)"
                >
                    <ChevronUp class="h-4 w-4" />
                </button>
                <div class="flex items-center gap-1 text-xs text-neutral-500 dark:text-neutral-400">
                    <input
                        v-model.number="pageJump"
                        type="number"
                        min="1"
                        :max="pages.length"
                        class="h-7 w-12 rounded-md border border-neutral-200 bg-transparent text-center text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                        @keydown.enter="jumpToPage"
                        @change="jumpToPage"
                    />
                    <span>/ {{ pages.length }}</span>
                </div>
                <button
                    type="button"
                    class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white"
                    :disabled="currentPage >= pages.length"
                    title="Halaman berikutnya"
                    @click="goToPage(currentPage + 1)"
                >
                    <ChevronDown class="h-4 w-4" />
                </button>
            </div>
        </div>
    </main>
</template>
