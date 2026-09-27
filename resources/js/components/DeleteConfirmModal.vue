<script setup>
import { X } from 'lucide-vue-next';
import AppButton from './AppButton.vue';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    title: { type: String, default: 'Hapus data ini?' },
    message: { type: String, default: 'Data akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.' },
    confirmLabel: { type: String, default: 'Hapus' },
    busy: { type: Boolean, default: false },
    showIcon: { type: Boolean, default: true },
});

const emit = defineEmits(['confirm', 'cancel']);

function onCancel() {
    if (props.busy) return;
    emit('cancel');
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="onCancel"></div>

        <div class="relative z-10 w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start gap-3">
                <span
                    v-if="showIcon"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600 dark:bg-red-950/60 dark:text-red-400"
                >
                    <slot name="icon"><X class="h-5 w-5" /></slot>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-base font-semibold text-neutral-900 dark:text-white">{{ title }}</h2>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                            aria-label="Tutup"
                            @click="onCancel"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <p v-if="message" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ message }}</p>
                    <slot />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    :disabled="busy"
                    @click="onCancel"
                >
                    Batal
                </button>
                <AppButton :disabled="busy" @click="emit('confirm')">
                    <slot name="confirm-icon" />
                    {{ busy ? 'Menghapus…' : confirmLabel }}
                </AppButton>
            </div>
        </div>
    </div>
</template>