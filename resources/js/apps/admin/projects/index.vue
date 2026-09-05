<script setup>
import { computed, onMounted, ref } from 'vue';
import { FolderKanban } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import SearchInput from '../../../components/SearchInput.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const projects = ref([]);
const query = ref('');

const columns = [
    { key: 'title', label: 'Project' },
    { key: 'user_name', label: 'Pemilik' },
    { key: 'category', label: 'Kategori' },
    { key: 'format', label: 'Format' },
    { key: 'status', label: 'Status' },
    { key: 'version', label: 'Versi', align: 'right' },
    { key: 'revisions_count', label: 'Riwayat', align: 'right' },
    { key: 'ai_results_count', label: 'Scan AI', align: 'right' },
    { key: 'created_at', label: 'Dibuat' },
];

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return projects.value;
    return projects.value.filter(
        (p) =>
            (p.title || '').toLowerCase().includes(q) ||
            (p.user_name || '').toLowerCase().includes(q) ||
            (p.user_email || '').toLowerCase().includes(q),
    );
});

function statusTone(status) {
    switch (status) {
        case 'published':
            return 'success';
        case 'draft':
            return 'info';
        default:
            return 'neutral';
    }
}

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/projects');
        total.value = data.total || 0;
        projects.value = data.projects || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Projects" :description="`${total} dokumen terdaftar`" />

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="sm:w-72">
                <SearchInput v-model="query" placeholder="Cari judul atau pemilik..." />
            </div>
        </div>

        <div class="mt-4">
            <DataTable :columns="columns" :rows="filtered" :loading="loading" empty-text="Belum ada project.">
                <template #cell-title="{ value }">
                    <span class="inline-flex items-center gap-2 font-medium">
                        <FolderKanban class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        {{ value }}
                    </span>
                </template>
                <template #cell-user_name="{ row }">
                    <div>
                        <p class="text-sm">{{ row.user_name || '-' }}</p>
                        <p class="text-xs text-neutral-400 dark:text-neutral-500">{{ row.user_email || '' }}</p>
                    </div>
                </template>
                <template #cell-format="{ value }">
                    <span class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ value }}</span>
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :label="value" :tone="statusTone(value)" />
                </template>
                <template #cell-version="{ value }">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">v{{ value }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value) }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
