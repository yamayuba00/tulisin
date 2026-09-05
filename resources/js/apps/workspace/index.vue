<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Upload, FileText, Loader2, Trash2, BookMarked, FileSearch, Quote, Library, Eye, Coins } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import { pdfToCSL, listReferences, addReferences, removeReference } from '../../utils/workspaceLibrary';
import { parseCSLItem, formatBibliography, authorYearLabel } from '../../utils/csl-formatter';
import { request } from '../../utils/http';
import { toast } from '../../utils/toast';
import { creditPricing, loadCreditPricing } from '../../utils/creditPricing';

const TYPE_OPTIONS = [
    { value: 'article-journal', label: 'Artikel Jurnal' },
    { value: 'book', label: 'Buku' },
    { value: 'chapter', label: 'Bab Buku' },
    { value: 'paper-conference', label: 'Prosiding' },
    { value: 'thesis', label: 'Skripsi/Tesis/Disertasi' },
    { value: 'report', label: 'Laporan' },
    { value: 'webpage', label: 'Web' },
];

const fileInput = ref(null);
const dragActive = ref(false);
const processing = ref(false);
const processingMsg = ref('Membaca struktur PDF…');

const router = useRouter();

// Draft hasil ekstraksi (bisa diedit sebelum disimpan).
const draft = ref(null);

// Perpustakaan referensi tersimpan.
const library = ref([]);

// Konfirmasi sebelum unggah + generate (memotong koin + kuota storage).
const confirmGenerateOpen = ref(false);
const pendingFile = ref(null);

onMounted(() => {
    library.value = listReferences();
    loadCreditPricing();
});

function openPicker() {
    fileInput.value?.click();
}

function onInputChange(e) {
    const file = e.target.files?.[0];
    if (file) handleFile(file);
    e.target.value = '';
}

function onDrop(e) {
    dragActive.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) handleFile(file);
}

async function handleFile(file) {
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.pdf')) {
        toast('Hanya file PDF yang didukung saat ini.', 'warning');
        return;
    }
    // Tahan dulu; minta konfirmasi sebelum unggah & generate
    // supaya file tidak terkirim ke object storage sebelum user setuju.
    pendingFile.value = file;
    confirmGenerateOpen.value = true;
}

// Setuju generate: potong koin, unggah ke object storage, lalu generate metadata AI.
async function confirmGenerate() {
    if (!pendingFile.value) return;
    const file = pendingFile.value;
    pendingFile.value = null;
    confirmGenerateOpen.value = false;

    const cost = Number(creditPricing.value.ai_generate) || 5;
    if (!(await spendCredits(cost, 'ai_generate'))) return;

    processing.value = true;
    processingMsg.value = 'Mengunggah & membaca PDF…';
    draft.value = null;

    let fileInfo = null;
    let text = '';
    try {
        const fd = new FormData();
        fd.append('file', file);
        const up = await request('/api/workspace/upload', { method: 'POST', body: fd });
        if (!up.ok) {
            throw new Error(up.data?.error || 'Gagal mengunggah file.');
        }
        fileInfo = up.data;
        text = fileInfo.text || '';
    } catch (e) {
        toast(e?.message || 'Gagal membaca file PDF.', 'error');
        processing.value = false;
        return;
    }

    processingMsg.value = 'Menganalisis dengan AI…';
    let ai = null;
    try {
        const pr = await request('/api/workspace/parse', {
            method: 'POST',
            body: JSON.stringify({ text: text.slice(0, 8000) }),
        });
        if (pr.ok) ai = pr.data;
    } catch {
        ai = null;
    } finally {
        processing.value = false;
    }

    buildDraft(fileInfo, text, ai);
}

// Batal: jangan unggah/generate sama sekali.
function cancelGenerate() {
    pendingFile.value = null;
    confirmGenerateOpen.value = false;
}

function buildDraft(fileInfo, text, ai) {
    draft.value = {
        type: ai?.type || 'article-journal',
        title: ai?.title || '',
        author: Array.isArray(ai?.authors) ? ai.authors.join('; ') : '',
        year: String(ai?.year || ''),
        doi: ai?.doi || '',
        journal: ai?.journal || '',
        volume: ai?.volume || '',
        issue: ai?.issue || '',
        page: ai?.pages || '',
        abstract: ai?.abstract || '',
        keywords: Array.isArray(ai?.keywords) ? ai.keywords : [],
        pageCount: fileInfo.pageCount || 1,
        snippet: text.slice(0, 600),
        filename: fileInfo.filename || '',
        fileId: fileInfo.id || '',
        fileUrl: fileInfo.url || '',
    };
}

// Potong saldo koin untuk suatu fitur. Return true bila berhasil.
async function spendCredits(amount, reason) {
    try {
        const res = await request('/api/wallet/spend', {
            method: 'POST',
            body: JSON.stringify({ credits: amount, reason }),
        });
        if (res.ok) return true;
        showToast(res.data?.error || 'Saldo koin tidak mencukupi.');
        return false;
    } catch {
        showToast('Gagal memotong koin. Coba lagi.');
        return false;
    }
}

function showToast(message) {
    toast(message);
}

function saveDraft() {
    if (!draft.value) return;
    addReferences([pdfToCSL(draft.value, draft.value.filename)]);
    library.value = listReferences();
    draft.value = null;
}

function resetDraft() {
    draft.value = null;
}

async function deleteRef(id) {
    const ref = library.value.find((r) => r.id === id);

    // Hapus file PDF dari object storage terlebih dahulu (hanya file ini).
    if (ref?._fileId) {
        try {
            const res = await request(`/api/workspace/files/${ref._fileId}`, { method: 'DELETE' });
            if (!res.ok) {
                showToast(res.data?.error || 'Gagal menghapus file di cloud.');
                return;
            }
        } catch {
            showToast('Gagal menghapus file di cloud. Coba lagi.');
            return;
        }
    }

    removeReference(id);
    library.value = listReferences();
}

function viewRef(id) {
    router.push({ path: '/apps/u/project', query: { builder: id, workspace: 'true', edit: 'false' } });
}

function preview(ref) {
    return formatBibliography(parseCSLItem(ref), 'APA', 1);
}

function typeLabel(value) {
    return TYPE_OPTIONS.find((t) => t.value === value)?.label || value;
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader
            title="Tulisin Workspace"
            description="Unggah PDF dan baca strukturnya (judul, penulis, tahun, DOI) untuk dijadikan sitasi di builder."
        />

        <div class="mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
            <FileSearch class="h-4 w-4 shrink-0" />
            Metadata diisi otomatis oleh AI (DeepSeek). Periksa kembali hasilnya sebelum disimpan.
        </div>

        <!-- Konfirmasi generate metadata AI (memotong koin) -->
        <div v-if="confirmGenerateOpen" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="cancelGenerate"></div>
            <div class="relative z-10 w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-300">
                        <Coins class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Generate metadata dengan AI?</h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Sekali generate memotong <strong class="font-semibold text-neutral-900 dark:text-white">{{ Number(creditPricing.ai_generate) || 5 }} koin</strong>. Lanjutkan?
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="cancelGenerate"
                    >
                        Batal
                    </button>
                    <AppButton @click="confirmGenerate">
                        <Coins class="h-4 w-4" />
                        Generate
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- Zona unggah -->
        <div
            class="rounded-xl border-2 border-dashed p-8 text-center transition-colors"
            :class="dragActive ? 'border-neutral-900 bg-neutral-50 dark:border-white dark:bg-neutral-900' : 'border-neutral-300 dark:border-neutral-700'"
            @dragover.prevent="dragActive = true"
            @dragleave.prevent="dragActive = false"
            @drop.prevent="onDrop"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                <Upload class="h-6 w-6" />
            </div>
            <p class="mt-4 text-sm font-medium">Seret &amp; lepas PDF ke sini</p>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">atau</p>
            <AppButton class="mt-3" @click="openPicker">
                Pilih File PDF
            </AppButton>
            <input ref="fileInput" type="file" accept="application/pdf" class="hidden" @change="onInputChange" />
        </div>

        <!-- Status proses -->
        <div v-if="processing" class="mt-6 flex items-center gap-3 rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
            <Loader2 class="h-5 w-5 animate-spin text-neutral-500" />
            <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ processingMsg }}</span>
        </div>

        <!-- Hasil ekstraksi -->
        <div v-if="draft" class="mt-6 rounded-xl border border-neutral-200 dark:border-neutral-800">
            <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <div class="flex items-center gap-2 text-sm font-medium">
                    <FileText class="h-4 w-4" />
                    {{ draft.filename }}
                </div>
                <span class="text-xs text-neutral-400">{{ draft.pageCount }} halaman</span>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm sm:col-span-2">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Judul</span>
                    <input v-model="draft.title" type="text" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900" />
                </label>
                <label class="flex flex-col gap-1 text-sm sm:col-span-2">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Jurnal / Sumber</span>
                    <input v-model="draft.journal" type="text" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900" />
                </label>
                <label class="flex flex-col gap-1 text-sm sm:col-span-2">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Penulis (pisahkan dengan ;)</span>
                    <input v-model="draft.author" type="text" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Tahun</span>
                    <input v-model="draft.year" type="text" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Volume</span>
                    <input v-model="draft.volume" type="text" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Nomor / Issue</span>
                    <input v-model="draft.issue" type="text" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Halaman (mis. 98-108)</span>
                    <input v-model="draft.page" type="text" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">DOI</span>
                    <input v-model="draft.doi" type="text" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Tipe Referensi</span>
                    <select v-model="draft.type" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900">
                        <option v-for="t in TYPE_OPTIONS" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </label>
                <div class="sm:col-span-2">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Abstrak (hasil AI)</span>
                    <textarea v-model="draft.abstract" rows="4" class="mt-1 w-full resize-y rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">Cuplikan teks mentah</span>
                    <p class="mt-1 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300">
                        {{ draft.snippet || '— teks tidak ditemukan (kemungkinan PDF hasil scan).' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 border-t border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <AppButton @click="saveDraft">
                    <Library class="h-4 w-4" />
                    Simpan ke Perpustakaan
                </AppButton>
                <AppButton variant="outline" @click="resetDraft">Batal</AppButton>
            </div>
        </div>

        <!-- Daftar referensi tersimpan -->
        <div class="mt-8">
            <div class="mb-3 flex items-center gap-2">
                <BookMarked class="h-4 w-4 text-neutral-500" />
                <h2 class="font-semibold">Perpustakaan Referensi ({{ library.length }})</h2>
            </div>

            <p v-if="!library.length" class="rounded-xl border border-neutral-200 px-5 py-8 text-center text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                Belum ada referensi. Unggah PDF untuk memulai.
            </p>

            <div v-else class="flex flex-col gap-3">
                <div
                    v-for="ref in library"
                    :key="ref.id"
                    class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold">{{ ref.title }}</span>
                                <span class="rounded-full border border-neutral-200 px-2 py-0.5 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                    {{ typeLabel(ref.type) }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ authorYearLabel(ref) || 'Tanpa penulis' }}
                                <template v-if="ref.DOI"> · DOI: {{ ref.DOI }}</template>
                            </p>
                            <p class="mt-2 flex items-start gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                                <Quote class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span>{{ preview(ref) }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                title="Lihat di builder"
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
                                @click="viewRef(ref.id)"
                            >
                                <Eye class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                title="Hapus"
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-400 transition-colors hover:text-red-500 dark:border-neutral-800 dark:text-neutral-500"
                                @click="deleteRef(ref.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
