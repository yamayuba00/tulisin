<script setup>
import { onMounted, ref } from 'vue';
import { Activity, Eye, MonitorSmartphone, Users } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import StatCard from '../../../components/StatCard.vue';
import DataTable from '../../../components/DataTable.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const stats = ref([]);
const traffic = ref({ page_views: 0, unique_sessions: 0, recent: [], top_pages: [] });

const recentColumns = [
    { key: 'path', label: 'Halaman' },
    { key: 'device', label: 'Perangkat' },
    { key: 'user_name', label: 'Pengguna' },
    { key: 'created_at', label: 'Waktu' },
];

const topColumns = [
    { key: 'path', label: 'Halaman' },
    { key: 'views', label: 'Views', align: 'right' },
];

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/dashboard');
        stats.value = data.stats || [];
        traffic.value = data.traffic || traffic.value;
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Dashboard Admin" description="Ringkasan analytics, traffic, dan aktivitas platform." />

        <!-- Statistik -->
        <div v-if="loading" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-lg border border-neutral-200 dark:border-neutral-800"></div>
        </div>
        <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard v-for="s in stats" :key="s.label" :label="s.label" :value="s.value" :hint="s.hint" />
        </div>

        <!-- Traffic -->
        <h2 class="mt-10 mb-4 flex items-center gap-2 text-lg font-semibold">
            <Activity class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
            Traffic & Aktivitas
        </h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex items-center gap-4 rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <Eye class="h-5 w-5" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Total Page Views</p>
                    <p class="text-2xl font-bold">{{ traffic.page_views }}</p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <MonitorSmartphone class="h-5 w-5" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Sesi Unik</p>
                    <p class="text-2xl font-bold">{{ traffic.unique_sessions }}</p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div>
                <h3 class="mb-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">Halaman Terpopuler</h3>
                <DataTable :columns="topColumns" :rows="traffic.top_pages" :loading="loading" empty-text="Belum ada kunjungan." key-field="path" />
            </div>
            <div>
                <h3 class="mb-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">Kunjungan Terbaru</h3>
                <DataTable :columns="recentColumns" :rows="traffic.recent" :loading="loading" empty-text="Belum ada aktivitas.">
                    <template #cell-device="{ value }">
                        <span class="inline-flex items-center gap-1 text-xs text-neutral-500 dark:text-neutral-400">
                            <Users class="h-3.5 w-3.5" /> {{ value || '-' }}
                        </span>
                    </template>
                    <template #cell-created_at="{ value }">
                        <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                    </template>
                </DataTable>
            </div>
        </div>
    </div>
</template>
