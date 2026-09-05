<script setup>
import { computed, ref, watch } from 'vue';
import { Search, X, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import FilterSelect from '../../../components/FilterSelect.vue';
import { authorYearLabel } from '../../../utils/csl-formatter';

const props = defineProps({
    references: { type: Array, default: () => [] },
    styleOptions: { type: Array, default: () => [] },
    preview: { type: Function, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });
const search = defineModel('search', { type: String, default: '' });
const citationStyle = defineModel('citationStyle', { type: String, default: 'APA' });

const emit = defineEmits(['close', 'select']);

// Pagination: maksimal 5 referensi per halaman agar tidak melebihi tinggi modal.
const PER_PAGE = 5;
const page = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(props.references.length / PER_PAGE)));
const pagedReferences = computed(() => {
    const start = (page.value - 1) * PER_PAGE;
    return props.references.slice(start, start + PER_PAGE);
});

// Reset ke halaman pertama saat pencarian berubah atau daftar mengecil.
watch(search, () => {
    page.value = 1;
});
watch(open, (v) => {
    if (v) page.value = 1;
});
watch(totalPages, (n) => {
    if (page.value > n) page.value = n;
});

function prevPage() {
    if (page.value > 1) page.value -= 1;
}
function nextPage() {
    if (page.value < totalPages.value) page.value += 1;
}

function previewText(ref) {
    return typeof props.preview === 'function' ? props.preview(ref) : '';
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')"></div>

        <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start justify-between border-b border-neutral-200 px-5 py-4 dark:border-neutral-800">
                <div>
                    <h2 class="text-base font-semibold">Pilih Referensi</h2>
                    <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Pilih referensi untuk disisipkan sebagai sitasi ke blok aktif.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    aria-label="Tutup"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex flex-col gap-2 border-b border-neutral-200 px-5 py-3 dark:border-neutral-800 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari penulis, judul, atau jurnal..."
                        class="w-full rounded-lg border border-neutral-200 bg-transparent py-2 pl-8 pr-3 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                    />
                </div>
                <div class="w-full sm:w-44">
                    <FilterSelect
                        v-model="citationStyle"
                        :options="styleOptions"
                        placeholder="Format"
                    />
                </div>
            </div>

            <div class="flex-1 overflow-auto">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-neutral-50 text-xs text-neutral-500 dark:bg-neutral-900 dark:text-neutral-400">
                        <tr>
                            <th class="px-5 py-2 font-medium">Penulis</th>
                            <th class="px-3 py-2 font-medium">Judul</th>
                            <th class="px-3 py-2 font-medium">Format Sitasi</th>
                            <th class="px-5 py-2 text-right font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ref in pagedReferences" :key="ref.id" class="border-t border-neutral-100 dark:border-neutral-800/60">
                            <td class="px-5 py-2.5 align-top text-xs font-medium text-neutral-700 dark:text-neutral-200">{{ authorYearLabel(ref) }}</td>
                            <td class="max-w-[16rem] px-3 py-2.5 align-top text-xs text-neutral-500 dark:text-neutral-400">
                                <span class="block truncate">{{ ref.title }}</span>
                                <span class="block text-[11px] text-neutral-400 dark:text-neutral-500">{{ ref['container-title'] || '' }}</span>
                            </td>
                            <td class="px-3 py-2.5 align-top text-xs text-neutral-600 dark:text-neutral-300">{{ previewText(ref) }}</td>
                            <td class="px-5 py-2.5 text-right align-top">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-neutral-900 px-2.5 py-1 text-xs font-medium text-neutral-900 transition-colors hover:bg-neutral-100 dark:border-white dark:text-white dark:hover:bg-neutral-800"
                                    @click="emit('select', ref)"
                                >
                                    Sisipkan
                                </button>
                            </td>
                        </tr>
                        <tr v-if="references.length === 0">
                            <td colspan="4" class="px-5 py-8 text-center text-sm text-neutral-400 dark:text-neutral-500">Tidak ada referensi yang cocok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-neutral-200 px-5 py-2.5 dark:border-neutral-800">
                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ references.length }} referensi</span>
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white"
                        :disabled="page <= 1"
                        aria-label="Halaman sebelumnya"
                        @click="prevPage"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="min-w-12 text-center text-xs text-neutral-500 dark:text-neutral-400">{{ page }} / {{ totalPages }}</span>
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white"
                        :disabled="page >= totalPages"
                        aria-label="Halaman berikutnya"
                        @click="nextPage"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
