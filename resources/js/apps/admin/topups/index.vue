<script setup>
import { onMounted, ref } from 'vue';
import { CreditCard } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatCurrency, formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const orders = ref([]);

const columns = [
    { key: 'user_name', label: 'Pengguna' },
    { key: 'credits', label: 'Koin', align: 'right' },
    { key: 'amount', label: 'Nominal', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Tanggal' },
];

function statusMeta(status) {
    switch (status) {
        case 'paid':
            return { label: 'Lunas', tone: 'success' };
        case 'failed':
            return { label: 'Gagal', tone: 'danger' };
        default:
            return { label: 'Pending', tone: 'warning' };
    }
}

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/topup-orders');
        total.value = data.total || 0;
        orders.value = data.orders || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Topup Orders" :description="`${total} order pembelian koin`" />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="orders" :loading="loading" empty-text="Belum ada order topup.">
                <template #cell-user_name="{ value }">
                    <span class="inline-flex items-center gap-2 text-sm">
                        <CreditCard class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        {{ value || '-' }}
                    </span>
                </template>
                <template #cell-credits="{ value }">
                    <span class="text-sm font-medium">+{{ value }}</span>
                </template>
                <template #cell-amount="{ value }">
                    <span class="text-sm font-medium">{{ formatCurrency(Number(value)) }}</span>
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :label="statusMeta(value).label" :tone="statusMeta(value).tone" />
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
