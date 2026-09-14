<script setup>
import { RouterLink } from 'vue-router';
import { PenLine, ArrowLeft } from 'lucide-vue-next';
import appName from '../utils/appName';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-neutral-50 dark:bg-neutral-950">
        <!-- Navbar -->
        <header class="px-4 pt-4 lg:px-6">
            <div class="mx-auto flex h-16 max-w-4xl items-center justify-between rounded-2xl border border-neutral-200 bg-white/80 px-4 shadow-sm backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 lg:px-5">
                <RouterLink to="/" class="inline-flex items-center gap-2 text-lg font-bold tracking-tight text-neutral-900 dark:text-white">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                        <PenLine class="h-4 w-4" />
                    </span>
                    {{ appName }}
                </RouterLink>
                <RouterLink to="/" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-600 transition-colors hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white">
                    <ArrowLeft class="h-4 w-4" />
                    Beranda
                </RouterLink>
            </div>
        </header>

        <!-- Konten -->
        <main class="flex flex-1 items-center justify-center px-4 py-16">
            <div class="text-center">
                <p class="text-7xl font-bold tracking-tight text-neutral-900 dark:text-white sm:text-8xl">404</p>
                <h1 class="mt-4 text-xl font-semibold text-neutral-900 dark:text-white">Halaman Tidak Ditemukan</h1>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-neutral-500 dark:text-neutral-400">
                    Halaman yang kamu cari mungkin telah dipindahkan, dihapus, atau alamatnya salah ketik.
                </p>
                <RouterLink
                    to="/"
                    class="mt-6 inline-flex cursor-pointer items-center gap-2 rounded-xl border border-neutral-900 bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                >
                    Kembali ke Beranda
                </RouterLink>
            </div>
        </main>

        <footer class="border-t border-neutral-200 py-6 dark:border-neutral-800">
            <p class="text-center text-xs text-neutral-400 dark:text-neutral-500">&copy; 2026 PT Kerja Tanpa Batas. Semua hak dilindungi.</p>
        </footer>
    </div>
</template>
