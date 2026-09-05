<script setup>
import { onMounted, ref } from 'vue';
import { ScrollText } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const logs = ref([]);

const columns = [
    { key: 'user_name', label: 'Pengguna' },
    { key: 'action', label: 'Aksi' },
    { key: 'model_type', label: 'Modul' },
    { key: 'created_at', label: 'Waktu' },
];

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/audit-logs');
        logs.value = data.logs || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Audit Log" description="Jejak aktivitas pengguna di platform." />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="logs" :loading="loading" empty-text="Belum ada aktivitas tercatat.">
                <template #cell-user_name="{ value }">
                    <span class="text-sm text-neutral-700 dark:text-neutral-200">{{ value || 'System' }}</span>
                </template>
                <template #cell-action="{ value }">
                    <span class="inline-flex items-center gap-2 font-medium">
                        <ScrollText class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        {{ value }}
                    </span>
                </template>
                <template #cell-model_type="{ value }">
                    <StatusBadge v-if="value" :label="value" tone="neutral" />
                    <span v-else class="text-xs text-neutral-400 dark:text-neutral-500">-</span>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
