<script setup>
import { onMounted, reactive, ref } from 'vue';
import { BadgeCheck, Check, X, Eye } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const submissions = ref([]);
const processing = ref(null);
const creditDrafts = reactive({});

const columns = [
    { key: 'user_name', label: 'Pengguna' },
    { key: 'url', label: 'URL' },
    { key: 'views', label: 'Views', align: 'right' },
    { key: 'credits_awarded', label: 'Koin', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'reviewer_name', label: 'Peninjau' },
    { key: 'created_at', label: 'Dikirim' },
];

function statusMeta(status) {
    switch (status) {
        case 'approved':
            return { label: 'Disetujui', tone: 'success' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'danger' };
        default:
            return { label: 'Menunggu', tone: 'warning' };
    }
}

async function load() {
    loading.value = true;
    try {
        const data = await getJson('/api/admin/credit-submissions');
        total.value = data.total || 0;
        submissions.value = data.submissions || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

async function review(submission, decision) {
    processing.value = submission.id;
    const credits = Number(creditDrafts[submission.id] || 0);
    try {
        const res = await request(`/api/admin/credit-submissions/${submission.id}/review`, {
            method: 'POST',
            body: JSON.stringify({ decision, credits }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Pengajuan diproses.', 'success');
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal memproses.', 'error');
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
        <PageHeader title="Verifikasi Koin" :description="`${total} pengajuan koin`" />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="submissions" :loading="loading" empty-text="Belum ada pengajuan koin.">
                <template #cell-url="{ value }">
                    <a :href="value" target="_blank" rel="noopener noreferrer" class="max-w-[220px] truncate text-sm text-neutral-700 underline-offset-2 hover:underline dark:text-neutral-200">
                        {{ value }}
                    </a>
                </template>
                <template #cell-views="{ value }">
                    <span class="inline-flex items-center gap-1 text-sm text-neutral-500 dark:text-neutral-400">
                        <Eye class="h-3.5 w-3.5" /> {{ value ?? 0 }}
                    </span>
                </template>
                <template #cell-credits_awarded="{ value }">
                    <span v-if="Number(value) > 0" class="inline-flex items-center gap-1 text-sm font-medium">
                        <BadgeCheck class="h-4 w-4 text-emerald-500" /> +{{ value }}
                    </span>
                    <span v-else class="text-sm text-neutral-400 dark:text-neutral-500">-</span>
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :label="statusMeta(value).label" :tone="statusMeta(value).tone" />
                </template>
                <template #cell-reviewer_name="{ value }">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ value || '-' }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>

                <template #actions="{ row }">
                    <template v-if="row.status === 'pending'">
                        <input
                            v-model.number="creditDrafts[row.id]"
                            type="number"
                            min="0"
                            placeholder="0"
                            title="Koin yang diberikan"
                            class="w-20 rounded-lg border border-neutral-300 bg-neutral-50 px-2 py-1.5 text-xs outline-none focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-900"
                        />
                        <button
                            type="button"
                            :disabled="processing === row.id"
                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                            @click="review(row, 'approve')"
                        >
                            <Check class="h-3.5 w-3.5" /> Setujui
                        </button>
                        <button
                            type="button"
                            :disabled="processing === row.id"
                            class="inline-flex items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/40"
                            @click="review(row, 'reject')"
                        >
                            <X class="h-3.5 w-3.5" /> Tolak
                        </button>
                    </template>
                    <span v-else class="text-xs text-neutral-400 dark:text-neutral-500">-</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
