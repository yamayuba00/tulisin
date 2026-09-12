<script setup>
import { ref, computed, onMounted } from 'vue';
import { X, ArrowRight, Sparkles } from 'lucide-vue-next';
import { getJson } from '../utils/http';

const STORAGE_KEY = 'tulisin.promo.last-shown';
const visible = ref(false);
const promo = ref(null);

function todayKey() {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
}

function dismiss() {
    visible.value = false;
    try {
        localStorage.setItem(STORAGE_KEY, todayKey());
    } catch {
        // Abaikan bila localStorage tidak tersedia.
    }
}

const isExternal = computed(() => /^https?:\/\//i.test(promo.value?.cta_link || ''));

onMounted(async () => {
    let shownToday = false;
    try {
        shownToday = localStorage.getItem(STORAGE_KEY) === todayKey();
    } catch {
        shownToday = false;
    }

    if (shownToday) return;

    try {
        const data = await getJson('/api/promo');
        if (data.promo) {
            promo.value = data.promo;
            visible.value = true;
        }
    } catch {
        // Gagal memuat promo — biarkan modal tetap tertutup.
    }
});
</script>

<template>
    <div v-if="visible && promo" class="fixed inset-0 z-40 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="dismiss"></div>

        <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl shadow-black/20 dark:border-neutral-800 dark:bg-neutral-900">
            <button
                type="button"
                class="absolute right-3 top-3 z-10 inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg bg-white/80 text-neutral-500 backdrop-blur transition-colors hover:bg-white hover:text-neutral-900 dark:bg-neutral-900/80 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                aria-label="Tutup"
                @click="dismiss"
            >
                <X class="h-4 w-4" />
            </button>

            <div class="flex flex-col sm:flex-row">
                <!-- Banner horizontal: visual promo -->
                <div class="relative flex min-h-40 items-center justify-center overflow-hidden bg-neutral-900 px-8 py-8 sm:min-h-80 sm:w-2/5 sm:py-10">
                    <div class="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="pointer-events-none absolute -bottom-12 -right-8 h-48 w-48 rounded-full bg-white/10 blur-3xl"></div>

                    <div class="relative text-center text-white">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold">
                            <Sparkles class="h-3.5 w-3.5" />
                            {{ promo.badge }}
                        </span>
                        <p v-if="promo.highlight" class="mt-4 text-3xl font-bold leading-tight">{{ promo.highlight }}</p>
                        <p v-if="promo.subtitle" class="mt-1 text-sm text-white/70">{{ promo.subtitle }}</p>
                    </div>
                </div>

                <!-- Konten -->
                <div class="flex flex-1 flex-col justify-center px-6 py-6 sm:w-3/5 sm:px-8">
                    <h2 v-if="promo.title" class="text-xl font-semibold">{{ promo.title }}</h2>
                    <p v-if="promo.description" class="mt-2 text-sm leading-relaxed text-neutral-500 dark:text-neutral-400">
                        {{ promo.description }}
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a
                            v-if="isExternal"
                            :href="promo.cta_link"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-neutral-900 bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                            @click="dismiss"
                        >
                            {{ promo.cta_label || 'Lihat' }}
                            <ArrowRight class="h-4 w-4" />
                        </a>
                        <RouterLink
                            v-else
                            :to="promo.cta_link || '/apps/u/topup'"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-neutral-900 bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                            @click="dismiss"
                        >
                            {{ promo.cta_label || 'Lihat' }}
                            <ArrowRight class="h-4 w-4" />
                        </RouterLink>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-xl px-3 py-2.5 text-sm font-medium text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                            @click="dismiss"
                        >
                            {{ promo.cta_secondary_label || 'Nanti saja' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
