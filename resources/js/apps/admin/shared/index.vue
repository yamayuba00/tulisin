<script setup>
import { onMounted, ref } from 'vue';
import { Share2 } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const documents = ref([]);

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'user_name', label: 'Pemilik' },
    { key: 'state', label: 'State' },
    { key: 'time_view', label: 'Durasi (menit)', align: 'right' },
    { key: 'expires_at', label: 'Kedaluwarsa' },
    { key: 'created_at', label: 'Dibuat' },
];

function stateMeta(state) {
    switch (state) {
        case 'active':
            return { label: 'Aktif', tone: 'success' };
        case 'expired':
            return { label: 'Kedaluwarsa', tone: 'neutral' };
        default:
            return { label: state || 'view', tone: 'info' };
    }
}

function shareUrl(uuid) {
    return `${window.location.origin}/share?shared=${uuid}`;
}

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/shared-documents');
        total.value = data.total || 0;
        documents.value = data.documents || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Dokumen Dibagikan" :description="`${total} link berbagi`" />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="documents" :loading="loading" empty-text="Belum ada dokumen dibagikan.">
                <template #cell-name="{ row }">
                    <a :href="shareUrl(row.uuid)" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 font-medium underline-offset-2 hover:underline">
                        <Share2 class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        {{ row.name }}
                    </a>
                </template>
                <template #cell-state="{ value }">
                    <StatusBadge :label="stateMeta(value).label" :tone="stateMeta(value).tone" />
                </template>
                <template #cell-time_view="{ value }">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ value || '-' }}</span>
                </template>
                <template #cell-expires_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
