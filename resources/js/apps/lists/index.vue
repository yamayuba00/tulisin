<script setup>
import { computed, onMounted, ref } from 'vue';
import { Eye, Search, X, Lock, Loader2 } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import { PROJECT_CATEGORIES } from '../../utils/projectCategories';
import { getJson } from '../../utils/http';
import { formatDate } from '../../utils/format';
import { toast } from '../../utils/toast';

// Daftar project publik diambil dari database (bukan data contoh lokal).
const projects = ref([]);
const loading = ref(true);

const activeCategory = ref('Semua');
const query = ref('');
const selected = ref(null);

const categories = ['Semua', ...PROJECT_CATEGORIES];

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return projects.value.filter((p) => {
        const matchCat = activeCategory.value === 'Semua' || p.category === activeCategory.value;
        const matchQ = !q || p.title.toLowerCase().includes(q) || p.author.toLowerCase().includes(q);
        return matchCat && matchQ;
    });
});

function chipClass(cat) {
    return activeCategory.value === cat
        ? 'border-neutral-900 bg-neutral-900 text-white dark:border-white dark:bg-white dark:text-neutral-950'
        : 'border-neutral-200 text-neutral-600 hover:border-neutral-300 dark:border-neutral-700 dark:text-neutral-300 dark:hover:border-neutral-600';
}

async function loadProjects() {
    loading.value = true;
    try {
        const data = await getJson('/api/projects/public');
        projects.value = (data.projects || []).map((p) => ({
            ...p,
            updatedAt: formatDate(p.updatedAt),
        }));
    } catch (e) {
        toast(e.message, 'error');
        projects.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(loadProjects);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Lists Project" description="Jelajahi project dari pengguna lain. Mode lihat saja (read-only)." />

        <!-- Filter -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="c in categories"
                    :key="c"
                    type="button"
                    class="cursor-pointer rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                    :class="chipClass(c)"
                    @click="activeCategory = c"
                >
                    {{ c }}
                </button>
            </div>
            <div class="relative lg:ml-auto lg:w-72">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                <input
                    v-model="query"
                    type="text"
                    placeholder="Cari judul atau penulis..."
                    class="w-full rounded-lg border border-neutral-200 bg-transparent py-2 pl-9 pr-3 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                />
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="mt-6 flex items-center justify-center rounded-lg border border-dashed border-neutral-300 px-6 py-12 text-neutral-400 dark:border-neutral-700">
            <Loader2 class="h-5 w-5 animate-spin" />
            <span class="ml-2 text-sm">Memuat project publik…</span>
        </div>

        <!-- Grid -->
        <div v-else-if="filtered.length > 0" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="p in filtered" :key="p.id" class="flex flex-col rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
                <span class="inline-flex w-fit rounded-full border border-neutral-200 px-2 py-0.5 text-[11px] font-medium text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                    {{ p.category }}
                </span>
                <h3 class="mt-3 font-semibold leading-snug">{{ p.title }}</h3>
                <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">oleh {{ p.author }} · {{ p.updatedAt }}</p>
                <p class="mt-2 line-clamp-2 text-sm text-neutral-500 dark:text-neutral-400">{{ p.description }}</p>
                <div class="mt-4 flex items-center justify-between border-t border-neutral-100 pt-3 dark:border-neutral-900">
                    <span class="inline-flex items-center gap-1 text-xs text-neutral-400 dark:text-neutral-500">
                        <Lock class="h-3.5 w-3.5" /> Read-only
                    </span>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-neutral-900 hover:underline dark:text-white"
                        @click="selected = p"
                    >
                        <Eye class="h-4 w-4" /> Lihat
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="mt-6 rounded-lg border border-dashed border-neutral-300 px-6 py-12 text-center dark:border-neutral-700">
            <p class="text-sm text-neutral-500 dark:text-neutral-400">Belum ada project publik yang tersedia.</p>
        </div>

        <!-- Detail modal (read-only) -->
        <div v-if="selected" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="selected = null"></div>
            <div class="relative z-10 w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="inline-flex rounded-full border border-neutral-200 px-2 py-0.5 text-[11px] font-medium text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                            {{ selected.category }}
                        </span>
                        <h2 class="mt-2 text-lg font-semibold">{{ selected.title }}</h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">oleh {{ selected.author }} · {{ selected.updatedAt }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                        aria-label="Tutup"
                        @click="selected = null"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <p class="mt-4 text-sm text-neutral-600 dark:text-neutral-300">{{ selected.description }}</p>

                <div class="mt-4 flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
                    <Lock class="h-4 w-4 shrink-0" />
                    Mode lihat saja — kamu tidak bisa mengubah project orang lain.
                </div>

                <div class="mt-5 flex justify-end">
                    <AppButton variant="outline" @click="selected = null">Tutup</AppButton>
                </div>
            </div>
        </div>
    </div>
</template>
