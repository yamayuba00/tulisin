<script setup>
import { onMounted, ref } from 'vue';
import { Check, X, Star } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const reviews = ref([]);
const processing = ref(null);

const columns = [
    { key: 'user_name', label: 'Pengguna' },
    { key: 'rating', label: 'Rating' },
    { key: 'text', label: 'Ulasan' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Dikirim' },
];

function statusMeta(status) {
    switch (status) {
        case 'published':
            return { label: 'Dipublikasikan', tone: 'success' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'danger' };
        default:
            return { label: 'Menunggu', tone: 'warning' };
    }
}

async function load() {
    loading.value = true;
    try {
        const data = await getJson('/api/admin/reviews?per_page=100');
        total.value = data.total || 0;
        reviews.value = data.reviews || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

async function moderate(review, status) {
    processing.value = review.id;
    try {
        const res = await request(`/api/admin/reviews/${review.id}/moderate`, {
            method: 'POST',
            body: JSON.stringify({ status }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Review diproses.', 'success');
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal memproses review.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        processing.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Review Pengguna" :description="`${total} review untuk dimoderasi`" />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="reviews" :loading="loading" empty-text="Belum ada review pengguna.">
                <template #cell-rating="{ value }">
                    <div class="flex gap-0.5 text-amber-400">
                        <Star v-for="i in 5" :key="i" class="h-4 w-4" :class="i <= value ? 'fill-current' : 'fill-transparent text-neutral-300 dark:text-neutral-600'" />
                    </div>
                </template>
                <template #cell-text="{ value }">
                    <p class="max-w-md text-sm text-neutral-700 dark:text-neutral-200">{{ value }}</p>
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :label="statusMeta(value).label" :tone="statusMeta(value).tone" />
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>

                <template #actions="{ row }">
                    <button
                        type="button"
                        :disabled="processing === row.id || row.status === 'published'"
                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                        @click="moderate(row, 'published')"
                    >
                        <Check class="h-3.5 w-3.5" /> Publish
                    </button>
                    <button
                        type="button"
                        :disabled="processing === row.id || row.status === 'rejected'"
                        class="inline-flex items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/40"
                        @click="moderate(row, 'rejected')"
                    >
                        <X class="h-3.5 w-3.5" /> Tolak
                    </button>
                </template>
            </DataTable>
        </div>
    </div>
</template>
