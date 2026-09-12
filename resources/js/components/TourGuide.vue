<script setup>
import { ref, computed } from 'vue';
import {
    X,
    ArrowRight,
    Home,
    Sparkles,
    FolderKanban,
    Users,
    LayoutTemplate,
    FileText,
    Library,
    Wallet,
    Handshake,
} from 'lucide-vue-next';
import appName from '../utils/appName';

const emit = defineEmits(['done']);

const steps = [
    { icon: Home, title: 'Beranda', desc: 'Pusat kendali: ringkasan aktivitas, saldo koin, dan dokumen terbaru dalam satu layar.' },
    { icon: Sparkles, title: 'Agent AI', desc: 'Asisten menulis AI untuk mengembangkan ide, merapikan kalimat, dan menyusun kerangka.' },
    { icon: FolderKanban, title: 'Dokumen Saya', desc: 'Kelola semua dokumen/project kamu, dari kerangka hingga ekspor PDF & Word.' },
    { icon: Users, title: 'Dokumen Publik', desc: 'Temukan dokumen publik pengguna lain untuk inspirasi dan referensi.' },
    { icon: LayoutTemplate, title: 'Template', desc: 'Mulai cepat dengan template siap pakai sesuai jenis dokumen dan format kampus.' },
    { icon: FileText, title: 'Paper & Jurnal', desc: 'Cari dan baca paper & jurnal akademik dari Crossref.' },
    { icon: Library, title: `${appName} Workspace`, desc: 'Ruang kerja blok untuk menyusun tulisan secara visual dan terstruktur.' },
    { icon: Wallet, title: 'Isi Saldo & Koin', desc: 'Isi koin untuk fitur AI, dan cari bonus lewat menu Dapatkan Koin.' },
    { icon: Handshake, title: 'Afiliasi & Ulasan', desc: 'Ajak teman lewat referral, dan lihat ulasan dari sesama pengguna.' },
];

const step = ref(0);
const isLast = computed(() => step.value === steps.length - 1);

function next() {
    if (isLast.value) {
        emit('done');
    } else {
        step.value += 1;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

        <div class="relative w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-6 shadow-2xl shadow-black/20 dark:border-neutral-800 dark:bg-neutral-900">
            <button
                type="button"
                class="absolute right-4 top-4 inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                aria-label="Lewati tur"
                @click="emit('done')"
            >
                <X class="h-4 w-4" />
            </button>

            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                    <component :is="steps[step].icon" class="h-5 w-5" />
                </span>
                <span class="text-xs font-semibold text-neutral-400 dark:text-neutral-500">
                    Tur Aplikasi · {{ step + 1 }} / {{ steps.length }}
                </span>
            </div>

            <h2 class="mt-4 text-xl font-semibold">{{ steps[step].title }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-neutral-500 dark:text-neutral-400">{{ steps[step].desc }}</p>

            <div class="mt-6 flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center rounded-xl px-3 py-2 text-sm font-medium text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    @click="emit('done')"
                >
                    Lewati tur
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-neutral-900 bg-neutral-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                    @click="next"
                >
                    {{ isLast ? 'Selesai' : 'Berikutnya' }}
                    <ArrowRight class="h-4 w-4" />
                </button>
            </div>

            <div class="mt-5 flex justify-center gap-1.5">
                <span
                    v-for="(s, i) in steps"
                    :key="i"
                    class="h-1.5 rounded-full transition-all"
                    :class="i === step ? 'w-6 bg-neutral-900 dark:bg-white' : 'w-1.5 bg-neutral-200 dark:bg-neutral-700'"
                ></span>
            </div>
        </div>
    </div>
</template>
