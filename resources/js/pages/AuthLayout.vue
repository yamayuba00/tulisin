<script setup>
import { RouterLink } from 'vue-router';
import { PenLine, ArrowLeft, Check } from 'lucide-vue-next';
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-neutral-50 px-4 py-12 dark:bg-neutral-950">
        <!-- Dekorasi background halus -->
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -top-40 left-1/2 h-[28rem] w-[28rem] -translate-x-1/2 rounded-full bg-indigo-200/40 blur-3xl dark:bg-indigo-500/10"></div>
            <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-fuchsia-200/30 blur-3xl dark:bg-fuchsia-500/10"></div>
        </div>

        <div class="relative w-full max-w-md">
            <!-- Logo -->
            <RouterLink to="/" class="mb-8 flex items-center justify-center gap-2 text-2xl font-bold tracking-tight text-neutral-900 dark:text-white">
                <PenLine class="h-6 w-6" />
                Tulisin
            </RouterLink>

            <!-- Card form -->
            <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-neutral-900 shadow-2xl shadow-neutral-200/60 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white dark:shadow-black/40">
                <slot />
            </div>

            <!-- Trust -->
            <p class="mt-6 flex items-center justify-center gap-1.5 text-xs text-neutral-400 dark:text-neutral-500">
                <Check class="h-3.5 w-3.5 text-emerald-500" />
                Dipercaya mahasiswa dari 200+ kampus
            </p>

            <p class="mt-3 text-center text-xs">
                <RouterLink to="/" class="inline-flex items-center gap-1 text-neutral-400 transition-colors hover:text-neutral-700 dark:text-neutral-500 dark:hover:text-neutral-300">
                    <ArrowLeft class="h-3 w-3" />
                    Kembali ke beranda
                </RouterLink>
            </p>
        </div>
    </div>
</template>
