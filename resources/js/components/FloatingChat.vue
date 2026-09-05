<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { MessageCircle, X, Send, Sparkles } from 'lucide-vue-next';

// Cangkang konteks (knowledge base) per jalur/route.
// Setiap jalur punya `label`, `system` (instruksi AI), dan `fallback`
// (jawaban sementara sebelum terhubung ke backend). Tambah jalur baru di sini.
const CONTEXTS = {
    landing: {
        label: 'Landing',
        system: 'Kamu adalah asisten Tulisin, platform penulisan dokumen akademik berbasis AI. Bantu pengunjung memahami fitur, paket harga, dan cara kerja Tulisin.',
        fallback: 'Terima kasih atas pertanyaannya! Tulisin membantu menyusun skripsi, tesis, jurnal, dan dokumen akademik lain dengan block canvas, asisten AI, serta format otomatis sesuai standar kampus.',
    },
    // dashboard: { label: 'Dashboard', system: '...', fallback: '...' },
    // builder: { label: 'Builder', system: '...', fallback: '...' },
};

const props = defineProps({
    context: {
        type: String,
        default: 'landing',
    },
    suggestions: {
        type: Array,
        default: () => [],
    },
});

const ctx = computed(() => CONTEXTS[props.context] ?? CONTEXTS.landing);

const open = ref(false);
const input = ref('');
const sending = ref(false);
const messages = ref([]);
const body = ref(null);

onMounted(() => {
    messages.value = [
        { from: 'ai', text: `Halo! Saya asisten Tulisin (konteks: ${ctx.value.label}). Tanya apa saja — saya akan menjawab sesuai konteks halaman ini.` },
    ];
});

function toggle() {
    open.value = !open.value;
    if (open.value) scrollToBottom();
}

async function send(textOverride) {
    const text = (textOverride ?? input.value).trim();
    if (!text || sending.value) return;

    messages.value.push({ from: 'user', text });
    input.value = '';
    sending.value = true;
    scrollToBottom();

    // Payload yang akan dikirim ke backend AI (cangkang siap pakai).
    const payload = {
        context: props.context,
        system: ctx.value.system,
        message: text,
        history: messages.value.slice(0, -1),
    };

    try {
        await new Promise((r) => setTimeout(r, 700)); // simulasi latensi

        // TODO: ganti dengan panggilan backend sungguhan, contoh:
        // const res = await fetch('/api/chat', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(payload),
        // });
        // const data = await res.json();
        // const reply = data.reply;
        const reply = ctx.value.fallback;

        messages.value.push({ from: 'ai', text: reply });
    } catch {
        messages.value.push({ from: 'ai', text: 'Maaf, terjadi kendala. Coba lagi sebentar lagi.' });
    } finally {
        sending.value = false;
        scrollToBottom();
    }
}

function scrollToBottom() {
    nextTick(() => {
        if (body.value) body.value.scrollTop = body.value.scrollHeight;
    });
}
</script>

<template>
    <div class="fixed bottom-5 right-5 z-50 flex flex-col items-end">
        <!-- Panel chat -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-3 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-3 opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="mb-3 flex w-80 max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950"
                style="height: 28rem"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                            <Sparkles class="h-3.5 w-3.5" />
                        </span>
                        <div class="leading-tight">
                            <p class="text-sm font-semibold">Asisten Tulisin</p>
                            <p class="text-[11px] text-neutral-400 dark:text-neutral-500">Online · siap membantu</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-900 dark:hover:text-neutral-200"
                        aria-label="Tutup"
                        @click="open = false"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Body -->
                <div ref="body" class="flex-1 space-y-3 overflow-y-auto bg-neutral-50 p-4 dark:bg-neutral-900/40">
                    <div
                        v-for="(m, i) in messages"
                        :key="i"
                        class="max-w-[85%] rounded-xl px-3 py-2 text-sm leading-relaxed"
                        :class="m.from === 'user'
                            ? 'ml-auto rounded-tr-sm border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900'
                            : 'rounded-tl-sm bg-neutral-900 text-neutral-100 dark:bg-white dark:text-neutral-900'"
                    >
                        {{ m.text }}
                    </div>

                    <!-- Saran cepat -->
                    <div v-if="suggestions.length && messages.length === 1" class="flex flex-wrap gap-1.5">
                        <button
                            v-for="s in suggestions"
                            :key="s"
                            type="button"
                            class="cursor-pointer rounded-full border border-neutral-200 bg-white px-3 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            @click="send(s)"
                        >
                            {{ s }}
                        </button>
                    </div>

                    <!-- Indikator mengetik -->
                    <div v-if="sending" class="flex items-center gap-1.5 rounded-xl rounded-tl-sm bg-neutral-900 px-3 py-2.5 dark:bg-white">
                        <span class="h-1.5 w-1.5 animate-typing rounded-full bg-neutral-400"></span>
                        <span class="h-1.5 w-1.5 animate-typing rounded-full bg-neutral-400" style="animation-delay: 0.15s"></span>
                        <span class="h-1.5 w-1.5 animate-typing rounded-full bg-neutral-400" style="animation-delay: 0.3s"></span>
                    </div>
                </div>

                <!-- Input -->
                <form class="flex items-center gap-2 border-t border-neutral-200 p-3 dark:border-neutral-800" @submit.prevent="send()">
                    <input
                        v-model="input"
                        type="text"
                        placeholder="Tulis pertanyaan…"
                        class="flex-1 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none placeholder:text-neutral-400 focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900 dark:placeholder:text-neutral-500"
                    />
                    <button
                        type="submit"
                        class="inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-neutral-900 text-white transition-colors hover:bg-neutral-700 disabled:opacity-50 dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                        :disabled="!input.trim() || sending"
                        aria-label="Kirim"
                    >
                        <Send class="h-4 w-4" />
                    </button>
                </form>
            </div>
        </transition>

        <!-- Tombol mengambang -->
        <button
            type="button"
            class="inline-flex h-14 w-14 cursor-pointer items-center justify-center rounded-full border border-neutral-900 bg-neutral-900 text-white shadow-lg transition-transform hover:scale-105 dark:border-white dark:bg-white dark:text-neutral-950"
            :aria-label="open ? 'Tutup chat' : 'Buka chat'"
            @click="toggle"
        >
            <X v-if="open" class="h-5 w-5" />
            <MessageCircle v-else class="h-5 w-5" />
        </button>
    </div>
</template>

<style scoped>
@keyframes typing {
    0%,
    60%,
    100% {
        opacity: 0.3;
        transform: translateY(0);
    }
    30% {
        opacity: 1;
        transform: translateY(-3px);
    }
}
.animate-typing {
    animation: typing 1.2s ease-in-out infinite;
}
</style>
