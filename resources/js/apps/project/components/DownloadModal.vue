<script setup>
import { computed } from 'vue';
import { FileText, Download, X, Loader2 } from 'lucide-vue-next';

defineProps({
    scopes: { type: Array, default: () => [] },
    exporting: { type: Boolean, default: false },
});

const open = defineModel('open', { type: Boolean, default: false });
const format = defineModel('format', { type: String, default: 'pdf' });

const emit = defineEmits(['download']);

function formatToggleClass(fmt) {
    const active = 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950';
    const idle = 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300';
    return `inline-flex h-9 cursor-pointer items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-medium transition-colors ${format.value === fmt ? active : idle}`;
}

const downloadFormat = computed(() => format.value);
</script>

<template>
    <Transition name="modal-fade">
        <div
            v-if="open"
            class="fixed inset-0 z-[90] flex items-center justify-center p-4 print:hidden"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <div
                    v-if="exporting"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-white/90 px-6 text-center backdrop-blur-sm dark:bg-neutral-950/90"
                >
                    <Loader2 class="h-8 w-8 animate-spin text-neutral-700 dark:text-neutral-200" />
                    <div>
                        <p class="text-sm font-semibold text-neutral-800 dark:text-neutral-100">Mengunduh dokumen…</p>
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Menyiapkan file, mohon tunggu sebentar.</p>
                    </div>
                    <div class="h-1.5 w-44 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-800">
                        <div class="animate-download-progress h-full w-1/3 rounded-full bg-neutral-900 dark:bg-white"></div>
                    </div>
                </div>
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Download Dokumen</h3>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white"
                        @click="open = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" :class="formatToggleClass('pdf')" @click="format = 'pdf'">
                            <FileText class="h-4 w-4" />
                            PDF
                        </button>
                        <button type="button" :class="formatToggleClass('word')" @click="format = 'word'">
                            <Download class="h-4 w-4" />
                            Word
                        </button>
                    </div>
                    <p class="mt-2 text-[11px] text-neutral-400 dark:text-neutral-500">4 koin per unduhan · +1 koin setiap tambahan 10 halaman.</p>
                </div>
                <div class="max-h-[60vh] overflow-y-auto p-2">
                    <template v-for="group in scopes" :key="group.label">
                        <p class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">{{ group.label }}</p>
                        <button
                            v-for="opt in group.items"
                            :key="opt.id"
                            type="button"
                            class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            @click="emit('download', opt)"
                        >
                            <FileText v-if="downloadFormat === 'pdf'" class="h-4 w-4 shrink-0 text-neutral-400 dark:text-neutral-500" />
                            <Download v-else class="h-4 w-4 shrink-0 text-neutral-400 dark:text-neutral-500" />
                            <span class="truncate">{{ opt.label }}</span>
                            <span class="ml-auto shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ opt.cost ?? 0 }} koin</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
@keyframes download-progress {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(400%);
    }
}
.animate-download-progress {
    animation: download-progress 1.2s ease-in-out infinite;
}
</style>
