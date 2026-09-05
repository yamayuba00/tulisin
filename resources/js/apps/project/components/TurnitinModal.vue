<script setup>
import { X, Wand2 } from 'lucide-vue-next';

defineProps({
    loading: { type: Boolean, default: false },
    result: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['close', 'goto', 'apply', 'keep']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')"></div>

        <div class="relative z-10 flex max-h-[80vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start justify-between border-b border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <div class="flex items-center gap-2">
                    <Wand2 class="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                    <div>
                        <h2 class="text-base font-semibold">Turnitin Similarity</h2>
                        <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Tulis ulang kalimat yang mirip sumber lain agar skor kemiripan turun.</p>
                    </div>
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

            <div v-if="loading" class="flex flex-col items-center gap-3 px-5 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                <span class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-neutral-300 border-t-neutral-900 dark:border-neutral-700 dark:border-t-white"></span>
                Menganalisis dan menulis ulang…
            </div>

            <div v-else-if="result" class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                <div class="rounded-lg border border-neutral-200 p-4 text-center dark:border-neutral-800">
                    <p class="text-3xl font-bold text-neutral-900 dark:text-white">{{ result.similarity }}%</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Perkiraan kemiripan (makin kecil makin baik)</p>
                </div>

                <div v-if="result.matches.length" class="space-y-3">
                    <p class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Bagian terdeteksi &amp; saran tulis ulang</p>

                    <div
                        v-for="(m, i) in result.matches"
                        :key="i"
                        class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800"
                        :class="{ 'opacity-60': m.applied, 'opacity-40 line-through': m.rejected }"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold">{{ m.blockLabel }}</span>
                            <span class="shrink-0 rounded-md border border-neutral-200 px-2 py-0.5 text-[11px] text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                {{ m.similarity }}% kemiripan
                            </span>
                        </div>

                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <div>
                                <p class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500">Sebelum</p>
                                <p class="mt-1 rounded-md border-l-2 border-red-400 bg-red-50 px-2 py-1.5 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ m.matched }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500">Sesudah (orisinal)</p>
                                <p class="mt-1 rounded-md border-l-2 border-emerald-400 bg-emerald-50 px-2 py-1.5 text-xs text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">{{ m.suggestion }}</p>
                            </div>
                        </div>

                        <div v-if="m.blockUid" class="mt-2 flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-neutral-200 px-2 py-1 text-xs text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="emit('goto', m)"
                            >
                                Lompat ke blok
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-neutral-200 px-2 py-1 text-xs text-neutral-600 transition-colors hover:bg-neutral-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                :disabled="m.applied || m.rejected"
                                @click="emit('keep', m)"
                            >
                                Pertahankan Asli
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-neutral-900 px-2 py-1 text-xs font-medium text-neutral-900 transition-colors hover:bg-neutral-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-neutral-800"
                                :disabled="m.applied || m.rejected"
                                @click="emit('apply', m)"
                            >
                                {{ m.applied ? 'Diterapkan' : 'Terapkan Saran' }}
                            </button>
                        </div>
                    </div>
                </div>

                <p v-else class="text-xs text-neutral-400 dark:text-neutral-500">Tidak ada kalimat yang perlu ditulis ulang.</p>
            </div>
        </div>
    </div>
</template>
