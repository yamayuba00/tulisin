<script setup>
import { onMounted, ref } from 'vue';
import { Handshake, Check, X, UserPlus } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatCurrency, formatDate } from '../../../utils/format';

const loading = ref(true);
const codes = ref([]);
const commissions = ref([]);
const referrals = ref([]);
const processing = ref(null);

const codeColumns = [
    { key: 'user_name', label: 'Affiliate' },
    { key: 'code', label: 'Kode Referral' },
    { key: 'is_active', label: 'Status' },
    { key: 'created_at', label: 'Dibuat' },
];

const referralColumns = [
    { key: 'referrer_name', label: 'Affiliate' },
    { key: 'referred_name', label: 'Pendaftar' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Tanggal' },
];

const commissionColumns = [
    { key: 'affiliate_name', label: 'Affiliate' },
    { key: 'amount', label: 'Komisi', align: 'right' },
    { key: 'rate', label: 'Rate (%)', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Tanggal' },
];

function referralStatus(status) {
    switch (status) {
        case 'approved':
            return { label: 'Disetujui', tone: 'success' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'danger' };
        case 'registered':
            return { label: 'Terdaftar', tone: 'info' };
        default:
            return { label: 'Menunggu', tone: 'warning' };
    }
}

function commissionStatus(status) {
    switch (status) {
        case 'approved':
            return { label: 'Disetujui', tone: 'success' };
        case 'paid':
            return { label: 'Dibayar', tone: 'info' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'danger' };
        default:
            return { label: 'Pending', tone: 'warning' };
    }
}

async function load() {
    loading.value = true;
    try {
        const [affData, refData] = await Promise.all([
            getJson('/api/admin/affiliates'),
            getJson('/api/admin/referrals'),
        ]);
        codes.value = affData.codes || [];
        commissions.value = affData.commissions || [];
        referrals.value = refData.referrals || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

async function reviewCommission(commission, decision) {
    processing.value = commission.id;
    try {
        const res = await request(`/api/admin/affiliates/commissions/${commission.id}/review`, {
            method: 'POST',
            body: JSON.stringify({ decision }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Komisi diproses.', 'success');
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

async function reviewReferral(referral, decision) {
    processing.value = referral.id;
    try {
        const res = await request(`/api/admin/referrals/${referral.id}/review`, {
            method: 'POST',
            body: JSON.stringify({ decision }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Referral diproses.', 'success');
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
        <PageHeader title="Affiliate" description="Verifikasi referral, komisi, dan kode afiliasi." />

        <h2 class="mt-6 mb-2 flex items-center gap-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">
            <UserPlus class="h-4 w-4" /> Verifikasi Referral (+20 koin)
        </h2>
        <p class="mb-3 text-xs text-neutral-400 dark:text-neutral-500">
            Setiap pendaftar lewat kode afiliasi. Setujui untuk menambahkan +20 koin ke afiliasi.
        </p>
        <DataTable :columns="referralColumns" :rows="referrals" :loading="loading" empty-text="Belum ada referral.">
            <template #cell-referrer_name="{ value }">
                <span class="font-medium">{{ value || '-' }}</span>
            </template>
            <template #cell-status="{ value }">
                <StatusBadge :label="referralStatus(value).label" :tone="referralStatus(value).tone" />
            </template>
            <template #cell-created_at="{ value }">
                <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
            </template>
            <template #actions="{ row }">
                <template v-if="row.status === 'pending' || row.status === 'registered'">
                    <button
                        type="button"
                        :disabled="processing === row.id"
                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                        @click="reviewReferral(row, 'approve')"
                    >
                        <Check class="h-3.5 w-3.5" /> Setujui
                    </button>
                    <button
                        type="button"
                        :disabled="processing === row.id"
                        class="inline-flex items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/40"
                        @click="reviewReferral(row, 'reject')"
                    >
                        <X class="h-3.5 w-3.5" /> Tolak
                    </button>
                </template>
                <span v-else class="text-xs text-neutral-400 dark:text-neutral-500">-</span>
            </template>
        </DataTable>

        <h2 class="mt-8 mb-2 flex items-center gap-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">
            <Handshake class="h-4 w-4" /> Kode Referral
        </h2>
        <DataTable :columns="codeColumns" :rows="codes" :loading="loading" empty-text="Belum ada kode referral.">
            <template #cell-code="{ value }">
                <span class="rounded-md border border-neutral-200 px-2 py-0.5 font-mono text-xs dark:border-neutral-800">{{ value }}</span>
            </template>
            <template #cell-is_active="{ value }">
                <StatusBadge :label="value ? 'Aktif' : 'Nonaktif'" :tone="value ? 'success' : 'neutral'" />
            </template>
            <template #cell-created_at="{ value }">
                <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value) }}</span>
            </template>
        </DataTable>

        <h2 class="mt-8 mb-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">Riwayat Komisi</h2>
        <DataTable :columns="commissionColumns" :rows="commissions" :loading="loading" empty-text="Belum ada komisi.">
            <template #cell-amount="{ value }">
                <span class="text-sm font-medium">{{ formatCurrency(Number(value)) }}</span>
            </template>
            <template #cell-status="{ value }">
                <StatusBadge :label="commissionStatus(value).label" :tone="commissionStatus(value).tone" />
            </template>
            <template #cell-created_at="{ value }">
                <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
            </template>
            <template #actions="{ row }">
                <template v-if="row.status === 'pending'">
                    <button
                        type="button"
                        :disabled="processing === row.id"
                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                        @click="reviewCommission(row, 'approve')"
                    >
                        <Check class="h-3.5 w-3.5" /> Setujui
                    </button>
                    <button
                        type="button"
                        :disabled="processing === row.id"
                        class="inline-flex items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/40"
                        @click="reviewCommission(row, 'reject')"
                    >
                        <X class="h-3.5 w-3.5" /> Tolak
                    </button>
                </template>
                <span v-else class="text-xs text-neutral-400 dark:text-neutral-500">-</span>
            </template>
        </DataTable>
    </div>
</template>
