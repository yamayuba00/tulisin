<script setup>
import { onMounted, ref } from 'vue';
import { Search, BookOpenCheck, ExternalLink, FileText, Loader2 } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import { getJson } from '../../utils/http';
import { toast } from '../../utils/toast';

const query = ref('');
const loading = ref(false);
const searched = ref(false);
const results = ref([]);
const total = ref(0);
const activeTab = ref('works');
const limit = ref(5);

const tabs = [
    { value: 'works', label: 'Works' },
    { value: 'journals', label: 'Journals' },
    { value: 'other', label: 'Lainnya' },
];

const exampleQueries = [
    'Artificial Intelligence',
    'Machine Learning',
    'Climate Change',
    'Pendidikan',
    'Kesehatan',
];

async function search() {
    const q = query.value.trim();
    if (!q) return;
    loading.value = true;
    searched.value = true;
    try {
        const data = await getJson(`/api/papers/search?query=${encodeURIComponent(q)}&tab=${activeTab.value}&limit=${limit.value}`);
        results.value = data.papers || [];
        total.value = data.total || 0;
    } catch (e) {
        toast(e.message || 'Gagal memuat hasil pencarian.', 'error');
        results.value = [];
        total.value = 0;
    } finally {
        loading.value = false;
    }
}

function setTab(tab) {
    activeTab.value = tab;
    if (searched.value && query.value.trim()) search();
}

function setLimit() {
    if (searched.value && query.value.trim()) search();
}

function useExample(q) {
    query.value = q;
    search();
}

function authorList(authors) {
    if (!authors || authors.length === 0) return 'Penulis tidak diketahui';
    return authors.length > 3 ? `${authors.slice(0, 3).join(', ')} et al.` : authors.join(', ');
}

function typeLabel(r) {
    if (r.type === 'journal') return 'Jurnal';
    if (r.type === 'work') {
        if (!r.subtype || r.subtype === 'journal-article') return 'Paper';
        const labels = {
            book: 'Buku',
            'book-chapter': 'Bab Buku',
            'proceedings-article': 'Prosiding',
            proceedings: 'Prosiding',
            dataset: 'Dataset',
            report: 'Laporan',
            dissertation: 'Disertasi',
            monograph: 'Monograf',
            'reference-book': 'Buku Referensi',
            'posted-content': 'Preprint',
            standard: 'Standar',
        };
        return labels[r.subtype] || r.subtype;
    }
    return 'Paper';
}

function truncate(text, max = 260) {
    if (!text) return '';
    return text.length > max ? `${text.slice(0, max).trim()}…` : text;
}

onMounted(() => {
    query.value = exampleQueries[0];
    search();
});

</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Paper / Journal" description="Cari dan baca paper & journal dari Crossref." />

        <form class="relative mb-6" @submit.prevent="search">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
            <input
                v-model="query"
                type="text"
                placeholder="Cari paper, judul, penulis, DOI, atau topik..."
                class="w-full rounded-lg border border-neutral-200 bg-transparent py-2.5 pl-10 pr-4 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                @keyup.enter="search"
            />
            <button
                type="submit"
                :disabled="loading || !query.trim()"
                class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-md bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white transition-opacity disabled:opacity-40 dark:bg-white dark:text-neutral-900"
            >
                Cari
            </button>
        </form>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs text-neutral-400 dark:text-neutral-500">Contoh:</span>
            <button
                v-for="q in exampleQueries"
                :key="q"
                type="button"
                class="cursor-pointer rounded-full border border-neutral-200 px-3 py-1 text-xs text-neutral-600 transition-colors hover:border-neutral-400 hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:text-white"
                @click="useExample(q)"
            >
                {{ q }}
            </button>
        </div>

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1 rounded-lg border border-neutral-200 p-1 dark:border-neutral-800">
                <button
                    v-for="t in tabs"
                    :key="t.value"
                    type="button"
                    class="cursor-pointer rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="activeTab === t.value
                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                        : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'"
                    @click="setTab(t.value)"
                >
                    {{ t.label }}
                </button>
            </div>
            <label class="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                Tampilkan
                <select
                    v-model.number="limit"
                    class="rounded-md border border-neutral-200 bg-transparent px-2 py-1.5 text-xs outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                    @change="setLimit"
                >
                    <option :value="5">5 hasil</option>
                    <option :value="10">10 hasil</option>
                    <option :value="20">20 hasil</option>
                </select>
            </label>
        </div>

        <EmptyState
            v-if="!searched"
            title="Research dari Crossref"
            description="Ketik kata kunci lalu tekan Enter. Pilih tab Works / Journals / Lainnya untuk memfilter, dan atur jumlah hasil (default 5)."
        >
            <template #icon>
                <BookOpenCheck class="h-6 w-6" />
            </template>
        </EmptyState>

        <div v-else-if="loading" class="flex items-center justify-center gap-2 py-16 text-neutral-500">
            <Loader2 class="h-5 w-5 animate-spin" />
            <span class="text-sm">Mencari paper...</span>
        </div>

        <EmptyState
            v-else-if="results.length === 0"
            title="Tidak ada hasil"
            description="Coba kata kunci lain, atau periksa kembali ejaannya."
        >
            <template #icon>
                <BookOpenCheck class="h-6 w-6" />
            </template>
        </EmptyState>

        <div v-else class="space-y-3">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">Ditemukan {{ total }} hasil</p>

            <article
                v-for="r in results"
                :key="r.id"
                class="rounded-lg border border-neutral-200 p-4 transition-colors hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a
                            :href="r.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-semibold leading-snug hover:underline"
                        >
                            {{ r.title }}
                        </a>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            {{ authorList(r.authors) }}
                            <template v-if="r.year"> · {{ r.year }}</template>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <span
                            class="inline-flex items-center rounded-full border border-neutral-200 px-2 py-0.5 text-xs text-neutral-500 dark:border-neutral-700 dark:text-neutral-400"
                        >
                            {{ typeLabel(r) }}
                        </span>
                        <span
                            v-if="r.citationCount"
                            class="inline-flex items-center gap-1 rounded-full border border-neutral-200 px-2 py-0.5 text-xs text-neutral-500 dark:border-neutral-700 dark:text-neutral-400"
                        >
                            {{ r.citationCount }} sitasi
                        </span>
                    </div>
                </div>

                <p v-if="r.abstract" class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">
                    {{ truncate(r.abstract) }}
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-neutral-500 dark:text-neutral-400">
                    <span v-if="r.venue">{{ r.venue }}</span>
                    <span v-if="r.doi">DOI: {{ r.doi }}</span>
                    <a
                        v-if="r.openAccessPdf"
                        :href="r.openAccessPdf"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-neutral-700 hover:underline dark:text-neutral-200"
                    >
                        <FileText class="h-3.5 w-3.5" /> PDF
                    </a>
                    <a
                        :href="r.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-neutral-700 hover:underline dark:text-neutral-200"
                    >
                        <ExternalLink class="h-3.5 w-3.5" /> Buka
                    </a>
                </div>
            </article>
        </div>
    </div>
</template>
