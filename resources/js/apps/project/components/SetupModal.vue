<script setup>
import { computed } from 'vue';
import { X } from 'lucide-vue-next';
import FilterSelect from '../../../components/FilterSelect.vue';
import AppButton from '../../../components/AppButton.vue';

defineProps({
    pageFormatOptions: { type: Array, default: () => [] },
    pageOrientationOptions: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
});

const open = defineModel('open', { type: Boolean, default: false });
const mode = defineModel('mode', { type: String, default: 'setup' });
const draftName = defineModel('draftName', { type: String, default: '' });
const draftCategory = defineModel('draftCategory', { type: String, default: 'Lainnya' });
const draftFormat = defineModel('draftFormat', { type: String, default: 'A4' });
const draftOrientation = defineModel('draftOrientation', { type: String, default: 'portrait' });
const draftMargins = defineModel('draftMargins', { type: Object, default: () => ({ top: 2.54, right: 2.54, bottom: 2.54, left: 2.54 }) });

const emit = defineEmits(['confirm', 'cancel']);

const isEdit = computed(() => mode.value === 'edit');
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    >
        <div class="absolute inset-0 bg-black/50" @click="emit('cancel')"></div>

        <div class="relative z-10 w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold">{{ isEdit ? 'Edit Project' : 'Siapkan Project' }}</h2>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        {{ isEdit ? 'Ubah nama, kategori, format, orientasi, dan margin project.' : 'Atur nama, kategori, format, orientasi, dan margin sebelum mulai menyusun.' }}
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    aria-label="Tutup"
                    @click="emit('cancel')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nama Project</label>
                    <input
                        v-model="draftName"
                        type="text"
                        placeholder="Mis. Laporan Akhir / Tugas Akhir"
                        class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                    />
                </div>

                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kategori</label>
                    <div class="mt-1">
                        <FilterSelect
                            v-model="draftCategory"
                            :options="categoryOptions"
                            placeholder="Pilih kategori"
                        />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Format Halaman</label>
                    <div class="mt-1">
                        <FilterSelect
                            v-model="draftFormat"
                            :options="pageFormatOptions"
                            placeholder="Pilih format"
                        />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Orientasi Halaman</label>
                    <div class="mt-1">
                        <FilterSelect
                            v-model="draftOrientation"
                            :options="pageOrientationOptions"
                            placeholder="Pilih orientasi"
                        />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Margin Halaman (cm)</label>
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[11px] text-neutral-400 dark:text-neutral-500">Atas</label>
                            <input
                                v-model.number="draftMargins.top"
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
                                v-model.number="draftMargins.bottom"
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
                                v-model.number="draftMargins.left"
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
                                v-model.number="draftMargins.right"
                                type="number"
                                min="0"
                                max="8"
                                step="0.1"
                                class="mt-0.5 w-full rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="emit('cancel')"
                >
                    Batal
                </button>
                <AppButton @click="emit('confirm')">{{ isEdit ? 'Simpan' : 'Mulai' }}</AppButton>
            </div>
        </div>
    </div>
</template>
