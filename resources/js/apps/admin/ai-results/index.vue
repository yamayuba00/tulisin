<script setup>
import { onMounted, ref } from 'vue';
import { ScanSearch } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const results = ref([]);

const columns = [
    { key: 'type', label: 'Jenis' },
    { key: 'project_title', label: 'Project' },
    { key: 'user_name', label: 'Pengguna' },
    { key: 'score', label: 'Skor', align: 'right' },
    { key: 'matches_count', label: 'Temuan', align: 'right' },
    { key: 'created_at', label: 'Waktu' },
];

function typeMeta(type) {
    return type === 'turnitin'
        ? { label: 'Turnitin', tone: 'warning' }
        : { label: 'Plagiarism', tone: 'info' };
}

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/ai-results');
        total.value = data.total || 0;
        results.value = data.results || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Hasil AI" :description="`${total} scan AI tercatat`" />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="results" :loading="loading" empty-text="Belum ada hasil scan AI.">
                <template #cell-type="{ value }">
                    <StatusBadge :label="typeMeta(value).label" :tone="typeMeta(value).tone" />
                </template>
                <template #cell-project_title="{ value }">
                    <span class="inline-flex items-center gap-2 font-medium">
                        <ScanSearch class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        {{ value || '-' }}
                    </span>
                </template>
                <template #cell-score="{ value }">
                    <span class="text-sm font-medium">{{ value }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
