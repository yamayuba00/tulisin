<script setup>
import { ref, nextTick } from 'vue';
import { Sparkles, Send, Loader2 } from 'lucide-vue-next';
import { request } from '../utils/http';
import { renderMarkdown } from '../utils/markdown';

const props = defineProps({
    context: { type: String, default: '' },
    canvasContext: { type: String, default: '' },
    placeholder: { type: String, default: 'Tulis pertanyaan...' },
    prompts: { type: Array, default: () => [] },
});

const input = ref('');
const messages = ref([]);
const listEl = ref(null);
const sending = ref(false);

function scrollBottom() {
    nextTick(() => listEl.value?.scrollTo({ top: listEl.value.scrollHeight }));
}

async function send(text) {
    const t = (text ?? input.value).trim();
    if (!t || sending.value) return;
    messages.value.push({ role: 'user', text: t });
    if (!text) input.value = '';
    sending.value = true;

    try {
        const res = await request('/api/ai/generate', {
            method: 'POST',
            body: JSON.stringify({
                agent: 'copilot',
                message: t,
                context: props.canvasContext || props.context || '',
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
    <div class="flex h-full flex-col overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800">
        <div class="flex items-center gap-2 border-b border-neutral-200 px-4 py-2.5 dark:border-neutral-800">
            <Sparkles class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
            <span class="text-sm font-semibold">Asisten AI</span>
        </div>

        <div v-if="context" class="border-b border-neutral-200 bg-neutral-50 px-4 py-1.5 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900/40 dark:text-neutral-400">
            Konteks: {{ context || 'Umum' }}
        </div>

        <div ref="listEl" class="flex-1 space-y-3 overflow-y-auto p-4">
            <div v-if="messages.length === 0" class="text-sm text-neutral-400 dark:text-neutral-500">
                Tanyakan apa saja seputar dokumen kamu. Aku akan menemani di setiap bagian.
            </div>

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
            </div>

            <div v-if="sending" class="flex items-center gap-2 text-sm text-neutral-400 dark:text-neutral-500">
                <Loader2 class="h-4 w-4 animate-spin" />
                Mengetik…
            </div>
        </div>

        <div v-if="prompts.length" class="flex flex-wrap gap-1.5 border-t border-neutral-200 px-4 py-2 dark:border-neutral-800">
            <button
                v-for="p in prompts"
                :key="p"
                type="button"
                class="cursor-pointer rounded-full border border-neutral-200 px-2.5 py-1 text-left text-[11px] text-neutral-500 transition-colors hover:border-neutral-400 hover:text-neutral-800 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200"
                @click="send(p)"
            >{{ p }}</button>
        </div>

        <div class="border-t border-neutral-200 p-3 dark:border-neutral-800">
            <div class="flex items-end gap-2">
                <textarea
                    v-model="input"
                    rows="2"
                    :placeholder="placeholder"
                    class="min-h-0 flex-1 resize-none rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                    @keydown="onKeydown"
                ></textarea>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-neutral-900 text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white disabled:cursor-not-allowed disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                    aria-label="Kirim"
                    :disabled="sending"
                    @click="send()"
                >
                    <Send class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
