<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    Activity,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    FileText,
    Monitor,
    RefreshCw,
    Smartphone,
    Tablet,
    Wallet,
    WifiOff,
} from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import StatCard from '../../../components/StatCard.vue';
import { formatDate } from '../../../utils/format';
import { getJson } from '../../../utils/http';

const INTERVAL = 60; // detik antar polling

const data = ref(null);
const connected = ref(false);
const lastUpdate = ref(null);
const error = ref('');

let pollTimer = null;

const overview = computed(() => data.value?.overview || {});
const devices = computed(() => data.value?.devices || []);
const devicesTotal = computed(() => Math.max(1, devices.value.reduce((s, d) => s + d.count, 0)));

// Aktivitas pengguna (server-side paginated + filter).
const activityData = ref([]);
const activityMeta = ref({ current_page: 1, last_page: 1, per_page: 5, total: 0 });
const activityType = ref('');
const activityUser = ref('');
const activityLoading = ref(false);

const activityTabs = [
    { id: '', label: 'Semua' },
    { id: 'view', label: 'Kunjungan' },
    { id: 'export', label: 'Export' },
    { id: 'project', label: 'Project' },
    { id: 'payment', label: 'Pembayaran' },
];

// Halaman terpopuler (server-side paginated).
const topPagesData = ref([]);
const topPagesMeta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
const topLoading = ref(false);

const kpiCards = computed(() => [
    { label: 'Page Views Hari Ini', value: overview.value.page_views_today ?? 0, hint: 'Total kunjungan hari ini' },
    { label: 'Pengunjung Unik Hari Ini', value: overview.value.unique_visitors_today ?? 0, hint: 'Sesi unik hari ini' },
    { label: 'User Aktif Hari Ini', value: overview.value.active_users_today ?? 0, hint: 'Akun yang login' },
    { label: 'User Baru Hari Ini', value: overview.value.new_users_today ?? 0, hint: 'Registrasi baru' },
    { label: 'Total Pengguna', value: overview.value.total_users ?? 0, hint: 'Akun terdaftar' },
    { label: 'Total Project', value: overview.value.projects_total ?? 0, hint: 'Semua dokumen' },
    { label: 'Total Export PDF', value: overview.value.exports_total ?? 0, hint: 'Unduhan PDF' },
    { label: 'Pendapatan (Paid)', value: formatRupiah(overview.value.revenue_paid ?? 0), hint: 'Total pembayaran sukses' },
]);

const typeMeta = {
    view: { icon: Eye, color: 'text-blue-500' },
    export: { icon: Download, color: 'text-emerald-500' },
    project: { icon: FileText, color: 'text-violet-500' },
    payment: { icon: Wallet, color: 'text-amber-500' },
};

const activityFrom = computed(() =>
    activityMeta.value.total === 0 ? 0 : (activityMeta.value.current_page - 1) * activityMeta.value.per_page + 1,
);
const activityTo = computed(() => Math.min(activityMeta.value.current_page * activityMeta.value.per_page, activityMeta.value.total));

const topFrom = computed(() =>
    topPagesMeta.value.total === 0 ? 0 : (topPagesMeta.value.current_page - 1) * topPagesMeta.value.per_page + 1,
);
const topTo = computed(() => Math.min(topPagesMeta.value.current_page * topPagesMeta.value.per_page, topPagesMeta.value.total));

function formatRupiah(v) {
    return 'Rp ' + Number(v || 0).toLocaleString('id-ID');
}

function deviceIcon(device) {
    if (device === 'mobile') return Smartphone;
    if (device === 'tablet') return Tablet;
    return Monitor;
}

async function loadAnalytics() {
    try {
        data.value = await getJson('/api/admin/analytics');
        lastUpdate.value = new Date();
        connected.value = true;
        error.value = '';
    } catch (e) {
        connected.value = false;
        error.value = e.message || 'Gagal memuat data.';
    }
}

function connect() {
    if (pollTimer) clearInterval(pollTimer);
    error.value = '';
    loadAnalytics();
    pollTimer = setInterval(loadAnalytics, INTERVAL * 1000);
}

async function loadActivity() {
    activityLoading.value = true;
    try {
        const params = new URLSearchParams({
            per_page: '5',
            page: String(activityMeta.value.current_page || 1),
        });
        if (activityType.value) params.set('type', activityType.value);
        if (activityUser.value.trim()) params.set('user', activityUser.value.trim());

        const res = await getJson(`/api/admin/analytics/activity?${params.toString()}`);
        activityData.value = res.data || [];
        activityMeta.value = res.pagination || { current_page: 1, last_page: 1, per_page: 5, total: 0 };
    } catch {
        activityData.value = [];
    } finally {
        activityLoading.value = false;
    }
}

async function loadTopPages() {
    topLoading.value = true;
    try {
        const params = new URLSearchParams({
            per_page: '10',
            page: String(topPagesMeta.value.current_page || 1),
        });
        const res = await getJson(`/api/admin/analytics/top-pages?${params.toString()}`);
        topPagesData.value = res.data || [];
        topPagesMeta.value = res.pagination || { current_page: 1, last_page: 1, per_page: 10, total: 0 };
    } catch {
        topPagesData.value = [];
    } finally {
        topLoading.value = false;
    }
}

function changeActivityType(type) {
    activityType.value = type;
    activityMeta.value.current_page = 1;
    loadActivity();
}

function searchActivity() {
    activityMeta.value.current_page = 1;
    loadActivity();
}

function setActivityPage(page) {
    if (page < 1 || page > activityMeta.value.last_page) return;
    activityMeta.value.current_page = page;
    loadActivity();
}

function setTopPage(page) {
    if (page < 1 || page > topPagesMeta.value.last_page) return;
    topPagesMeta.value.current_page = page;
    loadTopPages();
}

onMounted(() => {
    connect();
    loadActivity();
    loadTopPages();
});

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <div class="flex items-start justify-between gap-4">
            <PageHeader
                title="Dashboard Admin"
                description="Traffic, penggunaan fitur, dan aktivitas pengguna — ter-update otomatis."
            />
            <div class="flex shrink-0 items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="connected ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'"
                >
                    <span class="h-2 w-2 rounded-full" :class="connected ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    {{ connected ? 'Live' : 'Menghubungkan…' }}
                </span>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="connect"
                >
                    <RefreshCw class="h-4 w-4" />
                    Refresh
                </button>
            </div>
        </div>

        <p v-if="error" class="mt-3 inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
            <WifiOff class="h-3.5 w-3.5" /> {{ error }}
        </p>
        <p v-else-if="lastUpdate" class="mt-3 text-xs text-neutral-400 dark:text-neutral-500">
            Terakhir diperbarui {{ formatDate(lastUpdate, { withTime: true }) }} · refresh otomatis tiap {{ INTERVAL }} detik.
        </p>

        <!-- KPI -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard v-for="k in kpiCards" :key="k.label" :label="k.label" :value="k.value" :hint="k.hint" />
        </div>

        <!-- Halaman populer & perangkat -->
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div>
                <h3 class="mb-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">Halaman Terpopuler</h3>
                <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900/50">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Halaman</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="topLoading">
                                <td colspan="2" class="px-4 py-10 text-center text-neutral-400 dark:text-neutral-500">Memuat…</td>
                            </tr>
                            <tr v-else-if="topPagesData.length === 0">
                                <td colspan="2" class="px-4 py-10 text-center text-neutral-400 dark:text-neutral-500">Belum ada kunjungan.</td>
                            </tr>
                            <tr v-for="row in topPagesData" :key="row.path" class="border-b border-neutral-100 last:border-0 dark:border-neutral-900">
                                <td class="max-w-[220px] truncate px-4 py-2.5">{{ row.path }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold">{{ row.views }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex items-center justify-between border-t border-neutral-200 px-4 py-2.5 dark:border-neutral-800">
                        <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ topFrom }}–{{ topTo }} dari {{ topPagesMeta.total }}</span>
                        <div class="flex gap-1">
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
                                :disabled="topPagesMeta.current_page <= 1"
                                @click="setTopPage(topPagesMeta.current_page - 1)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
                                :disabled="topPagesMeta.current_page >= topPagesMeta.last_page"
                                @click="setTopPage(topPagesMeta.current_page + 1)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">Perangkat</h3>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div v-if="devices.length" class="space-y-4">
                        <div v-for="d in devices" :key="d.device" class="flex items-center gap-3">
                            <component :is="deviceIcon(d.device)" class="h-5 w-5 shrink-0 text-neutral-400 dark:text-neutral-500" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-neutral-600 dark:text-neutral-300">{{ d.label }}</span>
                                    <span class="font-semibold">{{ d.count }}</span>
                                </div>
                                <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                    <div class="h-full rounded-full bg-neutral-400 dark:bg-neutral-600" :style="{ width: `${(d.count / devicesTotal) * 100}%` }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-neutral-400 dark:text-neutral-500">Belum ada data perangkat.</p>
                </div>
            </div>
        </div>

        <!-- Aktivitas pengguna -->
        <h2 class="mt-10 mb-4 flex items-center gap-2 text-lg font-semibold">
            <Activity class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
            Aktivitas Pengguna Terbaru
        </h2>

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800">
            <!-- Filter: tab jenis + cari user -->
            <div class="flex flex-wrap items-center gap-3 border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                <div class="flex rounded-lg border border-neutral-200 p-0.5 dark:border-neutral-800">
                    <button
                        v-for="tab in activityTabs"
                        :key="tab.id"
                        type="button"
                        class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="activityType === tab.id ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                        @click="changeActivityType(tab.id)"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <input
                        v-model="activityUser"
                        type="text"
                        placeholder="Cari nama user…"
                        class="h-9 w-44 rounded-lg border border-neutral-300 bg-transparent px-3 text-sm outline-none focus:border-neutral-500 dark:border-neutral-700"
                        @keyup.enter="searchActivity"
                    />
                    <button
                        type="button"
                        class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-neutral-300 px-3 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="searchActivity"
                    >
                        Cari
                    </button>
                </div>
            </div>

            <div v-if="activityLoading" class="px-4 py-10 text-center text-sm text-neutral-400 dark:text-neutral-500">Memuat…</div>
            <div v-else-if="activityData.length" class="divide-y divide-neutral-100 dark:divide-neutral-800">
                <div v-for="(item, i) in activityData" :key="i" class="flex items-start gap-3 px-4 py-3">
                    <component :is="typeMeta[item.type]?.icon || Activity" class="mt-0.5 h-4 w-4 shrink-0" :class="typeMeta[item.type]?.color || 'text-neutral-400'" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-neutral-700 dark:text-neutral-200">
                            <span class="font-semibold">{{ item.user }}</span>
                            <span class="text-neutral-500 dark:text-neutral-400"> {{ item.action }}</span>
                            <span v-if="item.detail" class="text-neutral-500 dark:text-neutral-400"> · {{ item.detail }}</span>
                        </p>
                    </div>
                    <span class="shrink-0 whitespace-nowrap text-xs text-neutral-400 dark:text-neutral-500">{{ formatDate(item.at, { withTime: true }) }}</span>
                </div>
            </div>
            <div v-else class="px-4 py-10 text-center text-sm text-neutral-400 dark:text-neutral-500">Belum ada aktivitas tercatat.</div>

            <div class="flex items-center justify-between border-t border-neutral-200 px-4 py-2.5 dark:border-neutral-800">
                <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ activityFrom }}–{{ activityTo }} dari {{ activityMeta.total }}</span>
                <div class="flex gap-1">
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
                        :disabled="activityMeta.current_page <= 1"
                        @click="setActivityPage(activityMeta.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
                        :disabled="activityMeta.current_page >= activityMeta.last_page"
                        @click="setActivityPage(activityMeta.current_page + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
