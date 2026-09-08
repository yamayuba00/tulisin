<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    Activity,
    AlertTriangle,
    Cpu,
    HardDrive,
    ListChecks,
    RefreshCw,
    Server,
} from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import StatCard from '../../../components/StatCard.vue';
import DataTable from '../../../components/DataTable.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const data = ref(null);

const failedColumns = [
    { key: 'queue', label: 'Antrian' },
    { key: 'error', label: 'Error' },
    { key: 'failed_at', label: 'Waktu', align: 'right' },
];

const fpmCards = computed(() => {
    const fpm = data.value?.fpm;
    if (!fpm) return [];
    const labels = {
        'active-processes': 'Proses Aktif',
        'idle-processes': 'Proses Idle',
        'total-processes': 'Total Proses',
        'max-children-reached': 'Max Children Tercapai',
        'listen-queue': 'Listen Queue',
        'max-listen-queue': 'Max Listen Queue',
        'slow-requests': 'Slow Requests',
        'accepted-conn': 'Koneksi Diterima',
    };
    return Object.entries(labels)
        .filter(([key]) => fpm[key] != null)
        .map(([key, label]) => ({ label, value: fpm[key] }));
});

function formatBytes(bytes) {
    if (bytes == null) return '-';
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
}

function loadAvgText(load) {
    if (!Array.isArray(load)) return '-';
    return load.map((n) => Number(n).toFixed(2)).join(' / ');
}

async function load() {
    loading.value = true;
    try {
        data.value = await getJson('/api/admin/monitoring');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6 lg:p-8">
        <div class="flex items-start justify-between gap-4">
            <PageHeader
                title="Monitoring Server"
                description="Backlog antrian, status PHP-FPM, dan kondisi sistem secara ringan."
            />
            <button
                type="button"
                class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                :disabled="loading"
                @click="load"
            >
                <RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />
                Refresh
            </button>
        </div>

        <!-- Antrian (queue) -->
        <h2 class="mt-8 mb-4 flex items-center gap-2 text-lg font-semibold">
            <ListChecks class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
            Antrian (Queue)
        </h2>

        <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-lg border border-neutral-200 dark:border-neutral-800"></div>
        </div>
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard label="Antrian Default (notifikasi)" :value="data.queue.pending.default" hint="Job menunggu diproses" />
            <StatCard label="Antrian Exports (render PDF)" :value="data.queue.pending.exports" hint="Job PDF menunggu diproses" />
            <StatCard label="Job Gagal (failed_jobs)" :value="data.queue.failed" hint="Total job yang gagal" />
        </div>

        <!-- PHP-FPM -->
        <h2 class="mt-8 mb-4 flex items-center gap-2 text-lg font-semibold">
            <Server class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
            PHP-FPM
        </h2>

        <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-lg border border-neutral-200 dark:border-neutral-800"></div>
        </div>
        <div v-else-if="fpmCards.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard v-for="f in fpmCards" :key="f.label" :label="f.label" :value="f.value" />
        </div>
        <div v-else class="rounded-lg border border-dashed border-neutral-300 p-5 text-sm text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
            Status PHP-FPM tidak tersedia. Hanya aktif saat berjalan di bawah PHP-FPM (PHP 8.1+).
        </div>

        <!-- Sistem -->
        <h2 class="mt-8 mb-4 flex items-center gap-2 text-lg font-semibold">
            <Activity class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
            Sistem
        </h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex items-center gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                <Cpu class="h-5 w-5 shrink-0 text-neutral-400 dark:text-neutral-500" />
                <div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">PHP / SAPI</p>
                    <p class="font-semibold">{{ data?.php?.version || '-' }} · {{ data?.php?.sapi || '-' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                <HardDrive class="h-5 w-5 shrink-0 text-neutral-400 dark:text-neutral-500" />
                <div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Disk Storage</p>
                    <p class="font-semibold">{{ formatBytes(data?.system?.disk_free) }} bebas</p>
                    <p class="text-xs text-neutral-400 dark:text-neutral-500">dari {{ formatBytes(data?.system?.disk_total) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                <Activity class="h-5 w-5 shrink-0 text-neutral-400 dark:text-neutral-500" />
                <div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Load Average</p>
                    <p class="font-semibold">{{ loadAvgText(data?.system?.load_avg) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                <Cpu class="h-5 w-5 shrink-0 text-neutral-400 dark:text-neutral-500" />
                <div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Memory Limit</p>
                    <p class="font-semibold">{{ data?.php?.memory_limit || '-' }}</p>
                    <p class="text-xs text-neutral-400 dark:text-neutral-500">upload {{ data?.php?.upload_max_filesize || '-' }}</p>
                </div>
            </div>
        </div>

        <!-- Job gagal terbaru -->
        <h2 class="mt-8 mb-4 flex items-center gap-2 text-lg font-semibold">
            <AlertTriangle class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
            Job Gagal Terbaru
        </h2>

        <DataTable
            :columns="failedColumns"
            :rows="data?.queue?.recent_failed || []"
            :loading="loading"
            empty-text="Tidak ada job yang gagal."
            key-field="uuid"
        >
            <template #cell-queue="{ value }">
                <span class="inline-flex items-center gap-1 rounded bg-neutral-100 px-1.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                    {{ value }}
                </span>
            </template>
            <template #cell-error="{ value }">
                <span class="block max-w-md truncate text-xs text-neutral-500 dark:text-neutral-400" :title="value">{{ value }}</span>
            </template>
            <template #cell-failed_at="{ value }">
                <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
            </template>
        </DataTable>
    </div>
</template>
