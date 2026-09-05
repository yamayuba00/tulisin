<script setup>
import { ref, computed } from 'vue';
import { X, ArrowLeft, Printer, RotateCcw, History, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    loading: { type: Boolean, default: false },
    list: { type: Array, default: () => [] },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['close', 'revert', 'delete']);

const selected = ref(null);

function typeLabel(t) {
    return t === 'turnitin' ? 'Turnitin AI Optimizer' : 'Screening Plagiarism';
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

function openReport(entry) {
    selected.value = entry;
}

function back() {
    selected.value = null;
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[c]));
}

// Buka jendela cetak berisi laporan agar bisa di-screenshot / disimpan sebagai PDF.
function printReport(entry) {
    const rows = (entry.matches || []).map((m, i) => `
        <div style="margin:12px 0;padding:10px;border:1px solid #ddd;border-radius:6px;">
            <div style="font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">${i + 1}. ${escapeHtml(m.blockLabel || 'Teks terdeteksi')}</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div style="border-left:3px solid #ef4444;background:#fef2f2;padding:8px;border-radius:4px;">
                    <div style="font-size:11px;color:#999;">Sebelum</div>
                    <div style="font-size:12px;color:#b91c1c;">${escapeHtml(m.matched || '')}</div>
                </div>
                <div style="border-left:3px solid #10b981;background:#ecfdf5;padding:8px;border-radius:4px;">
                    <div style="font-size:11px;color:#999;">Sesudah</div>
                    <div style="font-size:12px;color:#047857;">${escapeHtml(m.suggestion || '')}</div>
                </div>
            </div>
        </div>`).join('');

    const html = `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>${escapeHtml(typeLabel(entry.type))}</title>
</head>
<body style="font-family:Arial,sans-serif;color:#111;max-width:720px;margin:0 auto;padding:24px;">
    <h1 style="font-size:20px;margin:0;">${escapeHtml(typeLabel(entry.type))}</h1>
    <p style="color:#777;font-size:12px;">${formatDate(entry.created_at)}</p>
    <div style="text-align:center;padding:16px;border:1px solid #ddd;border-radius:8px;margin:12px 0;">
        <div style="font-size:40px;font-weight:700;">${entry.score}%</div>
        <div style="font-size:12px;color:#777;">${entry.type === 'turnitin' ? 'Perkiraan terdeteksi sebagai AI' : 'Tingkat kemiripan'}</div>
    </div>
    ${rows || '<p style="color:#999;">Tidak ada bagian yang terdeteksi.</p>'}
    <script>window.onload=function(){setTimeout(function(){window.print();},300);};<\/script>
</body>
</html>`;

    const w = window.open('', '_blank');
    if (!w) return;
    w.document.open();
    w.document.write(html);
    w.document.close();
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[75] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')"></div>

        <div class="relative z-10 flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <!-- Header -->
            <div class="flex items-start justify-between border-b border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <div class="flex items-center gap-2">
                    <History class="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                    <div>
                        <h2 class="text-base font-semibold">Riwayat Hasil AI</h2>
                        <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Lihat ulang &amp; pelajari perubahan kosakata.</p>
                    </div>
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

            <!-- Body -->
            <div class="flex-1 overflow-y-auto px-5 py-4">
                <div v-if="loading" class="flex items-center gap-2 py-10 text-sm text-neutral-500 dark:text-neutral-400">
                    <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-neutral-300 border-t-neutral-900 dark:border-neutral-700 dark:border-t-white"></span>
                    Memuat riwayat…
                </div>

                <!-- Daftar riwayat -->
                <div v-else-if="!selected" class="space-y-2">
                    <p v-if="!list.length" class="py-8 text-center text-sm text-neutral-400 dark:text-neutral-500">
                        Belum ada riwayat. Jalankan Turnitin / Plagiarism dulu.
                    </p>
                    <button
                        v-for="e in list"
                        :key="e.id"
                        type="button"
                        class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-lg border border-neutral-200 px-3 py-2.5 text-left transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:hover:bg-neutral-800"
                        @click="openReport(e)"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ typeLabel(e.type) }}</p>
                            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(e.created_at) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="rounded-md border border-neutral-200 px-2 py-0.5 text-xs font-semibold dark:border-neutral-800">{{ e.score }}%</span>
                            <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ (e.matches || []).length }} bagian</span>
                        </div>
                    </button>
                </div>

                <!-- Detail laporan -->
                <div v-else class="space-y-4">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 text-sm text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                        @click="back"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Kembali
                    </button>

                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold">{{ typeLabel(selected.type) }}</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(selected.created_at) }}</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-900 hover:text-white dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-white dark:hover:text-neutral-950"
                            @click="printReport(selected)"
                        >
                            <Printer class="h-4 w-4" />
                            Cetak / Capture
                        </button>
                    </div>

                    <div class="rounded-lg border border-neutral-200 p-4 text-center dark:border-neutral-800">
                        <p class="text-3xl font-bold text-neutral-900 dark:text-white">{{ selected.score }}%</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ selected.type === 'turnitin' ? 'Perkiraan terdeteksi sebagai AI (makin kecil makin baik)' : 'Tingkat kemiripan (target < 20%)' }}
                        </p>
                    </div>

                    <div v-if="selected.matches && selected.matches.length" class="space-y-3">
                        <p class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Perbandingan sebelum vs sesudah</p>

                        <div
                            v-for="(m, i) in selected.matches"
                            :key="i"
                            class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold">{{ m.blockLabel }}</span>
                                <span class="shrink-0 rounded-md border border-neutral-200 px-2 py-0.5 text-[11px] text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                    {{ m.similarity ?? selected.score }}%
                                </span>
                            </div>

                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <div>
                                    <p class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500">Sebelum</p>
                                    <p class="mt-1 rounded-md border-l-2 border-red-400 bg-red-50 px-2 py-1.5 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ m.matched }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500">Sesudah (saran)</p>
                                    <p class="mt-1 rounded-md border-l-2 border-emerald-400 bg-emerald-50 px-2 py-1.5 text-xs text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">{{ m.suggestion }}</p>
                                </div>
                            </div>

                            <div v-if="m.blockUid" class="mt-2 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-neutral-200 px-2 py-1 text-xs text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                    @click="emit('revert', m)"
                                >
                                    <RotateCcw class="h-3.5 w-3.5" />
                                    Pulihkan ke asli
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-xs text-neutral-400 dark:text-neutral-500">Tidak ada bagian yang terdeteksi.</p>

                    <div class="flex justify-end border-t border-neutral-200 pt-3 dark:border-neutral-800">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs text-neutral-500 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-800"
                            @click="emit('delete', selected)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Hapus riwayat ini
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
