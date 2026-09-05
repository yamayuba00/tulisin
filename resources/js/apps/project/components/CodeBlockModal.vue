<script setup>
import { X, ClipboardPaste, Check } from 'lucide-vue-next';

const open = defineModel('open', { type: Boolean, default: false });
const code = defineModel('code', { type: String, default: '' });

const emit = defineEmits(['save', 'close']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')"></div>

        <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <div>
                    <h2 class="text-base font-semibold">Blok Kode</h2>
                    <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Tempel kode kamu di bawah. Pewarnaan sintaks diterapkan otomatis di canvas.</p>
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

            <div class="flex-1 overflow-auto p-4">
                <textarea
                    v-model="code"
                    rows="18"
                    spellcheck="false"
                    autocomplete="off"
                    autocapitalize="off"
                    placeholder="// Tempel kode di sini..."
                    class="w-full resize-none rounded-lg border border-neutral-200 bg-neutral-50 p-4 font-mono text-sm leading-relaxed text-neutral-900 outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:border-neutral-400"
                ></textarea>
            </div>

            <div class="flex items-center justify-between border-t border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <span class="inline-flex items-center gap-1.5 text-xs text-neutral-400 dark:text-neutral-500">
                    <ClipboardPaste class="h-4 w-4" />
                    Tempel langsung dari editor kamu (Ctrl+V / Cmd+V).
                </span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="emit('close')"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-900 px-4 py-2 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                        @click="emit('save')"
                    >
                        <Check class="h-4 w-4" />
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
