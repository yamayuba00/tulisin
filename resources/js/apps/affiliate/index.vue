<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link2, Copy, Check, Users, Coins, Gift } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import DataTable from '../../components/DataTable.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { getJson, request } from '../../utils/http';
import { toast } from '../../utils/toast';
import { formatDate } from '../../utils/format';

const loading = ref(true);
const code = ref('');
const isActive = ref(true);
const creditPerReferral = ref(20);
const totalReferred = ref(0);
const earnedCredits = ref(0);
const referrals = ref([]);
const copied = ref(false);

const draftCode = ref('');
const saving = ref(false);

const referralLink = computed(() => `${window.location.origin}/register?ref=${code.value}`);

const columns = [
    { key: 'name', label: 'Pengguna' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Tanggal' },
];

function referralStatus(status) {
    switch (status) {
        case 'approved':
            return { label: 'Aktif', tone: 'success' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'danger' };
        case 'registered':
            return { label: 'Terdaftar', tone: 'info' };
        case 'pending':
            return { label: 'Menunggu Verifikasi', tone: 'warning' };
        default:
            return { label: status || '-', tone: 'neutral' };
    }
}

onMounted(async () => {
    try {
        const data = await getJson('/api/affiliate');
        code.value = data.code || '';
        draftCode.value = code.value;
        isActive.value = data.is_active !== false;
        creditPerReferral.value = data.credit_per_referral || 20;
        totalReferred.value = data.total_referred || 0;
        earnedCredits.value = data.earned_credits || 0;
        referrals.value = data.referrals || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});

async function saveCode() {
    const value = draftCode.value.trim().toUpperCase();

    if (!value) {
        toast('Kode referral wajib diisi.', 'warning');
        return;
    }
    if (value.length < 4) {
        toast('Kode referral minimal 4 karakter.', 'warning');
        return;
    }
    if (value.length > 40) {
        toast('Kode referral maksimal 40 karakter.', 'warning');
        return;
    }
    if (!/^[A-Z0-9]+$/.test(value)) {
        toast('Hanya huruf dan angka, tanpa spasi.', 'warning');
        return;
    }

    saving.value = true;
    try {
        const res = await request('/api/affiliate/code', {
            method: 'POST',
            body: JSON.stringify({ code: value }),
        });
        if (!res.ok) {
            toast(res.data?.error || 'Gagal menyimpan kode.', 'error');
            return;
        }
        code.value = value;
        draftCode.value = value;
        toast('Kode referral tersimpan.', 'success');
    } finally {
        saving.value = false;
    }
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(referralLink.value);
    } catch {
        const el = document.createElement('textarea');
        el.value = referralLink.value;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        el.remove();
    }
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Affiliate" description="Ajak temanmu mendaftar, dapatkan koin setiap ada yang bergabung." />

        <div v-if="loading" class="flex items-center justify-center py-16 text-sm text-neutral-500 dark:text-neutral-400">
            Memuat…
        </div>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        <Users class="h-4 w-4" />
                        Teman Terdaftar
                    </div>
                    <p class="mt-2 text-3xl font-semibold">{{ totalReferred }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        <Coins class="h-4 w-4" />
                        Koin Didapat
                    </div>
                    <p class="mt-2 text-3xl font-semibold">{{ earnedCredits }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        <Gift class="h-4 w-4" />
                        Bonus per Teman
                    </div>
                    <p class="mt-2 text-3xl font-semibold">{{ creditPerReferral }} <span class="text-base font-normal text-neutral-500">koin</span></p>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <p class="text-sm font-medium">Link Referral Kamu</p>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Bagikan link ini. Setiap orang yang mendaftar lewat link kamu memberi kamu +{{ creditPerReferral }} koin,
                    setelah diverifikasi admin.
                </p>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <div class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 dark:border-neutral-800 dark:bg-neutral-900">
                        <Link2 class="h-4 w-4 shrink-0 text-neutral-400" />
                        <span class="truncate font-mono text-sm text-neutral-700 dark:text-neutral-300">{{ referralLink }}</span>
                    </div>
                    <AppButton @click="copyLink">
                        <Check v-if="copied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        {{ copied ? 'Tersalin' : 'Salin Link' }}
                    </AppButton>
                </div>

                <div class="mt-3 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kode Referral Kustom</label>
                    <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">Huruf & angka saja, tanpa spasi (minimal 4 karakter).</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input
                            v-model="draftCode"
                            type="text"
                            maxlength="40"
                            placeholder="CONTOH123"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 font-mono text-sm uppercase outline-none transition focus:border-neutral-400 dark:border-neutral-800 dark:focus:border-neutral-500"
                        />
                        <AppButton :disabled="saving" @click="saveCode">
                            {{ saving ? 'Menyimpan…' : 'Simpan Kode' }}
                        </AppButton>
                    </div>
                    <div class="mt-2 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                        <StatusBadge :label="isActive ? 'Aktif' : 'Nonaktif'" :tone="isActive ? 'success' : 'neutral'" />
                    </div>
                </div>
            </div>

            <h2 class="mt-8 mb-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">Daftar Teman yang Mendaftar</h2>
            <DataTable :columns="columns" :rows="referrals" :loading="false" empty-text="Belum ada teman yang mendaftar lewat link kamu.">
                <template #cell-name="{ value }">
                    <span class="font-medium">{{ value || '-' }}</span>
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :label="referralStatus(value).label" :tone="referralStatus(value).tone" />
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
            </DataTable>
        </template>
    </div>
</template>
