<script setup>
import { ref, computed, nextTick } from 'vue';
import { Sparkles, X, Send, Loader2, LayoutGrid, Plus } from 'lucide-vue-next';
import { request } from '../../../utils/http';
import { renderMarkdown } from '../../../utils/markdown';

const props = defineProps({
    summary: { type: String, default: '' },
    isEmpty: { type: Boolean, default: false },
    blockCount: { type: Number, default: 0 },
    pageCount: { type: Number, default: 0 },
    projectUuid: { type: String, default: '' },
    blockTypes: { type: Array, default: () => [] },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['close', 'apply']);

const input = ref('');
const format = ref('');
const messages = ref([]);
const listEl = ref(null);
const sending = ref(false);

const formatOptions = [
    { value: '', label: 'Umum (otomatis)' },
    { value: 'skripsi', label: 'Skripsi' },
    { value: 'tesis', label: 'Tesis' },
    { value: 'disertasi', label: 'Disertasi' },
    { value: 'makalah', label: 'Makalah' },
    { value: 'jurnal', label: 'Jurnal' },
    { value: 'laporan', label: 'Laporan' },
    { value: 'proposal', label: 'Proposal' },
    { value: 'esai', label: 'Esai' },
];

// Trigger pemicu utama: disesuaikan dengan kondisi canvas (kosong vs terisi).
const starterPrompts = computed(() => {
    if (props.isEmpty) {
        return [
            'Buatkan kerangka dokumen lengkap',
            'Tulis paragraf pembuka Bab 1 Pendahuluan',
            'Buat abstrak 200 kata',
        ];
    }
    return [
        'Lanjutkan paragraf berikutnya',
        'Ringkas isi halaman ini',
        'Beri saran judul bab berikutnya',
    ];
});

const blockTypeLabels = computed(() => props.blockTypes.map((b) => b.label).join(', '));

function scrollBottom() {
    nextTick(() => listEl.value?.scrollTo({ top: listEl.value.scrollHeight }));
}

function close() {
    emit('close');
    open.value = false;
}

async function send(text) {
    const t = (text ?? input.value).trim();
    if (!t) return;
    messages.value.push({ role: 'user', text: t });
    if (!text) input.value = '';
    sending.value = true;

    // Agent selalu membaca seluruh canvas (summary) + UUID project aktif.
    try {
        const res = await request('/api/ai/generate', {
            method: 'POST',
            body: JSON.stringify({
                agent: 'canvas',
                message: t,
                context: props.summary,
                uuid: props.projectUuid,
                format: format.value,
            }),
        });
        const reply = res.ok
            ? (res.data?.reply || '')
            : (res.data?.error || 'Gagal menghubungi AI.');
        messages.value.push({ role: 'assistant', text: reply });
    } catch {
        messages.value.push({ role: 'assistant', text: 'Gagal menghubungi AI. Coba lagi.' });
    } finally {
        sending.value = false;
        scrollBottom();
    }
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}
</script>

<template>
    <Transition name="modal-fade">
        <div
            v-if="open"
            class="fixed inset-0 z-[80] flex items-center justify-center p-4 print:hidden"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-black/50" @click="close"></div>

            <div class="relative z-10 flex h-[80vh] max-h-[640px] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <!-- Header -->
                <div class="flex items-start justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <Sparkles class="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Agent AI Canvas</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Membaca seluruh isi canvas kamu.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white"
                        aria-label="Tutup"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Konteks canvas + komponen sidebar kiri -->
                <div class="flex items-center gap-2 border-b border-neutral-200 bg-neutral-50 px-4 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900/40 dark:text-neutral-400">
                    <LayoutGrid class="h-3.5 w-3.5 shrink-0" />
                    <span>
                        {{ isEmpty
                            ? 'Canvas kosong — mulai dari trigger di bawah.'
                            : `${blockCount} blok · ${pageCount} halaman · blok tersedia: ${blockTypeLabels || '—'}` }}
                    </span>
                </div>

                <!-- Pesan / empty state -->
                <div ref="listEl" class="flex-1 space-y-3 overflow-y-auto p-4">
                    <template v-if="messages.length === 0">
                        <div class="rounded-xl border border-dashed border-neutral-300 p-4 text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                            <p class="font-medium text-neutral-700 dark:text-neutral-200">
                                {{ isEmpty ? 'Canvas kamu masih kosong.' : 'Canvas sudah terisi.' }}
                            </p>
                            <p class="mt-1 text-xs">Berikut pemicu yang bisa langsung kamu pilih:</p>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <button
                                    v-for="p in starterPrompts"
                                    :key="p"
                                    type="button"
                                    class="cursor-pointer rounded-full border border-neutral-200 px-3 py-1.5 text-left text-xs text-neutral-600 transition-colors hover:border-neutral-400 hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                                    @click="send(p)"
                                >{{ p }}</button>
                            </div>
                            <p v-if="blockTypes.length" class="mt-3 text-[11px] text-neutral-400 dark:text-neutral-500">
                                Agent juga membaca komponen blok di sidebar kiri: {{ blockTypeLabels }}.
                            </p>
                        </div>
                    </template>

                    <template v-else>
                        <div
                            v-for="(m, i) in messages"
                            :key="i"
                            class="text-sm"
                            :class="m.role === 'user' ? 'text-right' : 'text-left'"
                        >
                            <span
                                class="inline-block max-w-full whitespace-pre-wrap rounded-lg px-3 py-2 text-left"
                                :class="m.role === 'user'
                                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950'
                                    : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200'"
                                v-html="renderMarkdown(m.text)"
                            ></span>

                            <!-- Tombol generate: terapkan jawaban agent ke canvas -->
                            <div v-if="m.role === 'assistant' && m.text" class="mt-1.5">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-300 px-2.5 py-1 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-900 hover:text-white dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-white dark:hover:text-neutral-950"
                                    @click="emit('apply', m.text)"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                    Generate ke Canvas
                                </button>
                            </div>
                        </div>

                        <div v-if="sending" class="flex items-center gap-2 text-sm text-neutral-400 dark:text-neutral-500">
                            <Loader2 class="h-4 w-4 animate-spin" />
                            Agent sedang membaca canvas…
                        </div>
                    </template>
                </div>

                <!-- Trigger (selalu tampil setelah ada percakapan) -->
                <div v-if="messages.length" class="flex flex-wrap gap-1.5 border-t border-neutral-200 px-4 py-2 dark:border-neutral-800">
                    <button
                        v-for="p in starterPrompts"
                        :key="p"
                        type="button"
                        class="cursor-pointer rounded-full border border-neutral-200 px-2.5 py-1 text-left text-[11px] text-neutral-500 transition-colors hover:border-neutral-400 hover:text-neutral-800 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200"
                        @click="send(p)"
                    >{{ p }}</button>
                </div>

                <!-- Input -->
                <div class="border-t border-neutral-200 p-3 dark:border-neutral-800">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="shrink-0 text-xs font-medium text-neutral-500 dark:text-neutral-400">Format:</span>
                        <select
                            v-model="format"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent px-2.5 py-1.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                        >
                            <option v-for="o in formatOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <textarea
                            v-model="input"
                            rows="2"
                            placeholder="Tanyakan atau minta agent mengerjakan sesuatu di canvas…"
                            class="min-h-0 flex-1 resize-none rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            @keydown="onKeydown"
                        ></textarea>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-neutral-900 text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                            aria-label="Kirim"
                            @click="send()"
                        >
                            <Send class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
