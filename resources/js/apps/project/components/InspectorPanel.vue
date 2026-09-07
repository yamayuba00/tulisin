<script setup>
import { ref, computed } from 'vue';
import {
    CornerDownRight,
    Eye,
    EyeOff,
    X,
    Sparkles,
    BookOpenCheck,
    List,
    Upload,
    ChevronUp,
    ChevronDown,
    Trash2,
    ScanSearch,
    Wand2,
    History,
} from 'lucide-vue-next';
import FilterSelect from '../../../components/FilterSelect.vue';
import FloatingAI from '../../../components/FloatingAI.vue';
import { authorYearLabel } from '../../../utils/csl-formatter';
import { renderMarkdown } from '../../../utils/markdown';

const props = defineProps({
    selectedBlock: { type: Object, default: null },
    documentTabs: { type: Array, default: () => [] },
    tocEntryByUid: { type: Object, default: () => ({}) },
    numberingMap: { type: Object, default: () => ({}) },
    fontSelectOptions: { type: Array, default: () => [] },
    lineHeightOptions: { type: Array, default: () => [] },
    pageFormatOptions: { type: Array, default: () => [] },
    blockLineHeightOptions: { type: Array, default: () => [] },
    captionPositionOptions: { type: Array, default: () => [] },
    citationStyleOptions: { type: Array, default: () => [] },
    alignOptions: { type: Array, default: () => [] },
    currentChapter: { type: String, default: '' },
    pageContext: { type: String, default: '' },
    references: { type: Array, default: () => [] },
    blockAiPrompts: { type: Array, default: () => [] },
    aiGenLoading: { type: Boolean, default: false },
    wordCount: { type: Number, default: 0 },
    isTextBlock: { type: Boolean, default: false },
    isHeadingBlock: { type: Boolean, default: false },
    canStyleText: { type: Boolean, default: false },
    captionNumbers: { type: Object, default: () => ({}) },
    typeLabel: { type: Function, default: null },
    typeIcon: { type: Function, default: null },
    blockPreview: { type: Function, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });
const tab = defineModel('tab', { type: String, default: 'toc' });
const fontChoice = defineModel('fontChoice', { type: String, default: 'Times New Roman' });
const customFont = defineModel('customFont', { type: String, default: '' });
const pageFontSize = defineModel('pageFontSize', { type: Number, default: 12 });
const pageLineHeight = defineModel('pageLineHeight', { type: Number, default: 1.5 });
const pageFormat = defineModel('pageFormat', { type: String, default: 'A4' });
const pageMargins = defineModel('pageMargins', { type: Object, default: () => ({ top: 2.54, right: 2.54, bottom: 2.54, left: 2.54 }) });
const citationStyle = defineModel('citationStyle', { type: String, default: 'APA' });
const aiGenInput = defineModel('aiGenInput', { type: String, default: '' });
const aiGenOutput = defineModel('aiGenOutput', { type: String, default: '' });

const watermarkEnabled = defineModel('watermarkEnabled', { type: Boolean, default: false });
const watermarkType = defineModel('watermarkType', { type: String, default: 'text' });
const watermarkText = defineModel('watermarkText', { type: String, default: 'RAHASIA' });
const watermarkFontSize = defineModel('watermarkFontSize', { type: Number, default: 48 });
const watermarkColor = defineModel('watermarkColor', { type: String, default: '#b0b0b0' });
const watermarkOpacity = defineModel('watermarkOpacity', { type: Number, default: 0.15 });
const watermarkRotation = defineModel('watermarkRotation', { type: Number, default: -30 });
const watermarkImage = defineModel('watermarkImage', { type: String, default: '' });
const watermarkImageWidth = defineModel('watermarkImageWidth', { type: Number, default: 300 });

const emit = defineEmits([
    'toggle-toc',
    'scroll-to-block',
    'deselect-block',
    'set-block-line-height',
    'set-custom-number',
    'insert-inline-citation',
    'open-citation-browser',
    'open-workspace',
    'set-show-caption',
    'set-caption',
    'set-caption-position',
    'trigger-image-upload',
    'trigger-font-upload',
    'trigger-watermark-image-upload',
    'set-width',
    'set-align',
    'set-columns',
    'update-indent',
    'set-first-line-indent',
    'set-spacing',
    'set-text-color',
    'move-block-by',
    'remove-block',
    'generate-block-content',
    'insert-generated-content',
    'run-plagiarism',
    'run-turnitin',
    'load-ai-history',
]);

function typeLabelOf(type) {
    return typeof props.typeLabel === 'function' ? props.typeLabel(type) : type;
}
function typeIconOf(type) {
    return typeof props.typeIcon === 'function' ? props.typeIcon(type) : null;
}
function blockPreviewOf(b) {
    return typeof props.blockPreview === 'function' ? props.blockPreview(b) : '';
}

// ---- Riwayat hasil AI (Turnitin / Plagiarism) di sidebar ----
const historyFilter = ref('turnitin'); // 'turnitin' | 'plagiarism'
const historyExpandedId = ref(null);

const filteredHistory = computed(() =>
    (props.aiHistoryList || []).filter((e) => e.type === historyFilter.value),
);

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

function toggleHistory(id) {
    historyExpandedId.value = historyExpandedId.value === id ? null : id;
}

function historyTypeLabel() {
    return historyFilter.value === 'turnitin' ? 'Turnitin AI Optimizer' : 'Screening Plagiarism';
}
</script>

<template>
    <aside
        class="fixed inset-y-0 right-0 z-40 flex w-80 max-w-[90vw] flex-col border-l border-neutral-200 bg-white transition-transform duration-200 print:hidden dark:border-neutral-800 dark:bg-neutral-950 xl:static xl:z-auto xl:translate-x-0"
        :class="open ? 'translate-x-0' : 'translate-x-full'"
    >
        <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
            <span class="text-sm font-semibold">Pengaturan</span>
            <button
                type="button"
                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white xl:hidden"
                aria-label="Tutup"
                @click="open = false"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <!-- Tab navigasi panel kanan (tampil saat tidak ada blok terpilih) -->
            <div v-if="!selectedBlock" class="mb-4 flex rounded-lg border border-neutral-200 p-1 dark:border-neutral-800">
                <button
                    type="button"
                    class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="tab === 'toc' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    @click="tab = 'toc'"
                >
                    Isi
                </button>
                <button
                    type="button"
                    class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="tab === 'format' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    @click="tab = 'format'"
                >
                    Format
                </button>
                <button
                    type="button"
                    class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="tab === 'ai' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    @click="tab = 'ai'"
                >
                    AI
                </button>
                <button
                    type="button"
                    class="flex-1 cursor-pointer rounded-md px-2 py-1.5 text-sm font-medium transition-colors"
                    :class="tab === 'history' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    @click="tab = 'history'; emit('load-ai-history')"
                >
                    Riwayat
                </button>
            </div>

            <!-- Isi: struktur dokumen + kontrol daftar isi -->
            <template v-if="!selectedBlock && tab === 'toc'">
                <div>
                    <p class="text-sm font-semibold">Struktur Dokumen</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Klik blok untuk lompat. Ikon mata untuk menampilkan/sembunyikan di Daftar Isi.</p>

                    <div v-if="documentTabs.length === 0" class="py-8 text-center text-sm text-neutral-400 dark:text-neutral-500">
                        Belum ada blok di canvas.
                    </div>
                    <div v-else class="mt-3 space-y-1">
                        <div
                            v-for="(b, i) in documentTabs"
                            :key="b.uid"
                            class="flex items-center gap-1"
                        >
                            <CornerDownRight
                                v-if="b.level > 0"
                                class="h-3.5 w-3.5 shrink-0 text-neutral-300 dark:text-neutral-600"
                                :style="{ marginLeft: `${(b.level - 1) * 1}rem` }"
                            />
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-left text-sm transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-900"
                                @click="emit('scroll-to-block', b.uid)"
                            >
                                <span class="w-5 shrink-0 text-xs text-neutral-400 dark:text-neutral-500">{{ i + 1 }}</span>
                                <component :is="typeIconOf(b.type)" class="h-4 w-4 shrink-0 text-neutral-400 dark:text-neutral-600" />
                                <span class="shrink-0 text-xs font-medium">{{ typeLabelOf(b.type) }}</span>
                                <span class="ml-auto truncate text-xs text-neutral-400 dark:text-neutral-500">
                                    <span v-if="numberingMap[b.uid]" class="mr-1 text-neutral-600 dark:text-neutral-300">{{ numberingMap[b.uid] }}</span>
                                    {{ blockPreviewOf(b) }}
                                </span>
                            </button>
                            <button
                                v-if="tocEntryByUid[b.uid]"
                                type="button"
                                class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-400 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:text-neutral-500 dark:hover:bg-neutral-900"
                                :title="tocEntryByUid[b.uid].hidden ? 'Tampilkan di Daftar Isi' : 'Sembunyikan dari Daftar Isi'"
                                @click="emit('toggle-toc', b.uid)"
                            >
                                <Eye v-if="!tocEntryByUid[b.uid].hidden" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Format Dokumen -->
            <template v-else-if="!selectedBlock && tab === 'format'">
                <div>
                    <p class="text-sm font-semibold">Format Dokumen</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Atur font, ukuran halaman, dan margin sesuai ketentuan kampus.</p>

                    <div class="mt-3 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Font</label>
                            <div class="mt-1">
                                <FilterSelect v-model="fontChoice" :options="fontSelectOptions" placeholder="Pilih font" />
                            </div>
                            <input
                                v-if="fontChoice === '__custom__'"
                                v-model="customFont"
                                type="text"
                                placeholder="Nama font, mis. Liberation Serif"
                                class="mt-2 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            />
                            <button
                                type="button"
                                class="mt-2 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-neutral-300 px-3 py-2 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-900"
                                @click="emit('trigger-font-upload')"
                            >
                                <Upload class="h-3.5 w-3.5" />
                                Unggah Font (TTF/OTF)
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Ukuran (pt)</label>
                                <input
                                    v-model.number="pageFontSize"
                                    type="number"
                                    min="8"
                                    max="72"
                                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Spasi Baris</label>
                                <div class="mt-1">
                                    <FilterSelect v-model="pageLineHeight" :options="lineHeightOptions" placeholder="Pilih spasi" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Format Halaman</label>
                            <div class="mt-1">
                                <FilterSelect v-model="pageFormat" :options="pageFormatOptions" placeholder="Pilih format" />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Margin Halaman (cm)</label>
                            <div class="mt-1 grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[11px] text-neutral-400 dark:text-neutral-500">Atas</label>
                                    <input
                                        v-model.number="pageMargins.top"
                                        type="number"
                                        min="0"
                                        max="8"
                                        step="0.1"
                                        class="mt-0.5 w-full rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] text-neutral-400 dark:text-neutral-500">Bawah</label>
                                    <input
                                        v-model.number="pageMargins.bottom"
                                        type="number"
                                        min="0"
                                        max="8"
                                        step="0.1"
                                        class="mt-0.5 w-full rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] text-neutral-400 dark:text-neutral-500">Kiri</label>
                                    <input
                                        v-model.number="pageMargins.left"
                                        type="number"
                                        min="0"
                                        max="8"
                                        step="0.1"
                                        class="mt-0.5 w-full rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                    />
                                </div>
                                <div>
                                    <label class="text-[11px] text-neutral-400 dark:text-neutral-500">Kanan</label>
                                    <input
                                        v-model.number="pageMargins.right"
                                        type="number"
                                        min="0"
                                        max="8"
                                        step="0.1"
                                        class="mt-0.5 w-full rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Watermark -->
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Watermark</p>
                                    <p class="mt-0.5 text-[11px] text-neutral-400 dark:text-neutral-500">Teks/gambar tembus pandang di tengah halaman.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                                    :class="watermarkEnabled ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white' : 'border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400'"
                                    @click="watermarkEnabled = !watermarkEnabled"
                                >
                                    {{ watermarkEnabled ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </div>

                            <template v-if="watermarkEnabled">
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-medium transition-colors"
                                        :class="watermarkType === 'text' ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white' : 'border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400'"
                                        @click="watermarkType = 'text'"
                                    >Teks</button>
                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-lg border px-3 py-2 text-xs font-medium transition-colors"
                                        :class="watermarkType === 'image' ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white' : 'border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400'"
                                        @click="watermarkType = 'image'"
                                    >Gambar</button>
                                </div>

                                <template v-if="watermarkType === 'text'">
                                    <div class="mt-3">
                                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Teks</label>
                                        <input
                                            v-model="watermarkText"
                                            type="text"
                                            placeholder="Mis. RAHASIA"
                                            class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                        />
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Ukuran (pt)</label>
                                            <input
                                                v-model.number="watermarkFontSize"
                                                type="number"
                                                min="8"
                                                max="200"
                                                class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Warna</label>
                                            <div class="mt-1 flex items-center gap-2">
                                                <input
                                                    v-model="watermarkColor"
                                                    type="color"
                                                    class="h-9 w-12 cursor-pointer rounded-lg border border-neutral-200 bg-transparent p-0.5 dark:border-neutral-800"
                                                />
                                                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ watermarkColor }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <div
                                        v-if="watermarkImage"
                                        class="mt-3 overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800"
                                    >
                                        <img :src="watermarkImage" alt="Watermark" class="max-h-36 w-full object-contain" />
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-3 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                        @click="emit('trigger-watermark-image-upload')"
                                    >
                                        <Upload class="h-4 w-4" />
                                        {{ watermarkImage ? 'Ganti Gambar' : 'Pilih Gambar' }}
                                    </button>
                                    <div class="mt-3">
                                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Lebar Gambar (px)</label>
                                        <input
                                            v-model.number="watermarkImageWidth"
                                            type="number"
                                            min="40"
                                            max="1200"
                                            class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                        />
                                    </div>
                                </template>

                                <div class="mt-3">
                                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kepekatan (Opacity)</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input
                                            v-model.number="watermarkOpacity"
                                            type="range"
                                            min="0"
                                            max="1"
                                            step="0.05"
                                            class="w-full"
                                        />
                                        <span class="w-10 text-right text-xs tabular-nums text-neutral-500 dark:text-neutral-400">{{ Math.round(watermarkOpacity * 100) }}%</span>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kemiringan (Rotasi)</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input
                                            v-model.number="watermarkRotation"
                                            type="range"
                                            min="-90"
                                            max="90"
                                            step="1"
                                            class="w-full"
                                        />
                                        <span class="w-10 text-right text-xs tabular-nums text-neutral-500 dark:text-neutral-400">{{ watermarkRotation }}°</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-neutral-400 dark:text-neutral-500">Negatif miring ke kiri, positif miring ke kanan.</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <!-- AI: asisten menulis (fokus halaman/paragraf aktif) -->
            <template v-else-if="!selectedBlock && tab === 'ai'">
                <div class="flex h-full flex-col">
                    <div class="mb-2">
                        <p class="text-sm font-semibold">Asisten AI</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Fokus membantu menulis di halaman / paragraf yang sedang aktif.</p>
                    </div>

                    <div class="mb-3 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-neutral-200 px-2 py-2 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-900"
                            @click="emit('run-plagiarism')"
                        >
                            <ScanSearch class="h-3.5 w-3.5" />
                            Cek Plagiarism
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-neutral-200 px-2 py-2 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-900"
                            @click="emit('run-turnitin')"
                        >
                            <Wand2 class="h-3.5 w-3.5" />
                            Turnitin
                        </button>
                    </div>

                    <FloatingAI
                        class="h-[56vh] min-h-0"
                        :context="currentChapter"
                        :canvas-context="pageContext"
                        placeholder="Minta lanjutan, perbaikan, atau kalimat baru untuk bagian ini."
                        :prompts="['Lanjutkan paragraf pada halaman ini', 'Buatkan kalimat pembuka untuk bagian ini', 'Saran struktur paragraf di sini']"
                    />
                </div>
            </template>

            <!-- Riwayat hasil AI (Turnitin / Plagiarism) -->
            <template v-else-if="!selectedBlock && tab === 'history'">
                <div>
                    <p class="text-sm font-semibold">Riwayat Hasil AI</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Hasil Turnitin &amp; Plagiarism yang tersimpan.</p>

                    <div class="mt-3 flex rounded-lg border border-neutral-200 p-1 dark:border-neutral-800">
                        <button
                            type="button"
                            class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="historyFilter === 'turnitin' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                            @click="historyFilter = 'turnitin'"
                        >Turnitin</button>
                        <button
                            type="button"
                            class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="historyFilter === 'plagiarism' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                            @click="historyFilter = 'plagiarism'"
                        >Plagiarism</button>
                    </div>

                    <div v-if="aiHistoryLoading" class="flex items-center gap-2 py-8 text-sm text-neutral-400 dark:text-neutral-500">
                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-neutral-300 border-t-neutral-900 dark:border-neutral-700 dark:border-t-white"></span>
                        Memuat riwayat…
                    </div>

                    <div v-else-if="filteredHistory.length" class="mt-3 space-y-2">
                        <div v-for="e in filteredHistory" :key="e.id" class="rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center justify-between gap-2 px-3 py-2.5 text-left transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900"
                                @click="toggleHistory(e.id)"
                            >
                                <div class="min-w-0">
                                    <p class="text-xs font-medium">{{ historyTypeLabel() }}</p>
                                    <p class="truncate text-[11px] text-neutral-400 dark:text-neutral-500">{{ formatDate(e.created_at) }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="rounded-md border border-neutral-200 px-2 py-0.5 text-xs font-semibold dark:border-neutral-800">{{ e.score }}%</span>
                                    <component :is="historyExpandedId === e.id ? ChevronUp : ChevronDown" class="h-4 w-4 text-neutral-400 dark:text-neutral-600" />
                                </div>
                            </button>

                            <div v-if="historyExpandedId === e.id" class="space-y-2 border-t border-neutral-200 px-3 py-2.5 dark:border-neutral-800">
                                <div v-for="(m, i) in (e.matches || [])" :key="i" class="rounded-md border border-neutral-200 p-2 dark:border-neutral-800">
                                    <p class="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">{{ m.blockLabel || 'Teks terdeteksi' }}</p>
                                    <div class="mt-1.5 grid gap-1.5">
                                        <div>
                                            <p class="text-[10px] font-medium text-neutral-400 dark:text-neutral-500">Sebelum</p>
                                            <p class="rounded border-l-2 border-red-400 bg-red-50 px-2 py-1 text-[11px] text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ m.matched }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-medium text-neutral-400 dark:text-neutral-500">Sesudah</p>
                                            <p class="rounded border-l-2 border-emerald-400 bg-emerald-50 px-2 py-1 text-[11px] text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">{{ m.suggestion }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-else class="py-8 text-center text-sm text-neutral-400 dark:text-neutral-500">
                        Belum ada hasil {{ historyFilter === 'turnitin' ? 'Turnitin' : 'Plagiarism' }}.
                    </p>
                </div>
            </template>

            <!-- Pengaturan blok terpilih -->
            <template v-else>
                <template v-if="selectedBlock">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Kustomisasi blok yang dipilih.</p>
                        <button
                            type="button"
                            class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                            aria-label="Tutup fokus blok"
                            title="Tutup fokus blok"
                            @click="emit('deselect-block')"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Informasi -->
                    <div class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Informasi</p>
                        <p class="mt-1 text-sm font-semibold">{{ typeLabelOf(selectedBlock.type) }}</p>
                        <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">Jenis blok tidak bisa diganti. Hapus blok lalu tambah yang baru.</p>
                        <div class="mt-2 flex items-center justify-between border-t border-neutral-100 pt-2 dark:border-neutral-800">
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">Jumlah Kata</span>
                            <span class="text-sm font-bold">{{ wordCount }}</span>
                        </div>
                    </div>

                    <!-- Spasi baris per blok -->
                    <div v-if="canStyleText" class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Spasi Baris</p>
                        <p class="mt-1 text-[11px] text-neutral-400 dark:text-neutral-500">Berlaku hanya untuk blok ini.</p>
                        <div class="mt-2">
                            <FilterSelect
                                :model-value="selectedBlock.lineHeight || 0"
                                :options="blockLineHeightOptions"
                                placeholder="Default"
                                @update:model-value="emit('set-block-line-height', selectedBlock.uid, $event)"
                            />
                        </div>
                    </div>

                    <!-- Warna teks per blok -->
                    <div v-if="canStyleText" class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Warna Teks</p>
                        <p class="mt-1 text-[11px] text-neutral-400 dark:text-neutral-500">Berlaku hanya untuk blok ini. Reset untuk ikut warna dokumen.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                type="color"
                                :value="selectedBlock.color || '#000000'"
                                class="h-9 w-12 cursor-pointer rounded-lg border border-neutral-200 bg-transparent p-0.5 dark:border-neutral-800"
                                @input="emit('set-text-color', selectedBlock.uid, $event.target.value)"
                            />
                            <span class="text-sm text-neutral-600 dark:text-neutral-300">{{ selectedBlock.color || 'Default' }}</span>
                            <button
                                type="button"
                                class="ml-auto inline-flex cursor-pointer items-center rounded-lg border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                :disabled="!selectedBlock.color"
                                @click="emit('set-text-color', selectedBlock.uid, '')"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    <!-- Heading (judul/bab): penomoran -->
                    <div v-if="isHeadingBlock" class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Heading</p>

                        <div class="mt-2">
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Penomoran</label>
                            <button
                                type="button"
                                class="mt-1 flex w-full cursor-pointer items-center justify-between rounded-lg border px-3 py-2 text-sm transition-colors"
                                :class="selectedBlock.customNumber ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white' : 'border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400'"
                                @click="emit('set-custom-number', selectedBlock.uid, selectedBlock.customNumber ? '' : (numberingMap[selectedBlock.uid] || ''))"
                            >
                                <span>{{ selectedBlock.customNumber ? 'Kustom' : 'Otomatis' }}</span>
                                <span class="text-xs">{{ selectedBlock.customNumber || numberingMap[selectedBlock.uid] || '' }}</span>
                            </button>
                            <input
                                v-if="selectedBlock.customNumber"
                                :value="selectedBlock.customNumber"
                                type="text"
                                placeholder="Mis. 1.1 atau I"
                                @change="emit('set-custom-number', selectedBlock.uid, $event.target.value)"
                                class="mt-2 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            />
                        </div>
                    </div>

                    <!-- Generate dengan AI -->
                    <div class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <div class="flex items-center gap-2">
                            <Sparkles class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                            <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Generate dengan AI</p>
                        </div>
                        <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">AI membaca seluruh canvas lalu menulis ke blok ini.</p>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="p in blockAiPrompts"
                                :key="p"
                                type="button"
                                class="cursor-pointer rounded-full border border-neutral-200 px-2.5 py-1 text-left text-[11px] text-neutral-500 transition-colors hover:border-neutral-400 hover:text-neutral-800 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200"
                                @click="aiGenInput = p"
                            >{{ p }}</button>
                        </div>

                        <textarea
                            v-model="aiGenInput"
                            rows="3"
                            placeholder="Contoh: Tolong Tambahkan paragraf di bab 2 di bagian blablablaa"
                            class="mt-2 w-full resize-none rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                        ></textarea>

                        <button
                            type="button"
                            class="mt-2 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-900 px-3 py-2 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                            :disabled="aiGenLoading"
                            @click="emit('generate-block-content')"
                        >
                            <Sparkles class="h-4 w-4" />
                            {{ aiGenLoading ? 'Menyiapkan...' : 'Generate' }}
                        </button>

                        <div v-if="aiGenOutput" class="mt-3 rounded-lg border border-neutral-200 p-2 dark:border-neutral-800">
                            <p class="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">Hasil</p>
                            <p class="mt-1 whitespace-pre-wrap text-sm text-neutral-700 dark:text-neutral-200" v-html="renderMarkdown(aiGenOutput)"></p>
                            <button
                                type="button"
                                class="mt-2 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="emit('insert-generated-content')"
                            >
                                Sisipkan ke Blok
                            </button>
                        </div>
                    </div>

                    <!-- Sisipkan Sitasi (blok teks) -->
                    <div v-if="isTextBlock" class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Sisipkan Sitasi</p>
                        <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">Tambahkan sitasi ke dalam teks blok ini.</p>

                        <div class="mt-3">
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Format Sitasi</label>
                            <div class="mt-1">
                                <FilterSelect
                                    v-model="citationStyle"
                                    :options="citationStyleOptions"
                                    placeholder="Pilih format"
                                />
                            </div>
                        </div>

                        <template v-if="references.length">
                            <div class="mt-2 space-y-1">
                                <button
                                    v-for="ref in references.slice(0, 3)"
                                    :key="ref.id"
                                    type="button"
                                    class="flex w-full cursor-pointer items-start gap-2 rounded-md border border-neutral-200 px-2 py-1.5 text-left text-xs transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-900"
                                    title="Sisipkan di posisi kursor"
                                    @mousedown.prevent
                                    @click="emit('insert-inline-citation', ref)"
                                >
                                    <BookOpenCheck class="mt-0.5 h-3.5 w-3.5 shrink-0 text-neutral-400 dark:text-neutral-600" />
                                    <span class="min-w-0">
                                        <span class="block font-medium text-neutral-700 dark:text-neutral-200">{{ authorYearLabel(ref) }}</span>
                                        <span class="block truncate text-neutral-400 dark:text-neutral-500">{{ ref.title }}</span>
                                    </span>
                                </button>
                            </div>

                            <button
                                type="button"
                                class="mt-2 inline-flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-2 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-900"
                                @click="emit('open-citation-browser')"
                            >
                                <List class="h-3.5 w-3.5" />
                                Lihat semua referensi ({{ references.length }})
                            </button>
                            <p class="mt-2 text-[11px] text-neutral-400 dark:text-neutral-500">Klik referensi untuk menyisipkan pada posisi kursor.</p>
                        </template>

                        <template v-else>
                            <button
                                type="button"
                                class="mt-3 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="emit('open-workspace')"
                            >
                                <Upload class="h-4 w-4" />
                                Buka Workspace untuk Menambah Referensi
                            </button>
                            <p class="mt-2 text-[11px] text-neutral-400 dark:text-neutral-500">Belum ada referensi. Unggah PDF di Tulisin Workspace terlebih dahulu.</p>
                        </template>
                    </div>

                    <!-- Deskripsi (tabel/gambar) -->
                    <div v-if="selectedBlock.type === 'table' || selectedBlock.type === 'image'" class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Deskripsi</p>

                        <button
                            type="button"
                            class="mt-2 flex w-full cursor-pointer items-center justify-between rounded-lg border px-3 py-2 text-sm transition-colors"
                            :class="selectedBlock.showCaption !== false ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white' : 'border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400'"
                            @click="emit('set-show-caption', selectedBlock.uid, selectedBlock.showCaption === false)"
                        >
                            <span>Tampilkan Deskripsi</span>
                            <span class="text-xs">{{ selectedBlock.showCaption !== false ? 'Aktif' : 'Nonaktif' }}</span>
                        </button>

                        <template v-if="selectedBlock.showCaption !== false">
                            <label class="mt-3 block text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                {{ selectedBlock.type === 'table' ? 'Deskripsi Tabel' : 'Deskripsi Gambar' }}
                            </label>
                            <input
                                :value="selectedBlock.caption || ''"
                                type="text"
                                :placeholder="selectedBlock.type === 'table' ? 'Mis. Tabel A' : 'Mis. Gambar A'"
                                @change="emit('set-caption', selectedBlock.uid, $event.target.value)"
                                class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            />
                            <div class="mt-2">
                                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Posisi Deskripsi</label>
                                <div class="mt-1">
                                    <FilterSelect
                                        :model-value="selectedBlock.captionPosition || (selectedBlock.type === 'table' ? 'above' : 'below')"
                                        :options="captionPositionOptions"
                                        placeholder="Posisi"
                                        @update:model-value="emit('set-caption-position', selectedBlock.uid, $event)"
                                    />
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-neutral-400 dark:text-neutral-500">
                                Nomor otomatis: {{ selectedBlock.type === 'table' ? 'Tabel' : 'Gambar' }} {{ captionNumbers[selectedBlock.uid] || '' }}
                            </p>
                        </template>
                    </div>

                    <!-- Gambar -->
                    <div v-if="selectedBlock.type === 'image'" class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Gambar</p>
                        <div class="mt-2 space-y-3">
                            <div
                                v-if="selectedBlock.content"
                                class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800"
                            >
                                <img :src="selectedBlock.content" alt="Pratinjau gambar" class="max-h-44 w-full object-contain" />
                            </div>
                            <button
                                type="button"
                                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="emit('trigger-image-upload')"
                            >
                                <Upload class="h-4 w-4" />
                                {{ selectedBlock.content ? 'Ganti Gambar' : 'Unggah Gambar' }}
                            </button>
                            <div>
                                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Lebar (%)</label>
                                <input
                                    :value="selectedBlock.width || 100"
                                    type="number"
                                    min="10"
                                    max="100"
                                    @change="emit('set-width', selectedBlock.uid, $event.target.value)"
                                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Penempatan -->
                    <div v-if="selectedBlock.type !== 'divider' && selectedBlock.type !== 'spacer'" class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Penempatan</p>
                        <label class="mt-2 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Posisi</label>
                        <div class="mt-1 grid grid-cols-4 gap-2">
                            <button
                                v-for="a in alignOptions"
                                :key="a.id"
                                type="button"
                                class="inline-flex cursor-pointer items-center justify-center rounded-lg border py-2 transition-colors"
                                :class="(selectedBlock.align || 'left') === a.id
                                    ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white'
                                    : 'border-neutral-200 text-neutral-500 hover:border-neutral-300 dark:border-neutral-800 dark:text-neutral-400'"
                                @click="emit('set-align', selectedBlock.uid, a.id)"
                            >
                                <component :is="a.icon" class="h-4 w-4" />
                            </button>
                        </div>

                        <label class="mt-3 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Indentasi</label>
                        <div class="mt-1 flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="emit('update-indent', selectedBlock.uid, (selectedBlock.indent || 0) - 1)"
                            >−</button>
                            <span class="min-w-8 text-center text-sm font-medium">{{ selectedBlock.indent || 0 }}</span>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="emit('update-indent', selectedBlock.uid, (selectedBlock.indent || 0) + 1)"
                            >+</button>
                        </div>

                        <template v-if="isTextBlock">
                            <label class="mt-3 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Kolom</label>
                            <div class="mt-1 grid grid-cols-3 gap-2">
                                <button
                                    v-for="n in [1, 2, 3]"
                                    :key="n"
                                    type="button"
                                    class="inline-flex cursor-pointer items-center justify-center rounded-lg border py-2 text-sm transition-colors"
                                    :class="(selectedBlock.columns || 1) === n
                                        ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white'
                                        : 'border-neutral-200 text-neutral-500 hover:border-neutral-300 dark:border-neutral-800 dark:text-neutral-400'"
                                    @click="emit('set-columns', selectedBlock.uid, n)"
                                >{{ n }}</button>
                            </div>
                        </template>

                        <template v-if="selectedBlock.type === 'paragraph' || selectedBlock.type === 'quote'">
                            <label class="mt-3 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Indentasi Baris Pertama</label>
                            <button
                                type="button"
                                class="mt-1 flex w-full cursor-pointer items-center justify-between rounded-lg border px-3 py-2 text-sm transition-colors"
                                :class="selectedBlock.firstLineIndent ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white' : 'border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400'"
                                @click="emit('set-first-line-indent', selectedBlock.uid, !selectedBlock.firstLineIndent)"
                            >
                                <span>{{ selectedBlock.firstLineIndent ? 'Aktif' : 'Nonaktif' }}</span>
                                <span class="text-xs">{{ selectedBlock.firstLineIndent ? '✓' : '—' }}</span>
                            </button>
                            <p class="mt-1 text-[11px] text-neutral-400 dark:text-neutral-500">Tekan Tab di awal paragraf untuk mengaktifkan.</p>
                        </template>
                    </div>

                    <!-- Jarak (spacer) -->
                    <div v-if="selectedBlock.type === 'spacer'" class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Jarak</p>
                        <label class="mt-2 block text-xs font-medium text-neutral-500 dark:text-neutral-400">Jarak (px)</label>
                        <input
                            :value="selectedBlock.spacing || 24"
                            type="number"
                            min="0"
                            max="500"
                            @change="emit('set-spacing', selectedBlock.uid, $event.target.value)"
                            class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                        />
                    </div>

                    <!-- Aksi -->
                    <div class="mt-4">
                        <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Pindahkan Posisi</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="emit('move-block-by', selectedBlock.uid, -1)"
                            >
                                <ChevronUp class="h-4 w-4" />
                                Naik
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="emit('move-block-by', selectedBlock.uid, 1)"
                            >
                                <ChevronDown class="h-4 w-4" />
                                Turun
                            </button>
                        </div>

                        <button
                            type="button"
                            class="mt-2 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            @click="emit('remove-block')"
                        >
                            <Trash2 class="h-4 w-4" />
                            Hapus Blok
                        </button>
                    </div>
                </template>

                <!-- Fallback (tidak terpakai) -->
                <template v-else>
                    <p class="py-8 text-center text-sm text-neutral-400 dark:text-neutral-500">
                        Pilih blok di canvas untuk mengaturnya.
                    </p>
                </template>
            </template>
        </div>
    </aside>
</template>
