<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Clock, Download, RefreshCw, TrendingUp, Users, Waves, WifiOff } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import StatCard from '../../../components/StatCard.vue';
import BarChart from '../../../components/BarChart.vue';
import { formatDate } from '../../../utils/format';
import { getJson } from '../../../utils/http';

const INTERVAL = 60; // detik

const data = ref(null);
const connected = ref(false);
const lastUpdate = ref(null);
const error = ref('');
const waveMetric = ref('views'); // 'views' | 'users'

let pollTimer = null;

const activeUsers = computed(() => data.value?.active_users || {});
const session = computed(() => data.value?.session || {});
const downloads = computed(() => data.value?.downloads || {});

const waveItems = computed(() =>
    waveMetric.value === 'users'
        ? activeUsers.value.hourly || []
        : data.value?.series?.hourly || [],
);
const waveTotal = computed(() => waveItems.value.reduce((s, p) => s + p.value, 0));
const waveLabel = computed(() => (waveMetric.value === 'users' ? 'user aktif' : 'kunjungan'));

const kpiCards = computed(() => [
    { label: 'Pengguna Aktif Hari Ini', value: activeUsers.value.today ?? 0, hint: 'Akun yang login hari ini' },
    { label: 'Rata-rata Durasi Sesi', value: session.value.average_duration_label ?? '0 dtk', hint: 'Lama user membuka aplikasi' },
    { label: 'Total Sesi (7 hari)', value: session.value.total_sessions ?? 0, hint: 'Bounce: ' + (session.value.bounce_sessions ?? 0) },
    { label: 'Download Hari Ini', value: downloads.value.today ?? 0, hint: 'Total: ' + (downloads.value.total ?? 0) },
]);

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

onMounted(connect);
onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <div class="flex items-start justify-between gap-4">
            <PageHeader
                title="Analitik"
                description="Gelombang traffic, pengguna aktif, durasi sesi, dan unduhan — ter-update otomatis."
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

        <!-- Gelombang harian -->
        <div class="mt-8 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Waves class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
                    Gelombang Harian (24 Jam)
                </h2>
                <div class="flex rounded-lg border border-neutral-200 p-0.5 dark:border-neutral-800">
                    <button
                        type="button"
                        class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="waveMetric === 'views' ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                        @click="waveMetric = 'views'"
                    >
                        Kunjungan
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="waveMetric === 'users' ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                        @click="waveMetric = 'users'"
                    >
                        User Aktif
                    </button>
                </div>
            </div>

            <p class="mt-4 text-3xl font-bold">{{ waveTotal }} <span class="text-sm font-normal text-neutral-400 dark:text-neutral-500">{{ waveLabel }}</span></p>
            <div class="mt-4">
                <BarChart :items="waveItems" color="bg-blue-500/80" hover-color="hover:bg-blue-500" label-step="4" />
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Pengguna aktif -->
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Users class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
                    Pengguna Aktif (30 Hari)
                </h2>
                <p class="mt-1 text-sm text-neutral-400 dark:text-neutral-500">Jumlah akun yang aktif per hari.</p>
                <div class="mt-4">
                    <BarChart :items="activeUsers.daily" color="bg-emerald-500/80" hover-color="hover:bg-emerald-500" label-step="5" height="140px" />
                </div>
            </div>

            <!-- Download traffic -->
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Download class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
                    Download Traffic (30 Hari)
                </h2>
                <p class="mt-1 text-sm text-neutral-400 dark:text-neutral-500">Jumlah unduhan PDF per hari.</p>
                <div class="mt-4">
                    <BarChart :items="downloads.daily" color="bg-violet-500/80" hover-color="hover:bg-violet-500" label-step="5" height="140px" />
                </div>
            </div>
        </div>

        <!-- Ringkasan durasi sesi -->
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Clock class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
                    Durasi Sesi Pengguna
                </h2>
                <p class="mt-1 text-sm text-neutral-400 dark:text-neutral-500">Rata-rata lama pengguna membuka aplikasi (7 hari terakhir).</p>
                <p class="mt-4 text-4xl font-bold">{{ session.average_duration_label || '0 dtk' }}</p>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ session.total_sessions ?? 0 }} total sesi · {{ session.bounce_sessions ?? 0 }} sesi singkat (1 kunjungan)
                </p>
            </div>

            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <TrendingUp class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
                    Aktivitas Traffic
                </h2>
                <p class="mt-1 text-sm text-neutral-400 dark:text-neutral-500">Ringkasan metrik traffic aplikasi.</p>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Pengguna aktif hari ini</span>
                        <span class="font-semibold">{{ activeUsers.today ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Total unduhan PDF</span>
                        <span class="font-semibold">{{ downloads.total ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500 dark:text-neutral-400">Unduhan hari ini</span>
                        <span class="font-semibold">{{ downloads.today ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
