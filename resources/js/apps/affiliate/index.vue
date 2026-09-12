<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link2, Copy, Check, Users, Coins, Landmark, Download, Banknote, Wallet, Gift } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import DataTable from '../../components/DataTable.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { getJson, request, ensureCsrf } from '../../utils/http';
import { toast } from '../../utils/toast';
import { formatDate, formatCurrency } from '../../utils/format';
import appName from '../../utils/appName';

const loading = ref(true);
const code = ref('');
const isActive = ref(true);
const totalReferred = ref(0);
const commissionBalance = ref(0);
const totalWithdrawn = ref(0);
const conversionRate = ref(250);
const minWithdrawal = ref(50000);
const commissionPerReferral = ref(4000);
const referralDiscount = ref(10000);
const referrals = ref([]);
const payouts = ref([]);
const copied = ref(false);

const draftCode = ref('');
const saving = ref(false);

const withdrawType = ref('coins');
const bankName = ref('');
const accountNumber = ref('');
const accountName = ref('');
const withdrawing = ref(false);

const referralLink = computed(() => `${window.location.origin}/register?ref=${code.value}`);

const estimatedCoins = computed(() => Math.floor((commissionBalance.value || 0) / (conversionRate.value || 1)));

const canWithdraw = computed(() => (commissionBalance.value || 0) >= (minWithdrawal.value || 0));

const columns = [
    { key: 'name', label: 'Pengguna' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Tanggal' },
];

const payoutColumns = [
    { key: 'created_at', label: 'Tanggal' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'method', label: 'Metode' },
    { key: 'status', label: 'Status' },
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
            return { label: 'Menunggu', tone: 'warning' };
        default:
            return { label: status || '-', tone: 'neutral' };
    }
}

function payoutStatus(status) {
    switch (status) {
        case 'paid':
            return { label: 'Dibayar', tone: 'success' };
        case 'pending':
            return { label: 'Diproses', tone: 'warning' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'danger' };
        default:
            return { label: status || '-', tone: 'neutral' };
    }
}

function methodLabel(method) {
    return method === 'coins' ? 'Koin' : method === 'bank' ? 'Bank' : method || '-';
}

async function loadData() {
    try {
        const data = await getJson('/api/affiliate');
        code.value = data.code || '';
        draftCode.value = code.value;
        isActive.value = data.is_active !== false;
        totalReferred.value = data.total_referred || 0;
        commissionBalance.value = data.commission_balance || 0;
        totalWithdrawn.value = data.total_withdrawn || 0;
        conversionRate.value = data.conversion_rate || 250;
        minWithdrawal.value = data.min_withdrawal || 50000;
        commissionPerReferral.value = data.commission_per_referral || 4000;
        referralDiscount.value = data.referral_discount || 10000;
        referrals.value = data.referrals || [];
    } catch (e) {
        toast(e.message, 'error');
    }

    try {
        const data = await getJson('/api/affiliate/payouts');
        payouts.value = data.payouts || [];
    } catch {
        payouts.value = [];
    }

    loading.value = false;
}

onMounted(loadData);

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

async function withdraw() {
    if (!canWithdraw.value) {
        toast('Saldo komisi belum mencapai minimal penarikan.', 'warning');
        return;
    }

    if (withdrawType.value === 'bank') {
        if (!bankName.value.trim() || !accountNumber.value.trim() || !accountName.value.trim()) {
            toast('Nama bank, nomor rekening, dan nama pemilik wajib diisi.', 'warning');
            return;
        }
    }

    withdrawing.value = true;
    try {
        await ensureCsrf();
        const res = await request('/api/affiliate/withdraw', {
            method: 'POST',
            body: JSON.stringify({
                type: withdrawType.value,
                bank_name: bankName.value,
                account_number: accountNumber.value,
                account_name: accountName.value,
            }),
        });

        if (!res.ok) {
            toast(res.data?.error || 'Gagal melakukan penarikan.', 'error');
            return;
        }

        toast(res.data?.message || 'Penarikan berhasil.', 'success');
        await loadData();
    } finally {
        withdrawing.value = false;
    }
}

function escapeXml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

// Render kartu promosi (HTML/SVG) lalu unduh sebagai PNG.
async function downloadPromo() {
    const name = escapeXml(appName);
    const promoCode = escapeXml(code.value || 'KODE');
    const promoLink = escapeXml(referralLink.value);

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675" viewBox="0 0 1200 675">
  <rect width="1200" height="675" fill="#ffffff"/>
  <rect x="48" y="48" width="1104" height="579" rx="24" fill="none" stroke="#e5e5e5" stroke-width="1.5"/>

  <rect x="88" y="96" width="56" height="56" rx="14" fill="#171717"/>
  <g transform="translate(100,108) scale(1.333)" stroke="#ffffff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 20h9"/>
    <path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.855z"/>
  </g>

  <text x="164" y="140" font-family="Arial, sans-serif" font-size="44" font-weight="bold" fill="#171717">${name}</text>

  <text x="88" y="238" font-family="Arial, sans-serif" font-size="28" fill="#525252">Platform menulis skripsi, tesis &amp; disertasi</text>

  <line x1="88" y1="286" x2="1112" y2="286" stroke="#e5e5e5" stroke-width="1"/>

  <text x="88" y="340" font-family="Arial, sans-serif" font-size="22" fill="#737373">Kode Referral</text>
  <rect x="88" y="360" width="360" height="88" rx="14" fill="#fafafa" stroke="#e5e5e5" stroke-width="1.5"/>
  <text x="116" y="416" font-family="'Courier New', monospace" font-size="44" font-weight="bold" fill="#171717">${promoCode}</text>

  <text x="88" y="510" font-family="Arial, sans-serif" font-size="24" fill="#404040">✓ Kamu dapat komisi Rp 4.000 tiap referral berlangganan</text>
  <text x="88" y="550" font-family="Arial, sans-serif" font-size="24" fill="#404040">✓ Temanmu hemat Rp 10.000 di langganan pertama</text>

  <text x="88" y="600" font-family="Arial, sans-serif" font-size="18" fill="#a3a3a3">${promoLink}</text>
</svg>`;

    const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);

    const img = new Image();
    await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = reject;
        img.src = url;
    });

    const canvas = document.createElement('canvas');
    canvas.width = 1200;
    canvas.height = 675;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, 1200, 675);
    URL.revokeObjectURL(url);

    canvas.toBlob((png) => {
        if (!png) return;
        const a = document.createElement('a');
        a.href = URL.createObjectURL(png);
        a.download = `${appName.toLowerCase()}-afiliasi.png`;
        document.body.appendChild(a);
        a.click();
        a.remove();
    }, 'image/png');
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Afiliasi" description="Ajak teman berlangganan, kumpulkan komisi, dan tukarkan jadi koin atau uang." />

        <div v-if="loading" class="flex items-center justify-center py-16 text-sm text-neutral-500 dark:text-neutral-400">
            Memuat…
        </div>

        <template v-else>
            <!-- Statistik -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        <Users class="h-4 w-4" />
                        Teman Terdaftar
                    </div>
                    <p class="mt-2 text-3xl font-semibold">{{ totalReferred }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        <Banknote class="h-4 w-4" />
                        Saldo Komisi
                    </div>
                    <p class="mt-2 text-3xl font-semibold">{{ formatCurrency(commissionBalance) }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        <Wallet class="h-4 w-4" />
                        Total Ditarik
                    </div>
                    <p class="mt-2 text-3xl font-semibold">{{ formatCurrency(totalWithdrawn) }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        <Gift class="h-4 w-4" />
                        Komisi per Referral
                    </div>
                    <p class="mt-2 text-3xl font-semibold">{{ formatCurrency(commissionPerReferral) }}</p>
                </div>
            </div>

            <!-- Link referral -->
            <div class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <p class="text-sm font-medium">Link Referral Kamu</p>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Bagikan link ini. Setiap teman yang mendaftar & membeli langganan pertamanya memberi kamu komisi
                    {{ formatCurrency(commissionPerReferral) }}.
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
                    <AppButton variant="outline" @click="downloadPromo">
                        <Download class="h-4 w-4" />
                        Unduh Materi Promosi
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

            <!-- Cara kerja -->
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <Gift class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                        <h2 class="text-sm font-semibold">Keuntungan Kamu</h2>
                    </div>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Dapatkan komisi <span class="font-semibold text-neutral-900 dark:text-white">{{ formatCurrency(commissionPerReferral) }}</span>
                        setiap kali temanmu membeli langganan bulanan untuk pertama kali.
                    </p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <Landmark class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                        <h2 class="text-sm font-semibold">Keuntungan Temanmu</h2>
                    </div>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Temanmu otomatis hemat <span class="font-semibold text-neutral-900 dark:text-white">{{ formatCurrency(referralDiscount) }}</span>
                        pada pembelian langganan pertamanya lewat link kamu.
                    </p>
                </div>
            </div>

            <!-- Penarikan komisi -->
            <div class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex items-center gap-2">
                    <Coins class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                    <h2 class="text-sm font-semibold">Tarik Komisi</h2>
                </div>

                <p class="mt-2 text-xs text-neutral-400 dark:text-neutral-500">
                    Konversi: 1 koin = Rp {{ conversionRate }} · Minimal penarikan: {{ formatCurrency(minWithdrawal) }}
                </p>

                <div class="mt-4 flex rounded-lg border border-neutral-200 p-0.5 dark:border-neutral-800">
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="withdrawType === 'coins' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                        @click="withdrawType = 'coins'"
                    >
                        Tukar ke Koin
                    </button>
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="withdrawType === 'bank' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                        @click="withdrawType = 'bank'"
                    >
                        Tarik ke Rekening
                    </button>
                </div>

                <div v-if="withdrawType === 'coins'" class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        Saldo komisi {{ formatCurrency(commissionBalance) }} akan ditukar menjadi
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ estimatedCoins }} koin</span>.
                    </p>
                </div>

                <div v-else class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nama Bank</label>
                        <input
                            v-model="bankName"
                            type="text"
                            placeholder="BCA / BRI / Mandiri"
                            class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition focus:border-neutral-400 dark:border-neutral-800 dark:focus:border-neutral-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nomor Rekening</label>
                        <input
                            v-model="accountNumber"
                            type="text"
                            inputmode="numeric"
                            placeholder="Nomor rekening"
                            class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition focus:border-neutral-400 dark:border-neutral-800 dark:focus:border-neutral-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nama Pemilik Rekening</label>
                        <input
                            v-model="accountName"
                            type="text"
                            placeholder="Sesuai rekening"
                            class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition focus:border-neutral-400 dark:border-neutral-800 dark:focus:border-neutral-500"
                        />
                    </div>
                </div>

                <p v-if="withdrawType === 'bank'" class="mt-3 text-xs text-neutral-400 dark:text-neutral-500">
                    Penarikan ke rekening diproses dalam 1–3 hari kerja.
                </p>

                <div class="mt-4">
                    <AppButton :disabled="withdrawing || !canWithdraw" @click="withdraw">
                        {{ withdrawing ? 'Memproses…' : 'Ajukan Penarikan' }}
                    </AppButton>
                    <p v-if="!canWithdraw" class="mt-2 text-xs text-neutral-400 dark:text-neutral-500">
                        Saldo belum mencapai minimal penarikan {{ formatCurrency(minWithdrawal) }}.
                    </p>
                </div>
            </div>

            <!-- Riwayat penarikan -->
            <h2 class="mt-8 mb-2 text-sm font-medium text-neutral-500 dark:text-neutral-400">Riwayat Penarikan</h2>
            <DataTable :columns="payoutColumns" :rows="payouts" :loading="false" empty-text="Belum ada riwayat penarikan.">
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>
                <template #cell-amount="{ value }">
                    <span class="font-medium">{{ formatCurrency(value) }}</span>
                </template>
                <template #cell-method="{ value }">
                    <span class="capitalize">{{ methodLabel(value) }}</span>
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :label="payoutStatus(value).label" :tone="payoutStatus(value).tone" />
                </template>
            </DataTable>

            <!-- Daftar referral -->
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
