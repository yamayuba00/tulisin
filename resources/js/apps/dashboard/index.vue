<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import {
    LayoutTemplate,
    Sparkles,
    Download,
    ShieldCheck,
    FolderOpen,
    Clock,
    Plus,
    ArrowRight,
    ArrowUpRight,
    ArrowDownRight,
} from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import StatCard from '../../components/StatCard.vue';
import AppButton from '../../components/AppButton.vue';
import { getJson } from '../../utils/http';
import { useAuth } from '../../utils/auth';
import { formatDate } from '../../utils/format';

const router = useRouter();
const { currentUser } = useAuth();

const balance = ref(null);
const transactions = ref([]);
const projects = ref([]);

const ACTIVE_WINDOW = 7 * 24 * 60 * 60 * 1000; // 7 hari

const firstName = computed(() => {
    const name = currentUser.value?.name || '';
    return name.split(' ')[0] || '';
});

const activeCount = computed(() => {
    const now = Date.now();
    return projects.value.filter((p) => now - (Number(p.lastEdited) || 0) <= ACTIVE_WINDOW).length;
});

const recentProjects = computed(() => projects.value.slice(0, 4));
const recentTransactions = computed(() => transactions.value.slice(0, 5));

function reasonLabel(reason) {
    const map = {
        topup: 'Top-up Koin',
        template_use: 'Pakai Template',
        image_upload: 'Upload Gambar',
        font_upload: 'Upload Font',
        affiliate_referral: 'Bonus Referral',
        generate: 'Generate AI',
        plagiarism: 'Cek Plagiarisme',
    };
    return map[reason] || reason || '-';
}

async function loadData() {
    try {
        const data = await getJson('/api/projects');
        projects.value = Array.isArray(data?.projects) ? data.projects : [];
    } catch {
        projects.value = [];
    }
    try {
        const data = await getJson('/api/wallet');
        balance.value = data.balance ?? 0;
        transactions.value = data.transactions || [];
    } catch {
        balance.value = 0;
    }
}

function openProject(id) {
    router.push({ path: '/apps/u/project', query: { builder: id } });
}

onMounted(loadData);

const features = [
    {
        icon: LayoutTemplate,
        title: 'Precision Formatting Engine',
        desc: 'Atur format sesuai ketentuan kampus secara presisi: font, spasi baris, ukuran halaman, margin, dan penomoran otomatis.',
    },
    {
        icon: Sparkles,
        title: 'AI Academic Co-Pilot',
        desc: 'Asisten AI yang menemani menyusun setiap bab, dari ide hingga paragraf yang rapi.',
        soon: true,
    },
    {
        icon: Download,
        title: 'Export Perfection',
        desc: 'Ekspor dokumen ke PDF dan Word dengan tata letak yang konsisten dan siap cetak.',
    },
    {
        icon: ShieldCheck,
        title: 'Institutional Ready',
        desc: 'Sistem yang transparan dengan audit log dan standar etika penulisan.',
    },
];
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader
            :title="firstName ? `Halo, ${firstName}` : 'Dashboard'"
            description="Ringkasan aktivitas penulisan kamu."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink to="/apps/u/topup" class="block">
                <StatCard
                    label="Saldo Koin"
                    :value="balance === null ? '—' : balance"
                    hint="Top-up untuk menambah"
                />
            </RouterLink>
            <RouterLink to="/apps/u/projects" class="block">
                <StatCard label="Total Project" :value="projects.length" hint="Project dokumen kamu" />
            </RouterLink>
            <RouterLink to="/apps/u/projects" class="block">
                <StatCard label="Project Aktif" :value="activeCount" hint="Diedit 7 hari terakhir" />
            </RouterLink>
        </div>

        <div class="mt-6 flex flex-col gap-4 rounded-lg border border-neutral-200 p-6 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold">Mulai Menulis</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Buat project baru atau mulai dari template siap pakai.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <AppButton to="/apps/u/templates" variant="outline">
                    <LayoutTemplate class="h-4 w-4" />
                    Gunakan Template
                </AppButton>
                <AppButton to="/apps/u/projects">
                    <Plus class="h-4 w-4" />
                    Buat Project
                </AppButton>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Project terbaru -->
            <div class="rounded-lg border border-neutral-200 dark:border-neutral-800">
                <div class="flex items-center justify-between border-b border-neutral-100 p-4 dark:border-neutral-800">
                    <h2 class="text-sm font-semibold">Project Terbaru</h2>
                    <RouterLink
                        v-if="projects.length"
                        to="/apps/u/projects"
                        class="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    >
                        Lihat semua
                        <ArrowRight class="h-3.5 w-3.5" />
                    </RouterLink>
                </div>

                <div v-if="recentProjects.length" class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    <button
                        v-for="p in recentProjects"
                        :key="p.id"
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900"
                        @click="openProject(p.id)"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                            <FolderOpen class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ p.name }}</p>
                            <p class="mt-0.5 inline-flex items-center gap-1 text-xs text-neutral-400 dark:text-neutral-500">
                                <Clock class="h-3 w-3" />
                                {{ formatDate(p.lastEdited, { withTime: true }) }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full border border-neutral-200 px-2 py-0.5 text-[11px] text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">{{ p.category }}</span>
                    </button>
                </div>

                <div v-else class="p-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                    Belum ada project.
                    <RouterLink to="/apps/u/projects" class="font-medium underline">Buat sekarang</RouterLink>
                </div>
            </div>

            <!-- Aktivitas kredit -->
            <div class="rounded-lg border border-neutral-200 dark:border-neutral-800">
                <div class="flex items-center justify-between border-b border-neutral-100 p-4 dark:border-neutral-800">
                    <h2 class="text-sm font-semibold">Aktivitas Koin</h2>
                    <RouterLink
                        to="/apps/u/topup"
                        class="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    >
                        Top-up
                        <ArrowRight class="h-3.5 w-3.5" />
                    </RouterLink>
                </div>

                <div v-if="recentTransactions.length" class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    <div v-for="t in recentTransactions" :key="t.id" class="flex items-center gap-3 px-4 py-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                            :class="t.type === 'credit'
                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400'
                                : 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400'"
                        >
                            <ArrowUpRight v-if="t.type === 'credit'" class="h-4 w-4" />
                            <ArrowDownRight v-else class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ reasonLabel(t.reason) }}</p>
                            <p class="mt-0.5 text-xs text-neutral-400 dark:text-neutral-500">{{ formatDate(t.created_at, { withTime: true }) }}</p>
                        </div>
                        <span
                            class="shrink-0 text-sm font-semibold"
                            :class="t.type === 'credit' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ t.type === 'credit' ? '+' : '-' }}{{ t.amount }}
                        </span>
                    </div>
                </div>

                <div v-else class="p-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                    Belum ada transaksi koin.
                </div>
            </div>
        </div>

        <div class="mt-6">
            <h2 class="text-sm font-semibold">Fitur Unggulan</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="f in features" :key="f.title" class="flex flex-col rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <component :is="f.icon" class="h-5 w-5 shrink-0 text-neutral-500 dark:text-neutral-400" />
                        <h3 class="font-semibold">{{ f.title }}</h3>
                    </div>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{{ f.desc }}</p>
                    <span
                        v-if="f.soon"
                        class="mt-3 inline-flex w-fit items-center rounded-full border border-neutral-300 px-2 py-0.5 text-[11px] font-medium text-neutral-500 dark:border-neutral-700 dark:text-neutral-400"
                    >
                        Segera hadir
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
