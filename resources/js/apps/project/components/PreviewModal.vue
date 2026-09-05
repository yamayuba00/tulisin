<script setup>
import { ref, watch } from 'vue';
import { Eye, ZoomOut, ZoomIn, X, Maximize2 } from 'lucide-vue-next';
import CanvasBlock from '../../../components/CanvasBlock.vue';
import TableBlock from '../../../components/TableBlock.vue';
import ImageBlock from '../../../components/ImageBlock.vue';
import FormulaBlock from '../../../components/FormulaBlock.vue';

const props = defineProps({
    pages: { type: Array, default: () => [] },
    pageBoxStyle: { type: Object, default: () => ({}) },
    pageHeightPx: { type: Number, default: 1123 },
    captionNumbers: { type: Object, default: () => ({}) },
    contentHeightPx: { type: Number, default: 0 },
    numberingMap: { type: Object, default: () => ({}) },
    tocEntries: { type: Array, default: () => [] },
    tableEntries: { type: Array, default: () => [] },
    figureEntries: { type: Array, default: () => [] },
    referenceEntries: { type: Array, default: () => [] },
    citationStyle: { type: String, default: '' },
    pageNumberPosition: { type: String, default: 'bottom-center' },
    pageNumberClass: { type: String, default: '' },
    isCoverPage: { type: Function, default: null },
    pageNumberLabel: { type: Function, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });

const previewZoom = ref(1);

function zoomPreview(delta) {
    previewZoom.value = Math.min(2, Math.max(0.25, +(previewZoom.value + delta).toFixed(2)));
}

// Sesuaikan agar satu halaman muat di layar (tanpa perlu scroll di awal).
function mmToPx(mm) {
    return (Number(mm) || 0) * 96 / 25.4;
}

function pageWidthPxFromStyle() {
    const w = props.pageBoxStyle?.width;
    if (typeof w === 'number') return w;
    if (typeof w === 'string') {
        const m = w.match(/([\d.]+)\s*mm/);
        if (m) return mmToPx(parseFloat(m[1]));
        const p = w.match(/([\d.]+)\s*px/);
        if (p) return parseFloat(p[1]);
    }
    return 794;
}

function fitPreviewZoom() {
    const availHeight = Math.max(240, window.innerHeight - 150);
    const heightZoom = Math.max(0.25, availHeight / (props.pageHeightPx || 1123));
    const availWidth = Math.max(240, window.innerWidth - 48);
    const widthZoom = availWidth / pageWidthPxFromStyle();
    previewZoom.value = Math.min(1.5, Math.max(0.25, +Math.min(heightZoom, widthZoom).toFixed(2)));
}

// Sesuaikan zoom setiap kali pratinjau dibuka agar satu halaman muat di layar.
watch(
    () => open.value,
    (v) => {
        if (v) fitPreviewZoom();
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[60] flex flex-col bg-neutral-200/70 dark:bg-neutral-950">
        <div class="flex items-center justify-between gap-2 border-b border-neutral-200 bg-white px-4 py-2 dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex min-w-0 items-center gap-2">
                <Eye class="h-4 w-4 shrink-0 text-neutral-500 dark:text-neutral-400" />
                <span class="truncate text-sm font-semibold">Pratinjau Dokumen</span>
                <span class="hidden text-xs text-neutral-400 dark:text-neutral-500 lg:inline">Read-only · salin dinonaktifkan</span>
            </div>

            <div class="flex shrink-0 items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:bg-neutral-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    :disabled="previewZoom <= 0.25"
                    aria-label="Perkecil"
                    @click="zoomPreview(-0.25)"
                >
                    <ZoomOut class="h-4 w-4" />
                </button>
                <span class="w-12 text-center text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ Math.round(previewZoom * 100) }}%</span>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:bg-neutral-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    :disabled="previewZoom >= 2"
                    aria-label="Perbesar"
                    @click="zoomPreview(0.25)"
                >
                    <ZoomIn class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 cursor-pointer items-center justify-center gap-1 rounded-lg border border-neutral-200 px-2 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Sesuai layar"
                    @click="fitPreviewZoom"
                >
                    <Maximize2 class="h-4 w-4" />
                    <span class="hidden sm:inline">Sesuai Layar</span>
                </button>
                <button
                    type="button"
                    class="ml-2 inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white"
                    aria-label="Tutup pratinjau"
                    @click="open = false"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-auto p-6" @copy.prevent @cut.prevent @contextmenu.prevent>
            <div
                class="mx-auto flex select-none flex-wrap items-start justify-center gap-6"
                :style="{ zoom: previewZoom }"
            >
                <div
                    v-for="(page, pIndex) in pages"
                    :key="pIndex"
                    class="relative max-w-full shrink-0 rounded-sm border border-neutral-200 bg-white shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                    :style="pageBoxStyle"
                >
                    <div
                        v-if="page.length === 0"
                        class="flex min-h-[60vh] items-center justify-center text-center text-sm text-neutral-400 dark:text-neutral-500"
                    >
                        Halaman kosong
                    </div>

                    <template v-for="(block) in page" :key="block.chunkKey || block.uid">
                        <TableBlock
                            v-if="block.type === 'table'"
                            :block="block"
                            :measure="true"
                            :caption-prefix="captionNumbers[block.uid] || ''"
                        />
                        <ImageBlock
                            v-else-if="block.type === 'image'"
                            :block="block"
                            :measure="true"
                            :caption-prefix="captionNumbers[block.uid] || ''"
                            :max-height="contentHeightPx"
                        />
                        <FormulaBlock v-else-if="block.type === 'formula'" :block="block" :measure="true" />
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
                        v-if="pageNumberPosition !== 'none' && isCoverPage && !isCoverPage(pIndex)"
                        class="pointer-events-none absolute text-xs text-neutral-500 dark:text-neutral-400"
                        :class="pageNumberClass"
                    >{{ pageNumberLabel ? pageNumberLabel(pIndex) : '' }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
