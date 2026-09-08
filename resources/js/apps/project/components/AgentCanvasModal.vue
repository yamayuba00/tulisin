<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { Sparkles, X, Send, Loader2, LayoutGrid, Plus, Info, Trash2, MessageSquarePlus, Menu } from 'lucide-vue-next';
import { request, streamJson } from '../../../utils/http';
import { renderMarkdown } from '../../../utils/markdown';
import { creditPricing } from '../../../utils/creditPricing';

const props = defineProps({
    summary: { type: String, default: '' },
    isEmpty: { type: Boolean, default: false },
    blockCount: { type: Number, default: 0 },
    pageCount: { type: Number, default: 0 },
    projectUuid: { type: String, default: '' },
    blockTypes: { type: Array, default: () => [] },
    hasSelection: { type: Boolean, default: false },
    spendCredits: { type: Function, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['close', 'apply']);

const input = ref('');
const format = ref('');
const insertMode = ref('after');
const messages = ref([]);
const listEl = ref(null);
const sending = ref(false);
const showHelp = ref(false);
const sidebarOpen = ref(false);

// Sesi chat ala ChatGPT.
const sessions = ref([]);
const activeSessionId = ref(null);
const sessionsLoading = ref(false);

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

const activeSession = computed(() =>
    sessions.value.find((s) => s.id === activeSessionId.value) || null,
);

const costPerMessage = computed(() => Number(creditPricing.value.agent_generate) || 1);

// Apakah balasan berisi fenced block ```canvas ... ``` yang siap dimasukkan.
function hasCanvasFence(text) {
    return /```canvas\s*\n[\s\S]*?```/i.test(String(text || ''));
}

function scrollBottom() {
    nextTick(() => {
        const el = listEl.value;
        if (!el) return;
        el.scrollTop = el.scrollHeight;
        requestAnimationFrame(() => {
            if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight;
        });
    });
}

function close() {
    emit('close');
    open.value = false;
}

// ---- Sesi chat (persist ke database) ----
async function loadSessions() {
    if (!props.projectUuid) return;
    sessionsLoading.value = true;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats`, { method: 'GET' });
        sessions.value = res.ok ? (res.data?.sessions || []) : [];
    } catch {
        sessions.value = [];
    } finally {
        sessionsLoading.value = false;
    }
}

async function createSession() {
    if (!props.projectUuid) return null;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats`, {
            method: 'POST',
            body: JSON.stringify({ title: 'Chat baru' }),
        });
        if (res.ok && res.data) {
            sessions.value.unshift(res.data);
            return res.data.id;
        }
    } catch {
        // abaikan, fallback ke session lokal
    }
    return null;
}

async function selectSession(id) {
    if (!props.projectUuid || id == null) return;
    activeSessionId.value = id;
    sidebarOpen.value = false;
    messages.value = [];
    sending.value = false;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats/${id}`, { method: 'GET' });
        if (res.ok && Array.isArray(res.data?.messages)) {
            messages.value = res.data.messages.map((m) => ({ id: m.id, role: m.role, text: m.content }));
        }
    } catch {
        messages.value = [];
    }
    scrollBottom();
}

async function newChat() {
    const id = await createSession();
    activeSessionId.value = id;
    messages.value = [];
    input.value = '';
    scrollBottom();
    if (id) await selectSession(id);
}

async function deleteSession(id) {
    if (!props.projectUuid || id == null) return;
    try {
        await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats/${id}`, { method: 'DELETE' });
    } catch {
        // abaikan
    }
    sessions.value = sessions.value.filter((s) => s.id !== id);
    if (activeSessionId.value === id) {
        activeSessionId.value = null;
        messages.value = [];
    }
}

async function persistMessage(sessionId, role, content) {
    if (!props.projectUuid || !sessionId) return;
    try {
        await request(`/api/projects/${encodeURIComponent(props.projectUuid)}/ai-chats/${sessionId}/messages`, {
            method: 'POST',
            body: JSON.stringify({ role, content }),
        });
    } catch {
        // non-blocking
    }
}

async function send(text) {
    const t = (text ?? input.value).trim();
    if (!t || sending.value) return;

    // Pastikan ada sesi aktif (buat baru bila belum ada).
    if (!activeSessionId.value) {
        const id = await createSession();
        if (!id) {
            // Tanpa backend tetap bisa chat secara lokal.
            activeSessionId.value = null;
        } else {
            activeSessionId.value = id;
        }
    }

    // Potong koin sesuai tarif admin (agent_generate) sebelum memproses.
    if (props.spendCredits && !(await props.spendCredits(costPerMessage.value, 'agent_generate'))) {
        messages.value.push({ role: 'assistant', text: 'Saldo koin kamu tidak cukup untuk prompt berikutnya. Silakan top up terlebih dahulu.' });
        scrollBottom();
        return;
    }

    const history = messages.value.map((m) => ({ role: m.role, content: m.text }));
    const userMsg = { role: 'user', text: t };
    messages.value.push(userMsg);
    if (!text) input.value = '';
    sending.value = true;
    scrollBottom();

    if (activeSessionId.value) persistMessage(activeSessionId.value, 'user', t);

    // Tempatkan pesan asisten kosong yang akan diisi bertahap (streaming).
    const assistantIndex = messages.value.length;
    messages.value.push({ role: 'assistant', text: '' });
    scrollBottom();

    try {
        await streamJson(
            '/api/ai/generate',
            {
                method: 'POST',
                body: JSON.stringify({
                    agent: 'canvas',
                    message: t,
                    context: props.summary,
                    uuid: props.projectUuid,
                    format: format.value,
                    blockTypes: props.blockTypes.map((b) => b.id),
                    history,
                    stream: true,
                }),
            },
            (ev) => {
                if (ev.delta != null) {
                    messages.value[assistantIndex].text += ev.delta;
                } else if (ev.error != null) {
                    messages.value[assistantIndex].text = ev.error;
                }
                scrollBottom();
            },
        );
        const reply = messages.value[assistantIndex].text;
        if (activeSessionId.value) persistMessage(activeSessionId.value, 'assistant', reply);
    } catch {
        messages.value[assistantIndex].text = 'Gagal menghubungi AI. Coba lagi.';
    } finally {
        sending.value = false;
        scrollBottom();
        // Perbarui pratinjau judul di sidebar.
        if (activeSessionId.value) refreshSessionTitle();
    }
}

function refreshSessionTitle() {
    const firstUser = messages.value.find((m) => m.role === 'user');
    const s = sessions.value.find((x) => x.id === activeSessionId.value);
    if (s && firstUser) {
        const title = firstUser.text.replace(/\s+/g, ' ').trim().slice(0, 60);
        if (s.title === 'Chat baru') s.title = title || s.title;
    }
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

watch(open, (v) => {
    if (v && !sessions.value.length) loadSessions();
});
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

            <div class="relative z-10 flex h-[90vh] max-h-[920px] w-full max-w-5xl overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <!-- Sidebar sesi chat -->
                <aside
                    class="absolute inset-y-0 left-0 z-20 flex w-64 shrink-0 flex-col border-r border-neutral-200 bg-neutral-50 transition-transform duration-200 dark:border-neutral-800 dark:bg-neutral-900/60 md:static md:translate-x-0"
                    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-neutral-200 px-3 py-2.5 dark:border-neutral-800">
                        <span class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Chat</span>
                        <button
                            type="button"
                            class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-neutral-200 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white md:hidden"
                            aria-label="Tutup sidebar"
                            @click="sidebarOpen = false"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="p-2">
                        <button
                            type="button"
                            class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-medium text-neutral-800 transition-colors hover:border-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:border-neutral-500"
                            @click="newChat"
                        >
                            <MessageSquarePlus class="h-4 w-4" />
                            Chat baru
                        </button>
                    </div>

                    <div class="flex-1 space-y-1 overflow-y-auto px-2 pb-2">
                        <p v-if="sessionsLoading" class="px-2 py-3 text-center text-xs text-neutral-400 dark:text-neutral-500">Memuat…</p>
                        <p v-else-if="!sessions.length" class="px-2 py-3 text-center text-xs text-neutral-400 dark:text-neutral-500">
                            Belum ada chat. Mulai chat baru untuk bertanya.
                        </p>
                        <button
                            v-for="s in sessions"
                            :key="s.id"
                            type="button"
                            class="group flex w-full cursor-pointer items-start gap-2 rounded-lg border px-2.5 py-2 text-left transition-colors"
                            :class="s.id === activeSessionId
                                ? 'border-neutral-300 bg-white dark:border-neutral-700 dark:bg-neutral-950'
                                : 'border-transparent hover:bg-neutral-100 dark:hover:bg-neutral-900'"
                            @click="selectSession(s.id)"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium text-neutral-800 dark:text-neutral-200">{{ s.title }}</span>
                                <span v-if="s.preview" class="block truncate text-[11px] text-neutral-400 dark:text-neutral-500">{{ s.preview }}</span>
                            </span>
                            <span
                                class="inline-flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded text-neutral-400 opacity-0 transition-opacity hover:text-red-600 group-hover:opacity-100 dark:hover:text-red-400"
                                @click.stop="deleteSession(s.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </span>
                        </button>
                    </div>
                </aside>

                <!-- Area chat utama -->
                <div class="flex min-w-0 flex-1 flex-col">
                    <!-- Header -->
                    <div class="flex items-start justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white md:hidden"
                                aria-label="Buka daftar chat"
                                @click="sidebarOpen = true"
                            >
                                <Menu class="h-5 w-5" />
                            </button>
                            <Sparkles class="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                            <div>
                                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">{{ activeSession?.title || 'Agent AI Canvas' }}</h3>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Membaca seluruh canvas &amp; menjawab pertanyaan kamu.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white"
                                aria-label="Cara kerja"
                                @click="showHelp = !showHelp"
                            >
                                <Info class="h-5 w-5" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white"
                                aria-label="Tutup"
                                @click="close"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Konteks canvas -->
                    <div class="flex items-center gap-2 border-b border-neutral-200 bg-neutral-50 px-4 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900/40 dark:text-neutral-400">
                        <LayoutGrid class="h-3.5 w-3.5 shrink-0" />
                        <span>
                            {{ isEmpty
                                ? 'Canvas kosong — mulai dari trigger di bawah.'
                                : `${blockCount} blok · ${pageCount} halaman` }}
                        </span>
                        <span class="ml-auto text-[11px] text-neutral-400 dark:text-neutral-500">{{ costPerMessage }} koin / pesan</span>
                    </div>

                    <!-- Cara kerja -->
                    <div v-if="showHelp" class="border-b border-neutral-200 bg-blue-50/70 px-4 py-3 text-xs text-neutral-600 dark:border-neutral-800 dark:bg-blue-950/20 dark:text-neutral-300">
                        <p class="font-semibold">Cara kerja Agent AI Canvas:</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            <li>Buat beberapa chat untuk topik berbeda, mis. "Judul", "Bab 1", "Bab 2".</li>
                            <li>Agent membaca seluruh isi canvas + daftar jenis blok yang tersedia.</li>
                            <li>Tulis permintaan; agent menjawab dengan blok <code class="rounded bg-neutral-200/60 px-1 py-0.5 dark:bg-neutral-800">canvas</code> bila bisa langsung dimasukkan.</li>
                            <li>Klik "Generate ke Canvas" untuk menyisipkan ke dokumen.</li>
                            <li>Seluruh riwayat chat tersimpan dan bisa dibuka kembali.</li>
                        </ul>
                    </div>

                    <!-- Pesan -->
                    <div ref="listEl" class="flex-1 space-y-3 overflow-y-auto p-4">
                        <template v-if="messages.length === 0">
                            <div class="flex h-full flex-col items-center justify-center gap-3 text-center">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                                    <Sparkles class="h-6 w-6" />
                                </span>
                                <p class="max-w-sm text-sm text-neutral-600 dark:text-neutral-300">
                                    Halo! Tanyakan apa saja tentang dokumen kamu, atau minta agent menyusun isinya.
                                </p>
                                <div class="mt-1 flex max-w-sm flex-wrap justify-center gap-1.5">
                                    <button
                                        v-for="p in starterPrompts"
                                        :key="p"
                                        type="button"
                                        class="cursor-pointer rounded-full border border-neutral-200 px-3 py-1.5 text-left text-xs text-neutral-600 transition-colors hover:border-neutral-400 hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                                        @click="send(p)"
                                    >{{ p }}</button>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div
                                v-for="(m, i) in messages"
                                :key="m.id || i"
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

                                <div v-if="m.role === 'assistant' && hasCanvasFence(m.text)" class="mt-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-300 px-2.5 py-1 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-900 hover:text-white dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-white dark:hover:text-neutral-950"
                                        @click="emit('apply', { text: m.text, mode: insertMode })"
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

                        <div class="mb-2 flex items-center gap-2">
                            <span class="shrink-0 text-xs font-medium text-neutral-500 dark:text-neutral-400">Sisipkan:</span>
                            <div class="flex flex-1 gap-1 rounded-lg border border-neutral-200 p-0.5 dark:border-neutral-800">
                                <button
                                    type="button"
                                    class="flex-1 cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-colors"
                                    :class="insertMode === 'after'
                                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950'
                                        : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200'"
                                    @click="insertMode = 'after'"
                                >Setelah blok</button>
                                <button
                                    type="button"
                                    class="flex-1 cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                    :class="insertMode === 'replace'
                                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950'
                                        : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200'"
                                    :disabled="!hasSelection"
                                    :title="hasSelection ? 'Ganti blok yang sedang dipilih' : 'Pilih blok di canvas terlebih dahulu'"
                                    @click="insertMode = 'replace'"
                                >Ganti blok</button>
                            </div>
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
            </div>
        </div>
    </Transition>
</template>
