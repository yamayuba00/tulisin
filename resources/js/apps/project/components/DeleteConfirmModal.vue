<script setup>
import { X } from 'lucide-vue-next';
import AppButton from '../../../components/AppButton.vue';

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('cancel')"></div>

        <div class="relative z-10 w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-semibold">Hapus blok?</h2>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Blok ini masih memiliki isi. Konten yang sudah ditulis akan ikut terhapus.
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

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="emit('cancel')"
                >
                    Batal
                </button>
                <AppButton @click="emit('confirm')">Hapus</AppButton>
            </div>
        </div>
    </div>
</template>
