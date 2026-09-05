<script setup>
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
} from 'lucide-vue-next';
import ThemeToggle from '../../../components/ThemeToggle.vue';

defineProps({
    projectName: { type: String, default: '' },
    projectId: { type: String, default: '' },
    lastEditedLabel: { type: String, default: '' },
    totalCredits: { type: Number, default: 0 },
});

const showGuides = defineModel('showGuides', { type: Boolean, default: false });

const emit = defineEmits([
    'open-blocks',
    'open-setup',
    'open-preview',
    'print',
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
            class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white lg:hidden"
            aria-label="Buka blok"
            @click="emit('open-blocks')"
        >
            <Menu class="h-5 w-5" />
        </button>

        <RouterLink
            to="/apps/u/projects"
            class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
            aria-label="Kembali"
        >
            <ArrowLeft class="h-5 w-5" />
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
                class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
                title="Buka Agent AI untuk membantu di canvas"
                @click="emit('open-agent')"
            >
                <Sparkles class="h-4 w-4" />
                <span class="hidden sm:inline">Agent AI</span>
            </button>

            <div
                class="hidden items-center gap-1.5 px-2.5 py-1.5 text-xs text-neutral-500 dark:text-neutral-400 lg:flex"
                title="Terakhir diedit"
            >
                <Clock class="h-3.5 w-3.5" />
                <span>{{ lastEditedLabel }}</span>
            </div>

            <div
                class="hidden items-center gap-1.5 rounded-full border border-neutral-200 px-2.5 py-1 text-xs font-medium text-neutral-600 dark:border-neutral-800 dark:text-neutral-300 sm:flex"
                title="Total koin"
            >
                <Coins class="h-3.5 w-3.5 text-amber-500" />
                <span>{{ totalCredits }} koin</span>
            </div>

            <RouterLink
                to="/apps/u/topup"
                class="hidden items-center gap-1 rounded-full border border-neutral-200 px-2.5 py-1 text-xs font-medium text-neutral-600 transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800 sm:inline-flex"
                title="Isi ulang koin"
            >
                <Plus class="h-3.5 w-3.5" />
                Topup
            </RouterLink>

            <span class="mx-1 hidden h-6 w-px bg-neutral-200 dark:bg-neutral-800 sm:block"></span>

            <button
                type="button"
                class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
                title="Bagikan dokumen sebagai link publik"
                @click="emit('open-share')"
            >
                <Share2 class="h-4 w-4" />
                <span class="hidden sm:inline">Bagikan</span>
            </button>

            <button
                type="button"
                class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white"
                @click="emit('open-preview')"
            >
                <Eye class="h-4 w-4" />
                <span class="hidden sm:inline">Preview</span>
            </button>

            <button
                type="button"
                class="hidden h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white sm:inline-flex"
                @click="emit('print')"
            >
                <Printer class="h-4 w-4" />
                <span class="hidden sm:inline">Print</span>
            </button>

            <button
                type="button"
                class="hidden h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition-colors md:inline-flex"
                :class="showGuides ? 'bg-neutral-900 text-white dark:bg-white dark:text-black' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white'"
                @click="showGuides = !showGuides"
            >
                <Ruler class="h-4 w-4" />
                <span class="hidden md:inline">Ruler</span>
            </button>

            <button
                type="button"
                class="hidden h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white sm:inline-flex"
                @click="emit('toggle-download')"
            >
                <Download class="h-4 w-4" />
                <span class="hidden sm:inline">Download</span>
            </button>

            <button
                type="button"
                class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white xl:hidden"
                aria-label="Buka pengaturan"
                @click="emit('open-inspector')"
            >
                <Settings2 class="h-5 w-5" />
            </button>
            <ThemeToggle />
        </div>
    </header>
</template>
