<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowLeft, BookMarked, User, Calendar, Hash, Link2, Quote, FileText, BookOpen, Layers } from 'lucide-vue-next';
import { parseCSLItem, formatBibliography, CSL_STYLES } from '../../../utils/csl-formatter';

const props = defineProps({
    reference: { type: Object, default: null },
});

const router = useRouter();
const style = ref('APA');

const item = computed(() => (props.reference ? parseCSLItem(props.reference) : null));

const TYPE_LABELS = {
    'article-journal': 'Artikel Jurnal',
    book: 'Buku',
    chapter: 'Bab Buku',
    'paper-conference': 'Prosiding',
    thesis: 'Skripsi/Tesis/Disertasi',
    report: 'Laporan',
    webpage: 'Web',
};

const typeLabel = computed(() => TYPE_LABELS[props.reference?.type] || props.reference?.type || 'Referensi');

const authorsText = computed(() => {
    if (!item.value) return '';
    return item.value.authors.map((a) => [a.given, a.family].filter(Boolean).join(' ')).join('; ');
});

const bibliography = computed(() => {
    if (!item.value) return '';
    return formatBibliography(item.value, style.value, 1);
});

const abstractText = computed(() => props.reference?._abstract || props.reference?._snippet || '');

const keywords = computed(() => (Array.isArray(props.reference?._keywords) ? props.reference._keywords : []));

function back() {
    router.push('/apps/u/workspace');
}
</script>

<template>
    <div class="flex h-screen flex-col bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Bar atas -->
        <header class="flex h-16 shrink-0 items-center gap-3 border-b border-neutral-200 px-4 dark:border-neutral-800 lg:px-6">
            <button
                type="button"
                title="Kembali ke Workspace"
                class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white"
                @click="back"
            >
                <ArrowLeft class="h-5 w-5" />
            </button>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">Tulisin Workspace</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Mode baca (read-only)</p>
            </div>
        </header>

        <!-- Konten -->
        <div class="flex-1 overflow-y-auto">
            <!-- Referensi tidak ditemukan -->
            <div v-if="!reference" class="mx-auto max-w-3xl px-4 py-16 text-center lg:px-6">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Referensi tidak ditemukan.</p>
                <button
                    type="button"
                    class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="back"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke Workspace
                </button>
            </div>

            <!-- Detail referensi -->
            <div v-else class="mx-auto max-w-3xl px-4 py-8 lg:px-6">
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <!-- Judul & identitas -->
                    <div class="border-b border-neutral-200 p-6 dark:border-neutral-800">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-neutral-200 px-2 py-0.5 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">{{ typeLabel }}</span>
                            <span v-if="item?.year" class="inline-flex items-center gap-1 text-xs text-neutral-400">
                                <Calendar class="h-3.5 w-3.5" />
                                {{ item.year }}
                            </span>
                        </div>
                        <h1 class="mt-3 text-xl font-bold leading-snug">{{ item?.title || 'Tanpa Judul' }}</h1>
                    </div>

                    <!-- Field metadata -->
                    <div class="grid gap-4 p-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                <User class="h-3.5 w-3.5" />
                                Penulis
                            </p>
                            <p class="mt-1 text-sm text-neutral-700 dark:text-neutral-200">{{ authorsText || 'Anonim' }}</p>
                        </div>

                        <div v-if="item?.containerTitle" class="sm:col-span-2">
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                <BookMarked class="h-3.5 w-3.5" />
                                Jurnal / Sumber
                            </p>
                            <p class="mt-1 text-sm text-neutral-700 dark:text-neutral-200">{{ item.containerTitle }}</p>
                        </div>

                        <div>
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                <Layers class="h-3.5 w-3.5" />
                                Volume / No
                            </p>
                            <p class="mt-1 text-sm text-neutral-700 dark:text-neutral-200">
                                {{ [item?.volume ? `Vol. ${item.volume}` : '', item?.issue ? `No. ${item.issue}` : ''].filter(Boolean).join(', ') || '—' }}
                            </p>
                        </div>

                        <div>
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                <Hash class="h-3.5 w-3.5" />
                                Halaman
                            </p>
                            <p class="mt-1 text-sm text-neutral-700 dark:text-neutral-200">{{ item?.page || '—' }}</p>
                        </div>

                        <div v-if="item?.doi" class="sm:col-span-2">
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                <Link2 class="h-3.5 w-3.5" />
                                DOI
                            </p>
                            <p class="mt-1 text-sm text-neutral-700 dark:text-neutral-200">{{ item.doi }}</p>
                        </div>

                        <div v-if="reference._filename">
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                <FileText class="h-3.5 w-3.5" />
                                File
                            </p>
                            <p class="mt-1 text-sm text-neutral-700 dark:text-neutral-200">{{ reference._filename }}</p>
                            <a
                                v-if="reference._fileUrl"
                                :href="reference._fileUrl"
                                target="_blank"
                                rel="noopener"
                                class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-neutral-700 underline underline-offset-2 hover:text-neutral-900 dark:text-neutral-200 dark:hover:text-white"
                            >
                                <ExternalLink class="h-3.5 w-3.5" />
                                Lihat File PDF
                            </a>
                        </div>

                        <div v-if="reference._pages">
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                <BookOpen class="h-3.5 w-3.5" />
                                Jumlah Halaman PDF
                            </p>
                            <p class="mt-1 text-sm text-neutral-700 dark:text-neutral-200">{{ reference._pages }} halaman</p>
                        </div>
                    </div>

                    <!-- Abstrak -->
                    <div v-if="abstractText" class="border-t border-neutral-200 p-6 dark:border-neutral-800">
                        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                            <Quote class="h-3.5 w-3.5" />
                            Abstrak
                        </p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">{{ abstractText }}</p>
                    </div>

                    <!-- Kata kunci -->
                    <div v-if="keywords.length" class="border-t border-neutral-200 p-6 dark:border-neutral-800">
                        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                            <Tags class="h-3.5 w-3.5" />
                            Kata Kunci
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="k in keywords"
                                :key="k"
                                class="rounded-full border border-neutral-200 px-2.5 py-0.5 text-xs text-neutral-600 dark:border-neutral-800 dark:text-neutral-300"
                            >
                                {{ k }}
                            </span>
                        </div>
                    </div>

                    <!-- Pratinjau sitasi -->
                    <div class="border-t border-neutral-200 p-6 dark:border-neutral-800">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Pratinjau Sitasi (Daftar Pustaka)</p>
                            <select v-model="style" class="rounded-lg border border-neutral-200 bg-white px-2 py-1 text-sm outline-none focus:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900">
                                <option v-for="s in CSL_STYLES" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <p class="mt-3 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm leading-relaxed text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200" v-html="bibliography"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
