<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { PenLine, ArrowLeft, ChevronLeft, ChevronRight, Star } from 'lucide-vue-next';
import { getJson } from '../utils/http';
import { formatDate } from '../utils/format';

const PER_PAGE = 12;

const reviews = ref([]);
const loading = ref(true);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

async function load() {
    loading.value = true;
    try {
        const data = await getJson(`/api/reviews/published?per_page=${PER_PAGE}&page=${page.value}`);
        reviews.value = data.reviews || [];
        lastPage.value = Number(data.last_page) || 1;
        total.value = Number(data.total) || 0;
    } catch {
        reviews.value = [];
    } finally {
        loading.value = false;
    }
}

function goTo(p) {
    const target = Math.min(lastPage.value, Math.max(1, p));
    if (target === page.value) return;
    page.value = target;
    load();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(load);
</script>

<template>
    <div class="min-h-screen bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Navbar -->
        <header class="sticky top-0 z-30 px-4 pt-4 lg:px-6">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between rounded-2xl border border-neutral-200 bg-white/80 px-4 shadow-sm backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 lg:px-5">
                <RouterLink to="/" class="inline-flex items-center gap-2 text-lg font-bold tracking-tight">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                        <PenLine class="h-4 w-4" />
                    </span>
                    Tulisin
                </RouterLink>
                <RouterLink to="/" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-600 transition-colors hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke Beranda
                </RouterLink>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-16 lg:px-6">
            <div class="text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Semua Rating</span>
                <h1 class="mt-3 font-serif text-3xl font-bold tracking-tight">Ulasan pengguna Tulisin</h1>
                <p class="mx-auto mt-3 max-w-xl text-neutral-500 dark:text-neutral-400">
                    {{ total }} ulasan dari pengguna yang sudah menulis bersama Tulisin.
                </p>
            </div>

            <div v-if="loading" class="mt-12 text-center text-sm text-neutral-400 dark:text-neutral-500">
                Memuat ulasan…
            </div>

            <div v-else-if="reviews.length === 0" class="mt-12 rounded-xl border border-dashed border-neutral-300 px-6 py-16 text-center dark:border-neutral-700">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Belum ada ulasan yang dipublikasikan.</p>
            </div>

            <div v-else class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <figure
                    v-for="r in reviews"
                    :key="r.id"
                    class="flex flex-col rounded-xl border border-neutral-200 p-6 transition-all duration-200 hover:-translate-y-1 hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700"
                >
                    <div class="flex gap-0.5 text-amber-400">
                        <Star v-for="i in 5" :key="i" class="h-4 w-4" :class="i <= r.rating ? 'fill-current' : 'fill-transparent text-neutral-300 dark:text-neutral-600'" />
                    </div>
                    <blockquote class="mt-4 flex-1 text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                        "{{ r.text }}"
                    </blockquote>
                    <figcaption class="mt-5 flex items-center gap-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-sm font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                            {{ r.initial }}
                        </span>
                        <div>
                            <p class="text-sm font-medium">{{ r.name }}</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(r.published_at || r.created_at) }}</p>
                        </div>
                    </figcaption>
                </figure>
            </div>

            <!-- Pagination -->
            <div v-if="!loading && lastPage > 1" class="mt-10 flex items-center justify-center gap-3">
                <button
                    type="button"
                    :disabled="page <= 1"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 disabled:pointer-events-none disabled:opacity-40 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="goTo(page - 1)"
                >
                    <ChevronLeft class="h-4 w-4" />
                    Sebelumnya
                </button>
                <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ page }} / {{ lastPage }}</span>
                <button
                    type="button"
                    :disabled="page >= lastPage"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 disabled:pointer-events-none disabled:opacity-40 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="goTo(page + 1)"
                >
                    Berikutnya
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </main>
    </div>
</template>
