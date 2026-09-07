<script setup>
import CanvasBlock from '../../../components/CanvasBlock.vue';
import TableBlock from '../../../components/TableBlock.vue';
import ImageBlock from '../../../components/ImageBlock.vue';
import FormulaBlock from '../../../components/FormulaBlock.vue';
import WatermarkOverlay from '../../../components/WatermarkOverlay.vue';

const props = defineProps({
    printPages: { type: Array, default: () => [] },
    pageBoxStyle: { type: Object, default: () => ({}) },
    captionNumbers: { type: Object, default: () => ({}) },
    contentHeightPx: { type: Number, default: 0 },
    numberingMap: { type: Object, default: () => ({}) },
    tocEntries: { type: Array, default: () => [] },
    tableEntries: { type: Array, default: () => [] },
    figureEntries: { type: Array, default: () => [] },
    referenceEntries: { type: Array, default: () => [] },
    citationStyle: { type: String, default: '' },
    pageNumberClassFor: { type: Function, default: null },
    watermark: { type: Object, default: () => ({}) },
    isCoverPage: { type: Function, default: null },
    pageNumberLabel: { type: Function, default: null },
});

function pageNumberClassForPage(pIndex) {
    return typeof props.pageNumberClassFor === 'function' ? props.pageNumberClassFor(pIndex) : '';
}
</script>

<template>
    <!-- Print view: hanya halaman dokumen (bersih, tanpa UI builder) -->
    <div class="print-only" aria-hidden="true">
        <div
            v-for="({ page, pIndex }) in printPages"
            :key="'print-' + pIndex"
            class="print-page"
            :style="pageBoxStyle"
        >
            <WatermarkOverlay :watermark="watermark" />

            <template v-for="block in page" :key="block.chunkKey || block.uid">
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
                <div v-else-if="block.type === 'pageBreak'" class="h-0"></div>
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
                v-if="pageNumberClassForPage(pIndex) && isCoverPage && !isCoverPage(pIndex)"
                class="absolute text-xs text-neutral-900"
                :class="pageNumberClassForPage(pIndex)"
            >{{ pageNumberLabel ? pageNumberLabel(pIndex) : '' }}</span>
        </div>
    </div>
</template>
