<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { X, Copy, Settings2, ScanSearch, Trash2, Upload, Wand2, History } from 'lucide-vue-next';
import FilterSelect from '../../../components/FilterSelect.vue';

defineProps({
    pageSettingsPos: { type: Object, default: () => ({ x: 0, y: 0 }) },
    pageNumberPositionOptions: { type: Array, default: () => [] },
    frontMatterStyleOptions: { type: Array, default: () => [] },
    bodyStyleOptions: { type: Array, default: () => [] },
    blockMenuBlock: { type: Object, default: null },
    blockMenuTypeLabel: { type: String, default: 'Block' },
});

const pageSettingsOpen = defineModel('pageSettingsOpen', { type: Boolean, default: false });
const pageMenu = defineModel('pageMenu', { type: Object, default: () => ({ open: false, x: 0, y: 0, pIndex: 0 }) });
const blockMenu = defineModel('blockMenu', { type: Object, default: () => ({ open: false, x: 0, y: 0, uid: null }) });
const pageNumberPosition = defineModel('pageNumberPosition', { type: String, default: 'bottom-center' });
const frontMatterStyle = defineModel('frontMatterStyle', { type: String, default: 'roman' });
const bodyStyle = defineModel('bodyStyle', { type: String, default: 'decimal' });
const bodyStart = defineModel('bodyStart', { type: Number, default: 1 });

const emit = defineEmits([
    'close-page-settings',
    'close-page-menu',
    'close-block-menu',
    'duplicate-page',
    'open-settings-from-menu',
    'plagiarism',
    'turnitin',
    'history',
    'delete-page',
    'upload-image',
    'delete-block',
    'paraphrase-block',
]);

const pageSettingsEl = ref(null);
const pageMenuEl = ref(null);
const blockMenuEl = ref(null);

function onDocumentClick(e) {
    if (pageSettingsOpen.value && pageSettingsEl.value && !pageSettingsEl.value.contains(e.target)) {
        emit('close-page-settings');
    }
    if (pageMenu.value.open && pageMenuEl.value && !pageMenuEl.value.contains(e.target)) {
        emit('close-page-menu');
    }
    if (blockMenu.value.open && blockMenuEl.value && !blockMenuEl.value.contains(e.target)) {
        emit('close-block-menu');
    }
}

watch(
    () => pageSettingsOpen.value || pageMenu.value.open || blockMenu.value.open,
    (anyOpen) => {
        document.removeEventListener('click', onDocumentClick);
        if (anyOpen) {
            nextTick(() => document.addEventListener('click', onDocumentClick));
        }
    },
);

onMounted(() => document.removeEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <!-- Popover pengaturan nomor halaman (tidak menghalangi canvas) -->
    <div
        v-if="pageSettingsOpen"
        ref="pageSettingsEl"
        class="fixed z-50 w-72 rounded-lg border border-neutral-200 bg-white p-4 shadow-xl dark:border-neutral-800 dark:bg-neutral-950"
        :style="{ left: pageSettingsPos.x + 'px', top: pageSettingsPos.y + 'px' }"
    >
        <div class="max-h-[420px] overflow-y-auto">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Pengaturan Nomor Halaman</h3>
                <button
                    type="button"
                    class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    aria-label="Tutup"
                    @click="emit('close-page-settings')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Posisi Nomor</label>
                    <div class="mt-1">
                        <FilterSelect
                            v-model="pageNumberPosition"
                            :options="pageNumberPositionOptions"
                            placeholder="Pilih posisi"
                        />
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Halaman Depan (Abstrak)</label>
                        <div class="mt-1">
                            <FilterSelect
                                v-model="frontMatterStyle"
                                :options="frontMatterStyleOptions"
                                placeholder="Pilih"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Halaman Isi (Bab)</label>
                        <div class="mt-1">
                            <FilterSelect
                                v-model="bodyStyle"
                                :options="bodyStyleOptions"
                                placeholder="Pilih"
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Mulai Nomor Halaman Isi</label>
                    <input
                        v-model.number="bodyStart"
                        type="number"
                        min="1"
                        class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Menu konteks halaman (klik kanan) -->
    <div
        v-if="pageMenu.open"
        ref="pageMenuEl"
        class="fixed z-50 w-52 overflow-hidden rounded-lg border border-neutral-200 bg-white py-1 shadow-xl dark:border-neutral-800 dark:bg-neutral-950"
        :style="{ left: pageMenu.x + 'px', top: pageMenu.y + 'px' }"
    >
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
            @click="emit('duplicate-page')"
        >
            <Copy class="h-4 w-4" />
            Duplikat Halaman
        </button>
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
            @click="emit('open-settings-from-menu')"
        >
            <Settings2 class="h-4 w-4" />
            Pengaturan Nomor Halaman
        </button>
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
            @click="emit('plagiarism')"
        >
            <ScanSearch class="h-4 w-4" />
            Cek Plagiarism
        </button>
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
            @click="emit('turnitin')"
        >
            <Wand2 class="h-4 w-4" />
            Turnitin AI Optimizer
        </button>
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
            @click="emit('history')"
        >
            <History class="h-4 w-4" />
            Riwayat Hasil AI
        </button>
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-red-600 transition-colors hover:bg-neutral-100 dark:text-red-400 dark:hover:bg-neutral-800"
            @click="emit('delete-page')"
        >
            <Trash2 class="h-4 w-4" />
            Hapus Page
        </button>
    </div>

    <!-- Menu konteks blok (klik kanan pada blok canvas) -->
    <div
        v-if="blockMenu.open"
        ref="blockMenuEl"
        class="fixed z-50 w-52 overflow-hidden rounded-lg border border-neutral-200 bg-white py-1 shadow-xl dark:border-neutral-800 dark:bg-neutral-950"
        :style="{ left: blockMenu.x + 'px', top: blockMenu.y + 'px' }"
    >
        <p class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
            {{ blockMenuTypeLabel }}
        </p>
        <button
            v-if="blockMenuBlock && blockMenuBlock.type === 'image'"
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
            @click="emit('upload-image')"
        >
            <Upload class="h-4 w-4" />
            Ganti Gambar
        </button>
        <button
            v-if="blockMenuBlock && blockMenuBlock.type !== 'image'"
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
            @click="emit('paraphrase-block')"
        >
            <ScanSearch class="h-4 w-4" />
            Parafrase Blok
        </button>
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-sm text-red-600 transition-colors hover:bg-neutral-100 dark:text-red-400 dark:hover:bg-neutral-800"
            @click="emit('delete-block')"
        >
            <Trash2 class="h-4 w-4" />
            Hapus Block
        </button>
    </div>
</template>
