<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
    Menu,
    ArrowLeft,
    Pencil,
    Clock,
    Coins,
    Plus,
    Eye,
    Printer,
    Ruler,
    Download,
    Settings2,
    Sparkles,
    Share2,
    Rocket,
    MoreHorizontal,
} from 'lucide-vue-next';
import ThemeToggle from '../../../components/ThemeToggle.vue';

defineProps({
    projectName: { type: String, default: '' },
    projectId: { type: String, default: '' },
    lastEditedLabel: { type: String, default: '' },
    totalCredits: { type: Number, default: 0 },
});

const showGuides = defineModel('showGuides', { type: Boolean, default: false });
const moreOpen = ref(false);

const tooltip =
    'pointer-events-none absolute left-1/2 top-full z-50 mt-1.5 -translate-x-1/2 whitespace-nowrap rounded-md bg-neutral-900 px-2.5 py-1 text-xs font-medium text-white opacity-0 shadow-sm transition-opacity duration-150 group-hover:opacity-100 dark:bg-white dark:text-neutral-900';

const emit = defineEmits([
    'open-blocks',
    'open-setup',
    'open-preview',
    'toggle-download',
    'open-inspector',
    'open-agent',
    'open-share',
]);
</script>

<template>
    <header class="flex h-14 shrink-0 items-center gap-2 border-b border-neutral-200 px-4 print:hidden dark:border-neutral-800">
        <button
            type="button"
            class="group relative inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white lg:hidden"
            aria-label="Buka blok"
            @click="emit('open-blocks')"
        >
            <Menu class="h-5 w-5" />
            <span :class="tooltip">Buka Blok</span>
        </button>

        <RouterLink
            to="/apps/u/projects"
            class="group relative inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
            aria-label="Kembali"
        >
            <ArrowLeft class="h-5 w-5" />
            <span :class="tooltip">Kembali</span>
        </RouterLink>

        <div class="min-w-0">
            <button
                type="button"
                class="group flex min-w-0 cursor-pointer items-center gap-1.5 text-left"
                title="Edit nama project"
                @click="emit('open-setup')"
            >
                <p class="truncate text-sm font-semibold group-hover:underline">{{ projectName || 'Proyek Tanpa Judul' }}</p>
                <Pencil class="h-3.5 w-3.5 shrink-0 text-neutral-400 dark:text-neutral-500" />
            </button>
            <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">ID: {{ projectId }}</p>
        </div>

        <div class="ml-auto flex items-center gap-1.5">
            <button
                type="button"
                class="group relative inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
                aria-label="Buka Agent AI"
                @click="emit('open-agent')"
            >
                <Sparkles class="h-5 w-5" />
                <span :class="tooltip">Agent AI</span>
            </button>

            <div
                class="group relative hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg text-neutral-500 dark:text-neutral-400 lg:flex"
                aria-label="Terakhir diedit"
            >
                <Clock class="h-5 w-5" />
                <span :class="tooltip">Terakhir diedit: {{ lastEditedLabel }}</span>
            </div>

            <div
                class="group relative hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg text-neutral-600 dark:text-neutral-300 sm:flex"
                aria-label="Total koin"
            >
                <Coins class="h-5 w-5 text-amber-500" />
                <span :class="tooltip">{{ totalCredits }} koin</span>
            </div>

            <RouterLink
                to="/apps/u/topup"
                class="group relative hidden h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white sm:flex"
                aria-label="Isi ulang koin"
            >
                <Plus class="h-5 w-5" />
                <span :class="tooltip">Isi Ulang Koin</span>
            </RouterLink>

            <span class="mx-1 hidden h-6 w-px bg-neutral-200 dark:bg-neutral-800 sm:block"></span>

            <button
                type="button"
                class="group relative hidden h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white md:flex"
                aria-label="Bagikan dokumen"
                @click="emit('open-share')"
            >
                <Share2 class="h-5 w-5" />
                <span :class="tooltip">Bagikan</span>
            </button>

            <button
                type="button"
                class="group relative hidden h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-emerald-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950 md:flex"
                aria-label="Publikasikan project"
                @click="emit('open-publish')"
            >
                <Rocket class="h-5 w-5" />
                <span :class="tooltip">Publish</span>
            </button>

            <button
                type="button"
                class="group relative hidden h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white md:flex"
                aria-label="Preview"
                @click="emit('open-preview')"
            >
                <Eye class="h-5 w-5" />
                <span :class="tooltip">Preview</span>
            </button>

            <button
                type="button"
                class="group relative hidden h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg transition-colors md:flex"
                :class="showGuides ? 'bg-neutral-900 text-white dark:bg-white dark:text-black' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white'"
                aria-label="Ruler"
                @click="showGuides = !showGuides"
            >
                <Ruler class="h-5 w-5" />
                <span :class="tooltip">{{ showGuides ? 'Sembunyikan Ruler' : 'Tampilkan Ruler' }}</span>
            </button>

            <button
                type="button"
                class="group relative hidden h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white md:flex"
                aria-label="Download"
                @click="emit('toggle-download')"
            >
                <Download class="h-5 w-5" />
                <span :class="tooltip">Download</span>
            </button>

            <button
                type="button"
                class="group relative hidden h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white md:flex xl:hidden"
                aria-label="Pengaturan"
                @click="emit('open-inspector')"
            >
                <Settings2 class="h-5 w-5" />
                <span :class="tooltip">Pengaturan</span>
            </button>

            <!-- Menu "lebih" untuk layar kecil -->
            <div class="relative md:hidden">
                <button
                    type="button"
                    class="group relative inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
                    aria-label="Menu lainnya"
                    @click="moreOpen = !moreOpen"
                >
                    <MoreHorizontal class="h-5 w-5" />
                    <span :class="tooltip">Lainnya</span>
                </button>

                <div v-if="moreOpen" class="fixed inset-0 z-40" @click="moreOpen = false"></div>
                <div
                    v-if="moreOpen"
                    class="absolute right-0 top-11 z-50 w-52 overflow-hidden rounded-xl border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        @click="moreOpen = false; emit('open-share')"
                    >
                        <Share2 class="h-4 w-4 shrink-0" /> Bagikan
                    </button>
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-emerald-600 transition-colors hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950"
                        @click="moreOpen = false; emit('open-publish')"
                    >
                        <Rocket class="h-4 w-4 shrink-0" /> Publish
                    </button>
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        @click="moreOpen = false; emit('open-preview')"
                    >
                        <Eye class="h-4 w-4 shrink-0" /> Preview
                    </button>
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        @click="moreOpen = false; emit('toggle-download')"
                    >
                        <Download class="h-4 w-4 shrink-0" /> Download
                    </button>
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        @click="moreOpen = false; showGuides = !showGuides"
                    >
                        <Ruler class="h-4 w-4 shrink-0" /> {{ showGuides ? 'Sembunyikan Ruler' : 'Tampilkan Ruler' }}
                    </button>
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        @click="moreOpen = false; emit('open-inspector')"
                    >
                        <Settings2 class="h-4 w-4 shrink-0" /> Pengaturan
                    </button>
                </div>
            </div>

            <ThemeToggle />
        </div>
    </header>
</template>
