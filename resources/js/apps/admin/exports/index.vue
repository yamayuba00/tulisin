<script setup>
import { onMounted, ref } from 'vue';
import { FileDown } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const exports = ref([]);

const columns = [
    { key: 'user_name', label: 'Pengguna' },
    { key: 'project', label: 'Project' },
    { key: 'format', label: 'Format' },
    { key: 'html_length', label: 'Ukuran (chars)', align: 'right' },
    { key: 'created_at', label: 'Waktu' },
];

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/exports');
        total.value = data.total || 0;
        exports.value = data.exports || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Export PDF" :description="`${total} riwayat ekspor PDF`" />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="exports" :loading="loading" empty-text="Belum ada ekspor PDF.">
                <template #cell-user_name="{ value }">
                    <span class="inline-flex items-center gap-2 text-sm">
                        <FileDown class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        {{ value || 'System' }}
                    </span>
                </template>
                <template #cell-project="{ value }">
                    <span class="text-sm">{{ value || '-' }}</span>
                </template>
                <template #cell-format="{ value }">
                    <span class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ value || 'pdf' }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
