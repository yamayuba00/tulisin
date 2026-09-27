<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Upload, FileText, Loader2, Trash2, BookMarked, Quote, Library, Eye, Coins, Lock, Copy, Check, X } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import DeleteConfirmModal from '../../components/DeleteConfirmModal.vue';
import { pdfToCSL, listReferences, addReferences, removeReference, syncReferences } from '../../utils/workspaceLibrary';
import { parseCSLItem, formatBibliography, authorYearLabel } from '../../utils/csl-formatter';
import { request, getJson, ensureCsrf } from '../../utils/http';
import { toast } from '../../utils/toast';
import { creditPricing, loadCreditPricing } from '../../utils/creditPricing';
import appName from '../../utils/appName';

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

// Pagination perpustakaan: tampilkan maksimal 8 per halaman.
const PER_PAGE = 8;
const page = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil(library.value.length / PER_PAGE)));
const paged = computed(() => library.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE));
const pageButtons = computed(() => {
    const total = totalPages.value;
    const current = page.value;
    const start = Math.max(1, Math.min(current - 2, total - 4));
    const end = Math.min(total, start + 4);
    const buttons = [];
    for (let i = start; i <= end; i += 1) buttons.push(i);
    return buttons;
});
function goPage(p) {
    if (p < 1 || p > totalPages.value) return;
    page.value = p;
}
function refreshLibrary() {
    library.value = listReferences();
    // Bila halaman aktif melewati batas setelah penghapusan, kembali ke halaman valid.
    if (page.value > totalPages.value) page.value = totalPages.value;
}

// Konfirmasi hapus referensi sebelum benar-benar menghapus.
const deleteTarget = ref(null);
const deletingRef = ref(false);
const confirmDeleteRef = computed(() => library.value.find((r) => r.id === deleteTarget.value) || null);
function askDelete(id) {
    deleteTarget.value = id;
}
function cancelDelete() {
    if (deletingRef.value) return;
    deleteTarget.value = null;
}

// Konfirmasi sebelum unggah + generate (memotong koin + kuota storage).
const confirmGenerateOpen = ref(false);
const pendingFiles = ref([]);
const subscribed = ref(false);

onMounted(async () => {
    // Pastikan cookie CSRF sudah ada sebelum request state-changing (POST),
    // supaya penyimpanan referensi tidak gagal diam-diam dengan status 419.
    try {
        await ensureCsrf();
    } catch {
        // Abaikan; GET di bawah ini akan tetap berjalan.
    }
    library.value = listReferences();
    loadCreditPricing();
    loadSubscription();
    await syncReferences();
    library.value = listReferences();
});

async function loadSubscription() {
    try {
        const data = await getJson('/api/subscription');
        subscribed.value = !!data.active;
    } catch {
        subscribed.value = false;
    }
}

function openPicker() {
    fileInput.value?.click();
}

function onInputChange(e) {
    const files = Array.from(e.target.files || []);
    if (files.length) handleFiles(files);
    e.target.value = '';
}

function onDrop(e) {
    dragActive.value = false;
    const files = Array.from(e.dataTransfer?.files || []);
    if (files.length) handleFiles(files);
}

async function handleFiles(files) {
    if (!files.length) return;
    if (!subscribed.value) {
        toast('Fitur Workspace memerlukan langganan aktif.', 'warning');
        router.push('/apps/u/topup');
        return;
    }
    const pdfs = files.filter((f) => f.name.toLowerCase().endsWith('.pdf'));
    if (!pdfs.length) {
        toast('Hanya file PDF yang didukung saat ini.', 'warning');
        return;
    }
    if (pdfs.length !== files.length) {
        toast('Sebagian file dilewati karena bukan PDF.', 'warning');
    }
    // Tahan dulu; minta konfirmasi sebelum unggah & generate
    // supaya file tidak terkirim ke object storage sebelum user setuju.
    pendingFiles.value = pdfs;
    confirmGenerateOpen.value = true;
}

// Setuju generate: potong koin, unggah ke object storage, lalu generate metadata AI.
async function confirmGenerate() {
    const files = pendingFiles.value;
    pendingFiles.value = [];
    confirmGenerateOpen.value = false;
    if (!files.length) return;

    if (files.length === 1) {
        // Satu file: unggah + generate dulu tanpa memotong koin. Koin dipotong
        // HANYA saat user benar-benar menekan "Simpan ke Perpustakaan", supaya
        // tidak ada koin terbuang bila user membatalkan atau gagal mengatur ulang.
        const file = files[0];

        processing.value = true;
        processingMsg.value = 'Mengunggah & menganalisis PDF…';
        draft.value = null;

        try {
            const { fileInfo, text, ai } = await uploadAndParse(file);
            draft.value = buildDraftData(fileInfo, text, ai);
        } catch (e) {
            toast(e?.message || 'Gagal memproses file PDF.', 'error');
        } finally {
            processing.value = false;
        }
        return;
    }

    // Banyak file: proses langsung ke perpustakaan.
    await processBatch(files);
}

async function processBatch(files) {
    processing.value = true;
    let success = 0;

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        processingMsg.value = `Memproses ${i + 1}/${files.length}: ${file.name}`;

        if (!(await spendCredits('ai_generate'))) {
            break;
        }

        try {
            const { fileInfo, text, ai } = await uploadAndParse(file);
            const data = buildDraftData(fileInfo, text, ai);
            addReferences([pdfToCSL(data, data.filename)]);
            success += 1;
        } catch (e) {
            toast(`Gagal memproses ${file.name}: ${e?.message || 'kesalahan tak dikenal'}`, 'error');
        }
    }

    library.value = listReferences();
    processing.value = false;

    if (success === files.length) {
        toast(`${success} PDF berhasil ditambahkan ke perpustakaan.`, 'success');
    } else if (success > 0) {
        toast(`${success} dari ${files.length} PDF ditambahkan.`, 'success');
    } else {
        toast('Tidak ada PDF yang berhasil diproses.', 'error');
    }
}

async function uploadAndParse(file) {
    const fd = new FormData();
    fd.append('file', file);
    const up = await request('/api/workspace/upload', { method: 'POST', body: fd });
    if (!up.ok) {
        throw new Error(up.data?.error || 'Gagal mengunggah file.');
    }
    const fileInfo = up.data;
    const text = fileInfo.text || '';

    let ai = null;
    try {
        const pr = await request('/api/workspace/parse', {
            method: 'POST',
            body: JSON.stringify({ text: text.slice(0, 8000) }),
        });
        if (pr.ok) ai = pr.data;
    } catch {
        ai = null;
    }

    return { fileInfo, text, ai };
}

// Batal: jangan unggah/generate sama sekali.
function cancelGenerate() {
    pendingFiles.value = [];
    confirmGenerateOpen.value = false;
}

function buildDraftData(fileInfo, text, ai) {
    return {
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

// Potong saldo koin untuk suatu fitur. Biaya dihitung server-side dari reason.
async function spendCredits(reason, { quantity = 1, pages = 0 } = {}) {
    try {
        const res = await request('/api/wallet/spend', {
            method: 'POST',
            body: JSON.stringify({ reason, quantity, pages }),
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

// Simpan referensi: koin dipotong di sini (hanya saat benar-benar menyimpan),
// lalu tambah ke pustaka lokal dan kirim ke server. `addReferences` menambah
// lokal secara sinkron dan mengembalikan promise POST.
const savingDraft = ref(false);

async function saveDraft() {
    if (!draft.value || savingDraft.value) return;

    // Potong koin HANYA saat menyimpan. Kalau gagal, jangan lanjut simpan
    // agar user tidak kehilangan koin tanpa referensi tersimpan.
    if (!(await spendCredits('ai_generate'))) return;

    savingDraft.value = true;
    const item = pdfToCSL(draft.value, draft.value.filename);
    try {
        await addReferences([item]);
    } catch {
        // Cache lokal tetap ada; sinkronisasi berikutnya akan mengulang ke server.
    } finally {
        savingDraft.value = false;
    }
    library.value = listReferences();
    // Item terbaru ada di paling atas; kembali ke halaman pertama agar terlihat.
    page.value = 1;
    draft.value = null;
    toast('Referensi tersimpan ke perpustakaan.', 'success');
}

function resetDraft() {
    draft.value = null;
}

// Salin sitasi APA (in-text) ke clipboard untuk dipakai di builder/Word.
const copiedId = ref('');

async function copyCitation(ref) {
    const label = `${authorYearLabel(ref)}`.trim() || 'Anonim';
    const year = ref.issued?.['date-parts']?.[0]?.[0] || '';
    const text = year ? `${label.replace(new RegExp(`\\s*\\(${year}\\)$`), '')} (${year})` : label;
    try {
        await navigator.clipboard.writeText(text);
        copiedId.value = ref.id;
        setTimeout(() => {
            if (copiedId.value === ref.id) copiedId.value = '';
        }, 1500);
    } catch {
        toast('Gagal menyalin. Coba lagi.', 'error');
    }
}

// Salin entri daftar pustaka (APA, hasil formatter) ke clipboard.
async function copyBibliography(ref) {
    const html = formatBibliography(parseCSLItem(ref), 'APA', 1);
    // Ubah <i>/<b> menjadi teks polos (tanpa markup) agar aman ditempel di mana saja.
    const plain = html.replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'");
    try {
        await navigator.clipboard.writeText(plain);
        toast('Sitasi daftar pustaka (APA) disalin ke clipboard.', 'success');
    } catch {
        toast('Gagal menyalin. Coba lagi.', 'error');
    }
}

async function deleteRef(id) {
    const ref = library.value.find((r) => r.id === id);
    if (!ref || deletingRef.value) return;

    deletingRef.value = true;
    try {
        // Hapus file PDF dari object storage terlebih dahulu (hanya file ini).
        if (ref?._fileId) {
            const res = await request(`/api/workspace/files/${ref._fileId}`, { method: 'DELETE' });
            if (!res.ok) {
                showToast(res.data?.error || 'Gagal menghapus file di cloud.');
                return;
            }
        }

        removeReference(id);
        refreshLibrary();
        deleteTarget.value = null;
        toast('Referensi berhasil dihapus.', 'success');
    } catch {
        showToast('Gagal menghapus file di cloud. Coba lagi.');
    } finally {
        deletingRef.value = false;
    }
}

function viewRef(id) {
    router.push({ path: '/apps/u/project', query: { builder: id, workspace: 'true', edit: 'false' } });
}

function preview(ref) {
    return formatBibliography(parseCSLItem(ref), 'APA', 1);
}

// Pratinjau sitasi APA live untuk draft di modal (ikut berubah saat form diedit).
const draftPreview = computed(() => {
    if (!draft.value) return '';
    const csl = pdfToCSL(draft.value, draft.value.filename || '');
    return formatBibliography(parseCSLItem(csl), 'APA', 1);
});

function typeLabel(value) {
    return TYPE_OPTIONS.find((t) => t.value === value)?.label || value;
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader
            :title="`${appName} Workspace`"
            description="Unggah PDF dan baca strukturnya (judul, penulis, tahun, DOI) untuk dijadikan sitasi di builder."
        />

        <div v-if="!subscribed" class="mb-4 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
            <Lock class="h-4 w-4 shrink-0" />
            <span>Fitur Workspace memerlukan langganan aktif.</span>
            <button type="button" class="cursor-pointer font-semibold underline underline-offset-2" @click="router.push('/apps/u/topup')">Berlangganan</button>
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
                            {{ pendingFiles.length }} file PDF · total
                            <strong class="font-semibold text-neutral-900 dark:text-white">{{ pendingFiles.length * (Number(creditPricing.ai_generate) || 5) }} koin</strong>.
                            Lanjutkan?
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
            <input ref="fileInput" type="file" accept="application/pdf" multiple class="hidden" @change="onInputChange" />
        </div>

        <!-- Status proses -->
        <div v-if="processing" class="mt-6 flex items-center gap-3 rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
            <Loader2 class="h-5 w-5 animate-spin text-neutral-500" />
            <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ processingMsg }}</span>
        </div>

        <!-- Modal hasil ekstraksi: langsung terlihat isinya sebelum disimpan -->
        <div v-if="draft" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="resetDraft"></div>
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <div class="flex items-center justify-between gap-3 border-b border-neutral-200 px-5 py-3.5 dark:border-neutral-800">
                    <div class="flex min-w-0 items-center gap-2">
                        <FileText class="h-5 w-5 shrink-0 text-neutral-500" />
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold">Hasil Ekstraksi: Verifikasi &amp; Simpan</h2>
                            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ draft.filename }} · {{ draft.pageCount }} halaman</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        title="Tutup tanpa menyimpan"
                        class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                        @click="resetDraft"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="min-h-0 flex-1" style="overflow-y: scroll;">
                    <!-- Pratinjau sitasi hasil ekstraksi (italic ter-render seperti aslinya) -->
                    <div class="border-b border-neutral-200 bg-neutral-50 px-5 py-3.5 dark:border-neutral-800 dark:bg-neutral-900/60">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Pratinjau sitasi (APA)</p>
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <p class="mt-1.5 text-sm leading-relaxed text-neutral-700 dark:text-neutral-200" v-html="draftPreview"></p>
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
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-neutral-200 px-5 py-3.5 dark:border-neutral-800">
                    <AppButton variant="outline" :disabled="savingDraft" @click="resetDraft">Batal</AppButton>
                    <AppButton :disabled="savingDraft" @click="saveDraft">
                        <Loader2 v-if="savingDraft" class="h-4 w-4 animate-spin" />
                        <Library v-else class="h-4 w-4" />
                        {{ savingDraft ? 'Menyimpan…' : 'Simpan ke Perpustakaan' }}
                    </AppButton>
                </div>
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
                    v-for="ref in paged"
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
                                <!-- eslint-disable-next-line vue/no-v-html -->
                                <span v-html="preview(ref)"></span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                :title="copiedId === ref.id ? 'Sitasi (Penulis, Tahun) tersalin' : 'Salin sitasi (Penulis, Tahun)'"
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 transition-colors"
                                :class="copiedId === ref.id ? 'border-emerald-300 text-emerald-600 dark:border-emerald-800 dark:text-emerald-400' : 'text-neutral-500 hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white'"
                                @click="copyCitation(ref)"
                            >
                                <Check v-if="copiedId === ref.id" class="h-4 w-4" />
                                <Copy v-else class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                title="Salin sitasi daftar pustaka (APA)"
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
                                @click="copyBibliography(ref)"
                            >
                                <Quote class="h-4 w-4" />
                            </button>
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
                                @click="askDelete(ref.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination perpustakaan: hanya tampil jika > 8 referensi -->
                <div v-if="totalPages > 1" class="mt-2 flex items-center justify-between gap-3 rounded-xl border border-neutral-200 px-4 py-2.5 dark:border-neutral-800">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                        Halaman {{ page }} dari {{ totalPages }} · {{ library.length }} referensi
                    </span>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            :disabled="page <= 1"
                            class="flex h-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 px-3 text-sm text-neutral-600 transition-colors disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300"
                            @click="goPage(page - 1)"
                        >‹</button>
                        <button
                            v-for="p in pageButtons"
                            :key="p"
                            type="button"
                            class="flex h-8 cursor-pointer items-center justify-center rounded-lg border px-3 text-sm transition-colors"
                            :class="p === page ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                            @click="goPage(p)"
                        >{{ p }}</button>
                        <button
                            type="button"
                            :disabled="page >= totalPages"
                            class="flex h-8 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 px-3 text-sm text-neutral-600 transition-colors disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300"
                            @click="goPage(page + 1)"
                        >›</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal konfirmasi hapus referensi -->
        <DeleteConfirmModal
            :open="!!confirmDeleteRef"
            title="Hapus referensi?"
            message=""
            :busy="deletingRef"
            @confirm="deleteRef(confirmDeleteRef.id)"
            @cancel="cancelDelete"
        >
            <template #icon><Trash2 class="h-5 w-5" /></template>
            <template #confirm-icon><Trash2 v-if="!deletingRef" class="h-4 w-4" /></template>
            <template #default>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    <span class="font-medium text-neutral-800 dark:text-neutral-200">"{{ confirmDeleteRef.title }}"</span>
                    akan dihapus dari perpustakaan dan tidak bisa dikembalikan.
                    <template v-if="confirmDeleteRef._fileId">File PDF terkait juga akan dihapus dari penyimpanan.</template>
                </p>
            </template>
        </DeleteConfirmModal>
    </div>
</template>
