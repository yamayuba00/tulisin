<script setup>
import { onMounted, ref } from 'vue';
import { Wallet } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const transactions = ref([]);

const columns = [
    { key: 'user_name', label: 'Pengguna' },
    { key: 'type', label: 'Tipe' },
    { key: 'amount', label: 'Jumlah', align: 'right' },
    { key: 'balance_after', label: 'Saldo Akhir', align: 'right' },
    { key: 'reason', label: 'Alasan' },
    { key: 'created_at', label: 'Waktu' },
];

function typeMeta(type) {
    return type === 'credit'
        ? { label: 'Kredit', tone: 'success' }
        : { label: 'Debit', tone: 'danger' };
}

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/credit-transactions');
        total.value = data.total || 0;
        transactions.value = data.transactions || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Riwayat Koin" :description="`${total} pergerakan koin`" />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="transactions" :loading="loading" empty-text="Belum ada pergerakan koin.">
                <template #cell-user_name="{ value }">
                    <span class="inline-flex items-center gap-2 text-sm">
                        <Wallet class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        {{ value || '-' }}
                    </span>
                </template>
                <template #cell-type="{ value }">
                    <StatusBadge :label="typeMeta(value).label" :tone="typeMeta(value).tone" />
                </template>
                <template #cell-amount="{ row }">
                    <span class="text-sm font-medium" :class="row.type === 'credit' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                        {{ row.type === 'credit' ? '+' : '-' }}{{ row.amount }}
                    </span>
                </template>
                <template #cell-reason="{ value }">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ value || '-' }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
