<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { Loader2, Check, Coins, Ticket, BadgeCheck } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import DataTable from '../../components/DataTable.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatCurrency, formatDate } from '../../utils/format';
import { getJson, request } from '../../utils/http';
import { toast } from '../../utils/toast';

const MIN_AMOUNT = 25000; // Rp minimal topup
const CREDIT_PER_AMOUNT = 500; // Rp 500 = 1 koin

const balance = ref(0);
const loading = ref(true);
const selected = ref(null);
const customAmount = ref(''); // string tampilan terformat, mis. "25.000"
const submitting = ref(false);

const packages = [
    { id: 1, amount: 50000, popular: false },
    { id: 2, amount: 100000, popular: true },
    { id: 3, amount: 200000, popular: false },
];

// ---- Promo ----
const promoCode = ref('');
const promo = ref(null);
const promoLoading = ref(false);

// ---- Langganan ----
const sub = ref(null); // { monthly_price, active, subscription }
const subscribing = ref(false);

// ---- Biaya / fee pembayaran ----
const route = useRoute();
const feeFixed = ref(2000);
const feePercent = ref(0);

function formatNumber(value) {
    return new Intl.NumberFormat('id-ID').format(value || 0);
}

function creditsFrom(amount) {
    return Math.floor(amount / CREDIT_PER_AMOUNT);
}

const customAmountValue = computed(() => {
    const n = Number(String(customAmount.value || '').replace(/\D/g, ''));
    return Number.isNaN(n) ? 0 : n;
});

const effectiveAmount = computed(() => {
    if (customAmountValue.value > 0) return customAmountValue.value;
    const pkg = packages.find((p) => p.id === selected.value);
    return pkg ? pkg.amount : 0;
});

const baseCredits = computed(() => creditsFrom(effectiveAmount.value));

const effectivePayable = computed(() => (promo.value ? promo.value.payable : effectiveAmount.value));
const effectiveBonus = computed(() => (promo.value ? promo.value.bonus_credits : 0));
const effectiveCredits = computed(() => (promo.value ? promo.value.total_credits : baseCredits.value));

const fee = computed(() => feeFixed.value + Math.round(effectivePayable.value * (feePercent.value / 100)));
const total = computed(() => effectivePayable.value + fee.value);

const canSubmit = computed(() => effectiveAmount.value >= MIN_AMOUNT && !submitting.value);

const isSubscribed = computed(() => !!sub.value?.active);
const subEndsAt = computed(() => sub.value?.subscription?.ends_at || null);

// Perpanjangan hanya bisa dilakukan mulai 5 hari sebelum masa aktif berakhir.
const RENEW_WINDOW_DAYS = 5;
const canRenew = computed(() => {
    if (!subEndsAt.value) return false;
    const ends = new Date(subEndsAt.value).getTime();
    if (Number.isNaN(ends)) return false;
    const threshold = ends - RENEW_WINDOW_DAYS * 24 * 60 * 60 * 1000;
    return Date.now() >= threshold;
});
const daysUntilRenewable = computed(() => {
    if (!subEndsAt.value) return 0;
    const ends = new Date(subEndsAt.value).getTime();
    if (Number.isNaN(ends)) return 0;
    const threshold = ends - RENEW_WINDOW_DAYS * 24 * 60 * 60 * 1000;
    return Math.max(0, Math.ceil((threshold - Date.now()) / (24 * 60 * 60 * 1000)));
});

function selectPackage(id) {
    selected.value = id;
    customAmount.value = '';
    clearPromo();
}

function onCustomInput(event) {
    selected.value = null;
    const digits = event.target.value.replace(/\D/g, '');
    customAmount.value = digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
    clearPromo();
}

function clearPromo() {
    promo.value = null;
    promoCode.value = '';
}

async function applyPromo() {
    const code = promoCode.value.trim();
    if (!code) {
        toast('Masukkan kode promo terlebih dahulu.', 'error');
        return;
    }
    if (effectiveAmount.value < MIN_AMOUNT) {
        toast('Pilih nominal minimal Rp 25.000 terlebih dahulu.', 'error');
        return;
    }

    promoLoading.value = true;
    try {
        const data = await getJson(`/api/coupons/validate?code=${encodeURIComponent(code)}&amount=${effectiveAmount.value}`);
        promo.value = data;
        promoCode.value = data.coupon.code;
        toast(`Promo ${data.coupon.code} diterapkan.`, 'success');
    } catch (e) {
        promo.value = null;
        toast(e.message, 'error');
    } finally {
        promoLoading.value = false;
    }
}

async function submit() {
    if (!canSubmit.value) return;
    submitting.value = true;
    try {
        const payload = { amount: effectiveAmount.value };
        if (promo.value) payload.coupon = promo.value.coupon.code;

        const res = await request('/api/wallet/topup', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            toast(res.data?.error || 'Topup gagal. Coba lagi.', 'error');
            return;
        }

        const payment = res.data?.payment;
        toast(res.data?.message || 'Silakan selesaikan pembayaran QRIS.', 'success');

        if (payment?.payment_url) {
            window.location.href = payment.payment_url;
            return;
        }

        selected.value = null;
        customAmount.value = '';
        clearPromo();
    } catch (e) {
        toast(e.message || 'Topup gagal. Coba lagi.', 'error');
    } finally {
        submitting.value = false;
    }
}

// ---- Langganan ----
async function loadSubscription() {
    try {
        const data = await getJson('/api/subscription');
        sub.value = data;
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function subscribe() {
    subscribing.value = true;
    try {
        const res = await request('/api/subscription/subscribe', { method: 'POST' });
        if (!res.ok) {
            toast(res.data?.error || 'Gagal berlangganan. Coba lagi.', 'error');
            return;
        }

        const payment = res.data?.payment;
        toast(res.data?.message || 'Silakan selesaikan pembayaran QRIS.', 'success');

        if (payment?.payment_url) {
            window.location.href = payment.payment_url;
            return;
        }

        await loadSubscription();
    } catch (e) {
        toast(e.message || 'Gagal berlangganan.', 'error');
    } finally {
        subscribing.value = false;
    }
}

// ---- Riwayat transaksi ----
const transactions = ref([]);

const transactionColumns = [
    { key: 'id', label: 'Transaksi', format: (v) => String(v || '').slice(0, 8).toUpperCase() },
    { key: 'date', label: 'Tanggal', format: (v) => formatDate(v, { withTime: true }) },
    { key: 'type', label: 'Tipe' },
    { key: 'amount', label: 'Koin', align: 'right', format: (v, row) => `${row.type === 'credit' ? '+' : '-'}${formatNumber(v)}` },
    { key: 'balance_after', label: 'Saldo', align: 'right', format: (v) => formatNumber(v) },
];

async function loadWallet() {
    loading.value = true;
    try {
        const data = await getJson('/api/wallet');
        balance.value = data.balance || 0;
    } catch (e) {
        toast(e.message || 'Gagal memuat saldo.', 'error');
    }

    try {
        const tx = await getJson('/api/wallet/transactions');
        transactions.value = (tx.transactions || []).map((t) => ({
            id: t.id,
            date: t.created_at,
            type: t.type,
            amount: t.amount,
            balance_after: t.balance_after,
        }));
    } catch {
        transactions.value = [];
    } finally {
        loading.value = false;
    }
}

function typeLabel(type) {
    return type === 'credit' ? 'Topup' : 'Pemakaian';
}

function typeTone(type) {
    return type === 'credit' ? 'success' : 'info';
}

async function loadPaymentMeta() {
    try {
        const data = await getJson('/api/payments/meta');
        feeFixed.value = data.fee_fixed ?? 2000;
        feePercent.value = data.fee_percent ?? 0;
    } catch {
        // biaya tetap pakai default bila meta gagal dimuat
    }
}

function handleReturnStatus() {
    const status = route.query.status;
    if (status === 'success') toast('Pembayaran diterima. Saldo koin akan segera ditambahkan.', 'success');
    else if (status === 'cancel') toast('Pembayaran dibatalkan.', 'error');
}

let subscriptionPoll = null;

onMounted(() => {
    loadWallet();
    loadSubscription();
    loadPaymentMeta();
    handleReturnStatus();
    // Pantau status langganan agar UI berubah tanpa refresh (mis. setelah bayar).
    subscriptionPoll = setInterval(loadSubscription, 30000);
});

onBeforeUnmount(() => {
    if (subscriptionPoll) clearInterval(subscriptionPoll);
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Topup" description="Isi saldo koin untuk menggunakan fitur AI." />

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Kolom form -->
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800 sm:p-6">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                            <Coins class="h-4 w-4" />
                        </span>
                        <div>
                            <h2 class="font-semibold">Pilih Nominal</h2>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Minimal Rp 25.000 · Rp 500 = 1 koin</p>
                        </div>
                    </div>

                    <!-- Paket -->
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <button
                            v-for="pkg in packages"
                            :key="pkg.id"
                            type="button"
                            class="relative cursor-pointer rounded-xl border p-4 text-left transition-all"
                            :class="selected === pkg.id
                                ? 'border-neutral-900 ring-1 ring-neutral-900 dark:border-white dark:ring-white'
                                : 'border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:border-neutral-600 dark:hover:bg-neutral-900/40'"
                            @click="selectPackage(pkg.id)"
                        >
                            <span
                                v-if="pkg.popular"
                                class="absolute -top-2 left-3 rounded-full bg-neutral-900 px-2 py-0.5 text-[10px] font-semibold text-white dark:bg-white dark:text-neutral-900"
                            >
                                Populer
                            </span>
                            <Check
                                v-if="selected === pkg.id"
                                class="absolute right-3 top-3 h-4 w-4 text-neutral-900 dark:text-white"
                            />
                            <p class="text-lg font-bold">{{ formatCurrency(pkg.amount) }}</p>
                            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ creditsFrom(pkg.amount) }} koin</p>
                        </button>
                    </div>

                    <!-- Nominal lainnya -->
                    <div class="mt-4">
                        <label for="custom-amount" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            Nominal Lainnya
                        </label>
                        <div class="mt-2 flex items-center rounded-lg border border-neutral-200 bg-white transition-colors focus-within:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus-within:border-neutral-400">
                            <span class="pl-3 text-sm text-neutral-500 dark:text-neutral-400">Rp</span>
                            <input
                                id="custom-amount"
                                :value="customAmount"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="Minimal 25.000"
                                class="w-full bg-transparent px-3 py-2.5 text-sm outline-none placeholder:text-neutral-400"
                                @input="onCustomInput"
                            />
                        </div>
                    </div>

                    <!-- Promo -->
                    <div class="mt-4">
                        <label for="promo-code" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            Kode Promo (opsional)
                        </label>
                        <div class="mt-2 flex gap-2">
                            <div class="flex flex-1 items-center rounded-lg border border-neutral-200 bg-white transition-colors focus-within:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus-within:border-neutral-400">
                                <Ticket class="ml-3 h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                                <input
                                    id="promo-code"
                                    v-model="promoCode"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="Contoh: SKRIPSI10"
                                    class="w-full bg-transparent px-3 py-2.5 text-sm uppercase outline-none placeholder:text-neutral-400"
                                    @keyup.enter="applyPromo"
                                />
                            </div>
                            <AppButton variant="outline" :disabled="promoLoading" @click="applyPromo">
                                <Loader2 v-if="promoLoading" class="h-4 w-4 animate-spin" />
                                {{ promoLoading ? '...' : 'Terapkan' }}
                            </AppButton>
                        </div>
                    </div>

                    <!-- Ringkasan -->
                    <div v-if="effectiveAmount > 0" class="mt-5 space-y-1.5 rounded-lg bg-neutral-50 p-4 text-sm dark:bg-neutral-900/50">
                        <div class="flex items-center justify-between text-neutral-600 dark:text-neutral-300">
                            <span>Nominal</span>
                            <span class="font-medium">{{ formatCurrency(effectivePayable) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-neutral-600 dark:text-neutral-300">
                            <span>Biaya</span>
                            <span class="font-medium">{{ formatCurrency(fee) }}</span>
                        </div>
                        <div v-if="promo" class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                            <span>Promo {{ promo.coupon.code }}</span>
                            <span class="font-medium">{{ promo.bonus_credits > 0 ? `Bonus +${formatNumber(promo.bonus_credits)} koin` : `Hemat ${formatCurrency(effectiveAmount - effectivePayable)}` }}</span>
                        </div>
                        <div class="flex items-center justify-between text-neutral-600 dark:text-neutral-300">
                            <span>Koin Didapat</span>
                            <span class="inline-flex items-center gap-1 font-semibold text-neutral-900 dark:text-white">
                                <Coins class="h-4 w-4" />
                                {{ formatNumber(effectiveCredits) }} koin
                            </span>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-neutral-200 pt-2 text-neutral-800 dark:border-neutral-800 dark:text-neutral-100">
                            <span class="font-semibold">Total Pembayaran</span>
                            <span class="font-semibold">{{ formatCurrency(total) }}</span>
                        </div>
                        <p v-if="promo && effectiveBonus > 0" class="pt-1 text-xs text-emerald-600 dark:text-emerald-400">
                            Termasuk bonus {{ formatNumber(effectiveBonus) }} koin.
                        </p>
                        <p v-if="effectiveAmount < MIN_AMOUNT" class="pt-1 text-xs text-amber-600 dark:text-amber-400">
                            Nominal minimal Rp {{ formatNumber(MIN_AMOUNT) }}.
                        </p>
                    </div>

                    <!-- Notifikasi -->
                    <div class="mt-6">
                        <AppButton block :disabled="!canSubmit" @click="submit">
                            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                            {{ submitting ? 'Memproses...' : 'Topup Sekarang' }}
                        </AppButton>
                    </div>
                </div>
            </div>

            <!-- Kolom info saldo & langganan -->
            <div class="space-y-4">
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                            <Coins class="h-4 w-4" />
                        </span>
                        Saldo Koin
                    </div>
                    <p class="mt-3 text-3xl font-bold tracking-tight">{{ formatNumber(balance) }}</p>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">koin tersedia</p>
                </div>

                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                        <BadgeCheck class="h-4 w-4" />
                        Langganan Bulanan
                    </div>

                    <div v-if="isSubscribed" class="mt-3">
                        <StatusBadge label="Aktif" tone="success" />
                        <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                            Berlaku sampai <span class="font-medium">{{ formatDate(subEndsAt, { withTime: true }) }}</span>.
                        </p>
                        <AppButton block variant="outline" class="mt-3" :disabled="subscribing || !canRenew" @click="subscribe">
                            {{ subscribing ? 'Memproses…' : 'Perpanjang Langganan' }}
                        </AppButton>
                        <p v-if="!canRenew" class="mt-2 text-xs text-neutral-400 dark:text-neutral-500">
                            Perpanjangan tersedia {{ daysUntilRenewable }} hari lagi (5 hari sebelum berakhir).
                        </p>
                    </div>

                    <div v-else class="mt-3">
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            Aktifkan agar bisa mengunduh PDF, memakai Agent Canvas, Turnitin, dan Plagiarism.
                        </p>
                        <p class="mt-2 text-lg font-bold">{{ formatCurrency(sub?.monthly_price || 30000) }} <span class="text-xs font-normal text-neutral-400">/ 30 hari</span></p>
                        <AppButton block class="mt-3" :disabled="subscribing" @click="subscribe">
                            <Loader2 v-if="subscribing" class="h-4 w-4 animate-spin" />
                            {{ subscribing ? 'Memproses…' : 'Berlangganan' }}
                        </AppButton>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 p-5 text-sm dark:border-neutral-800">
                    <p class="font-medium text-neutral-700 dark:text-neutral-300">Kurs Topup</p>
                    <p class="mt-2 text-neutral-500 dark:text-neutral-400">Rp 500 = 1 koin</p>
                    <p class="mt-1 text-neutral-500 dark:text-neutral-400">Minimal topup Rp 25.000</p>
                </div>
            </div>
        </div>

        <!-- Riwayat -->
        <div class="mt-8">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold">Riwayat Transaksi</h2>
                <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ transactions.length }} transaksi</span>
            </div>
            <DataTable
                :columns="transactionColumns"
                :rows="transactions"
                :loading="loading"
                empty-text="Belum ada transaksi."
            >
                <template #cell-type="{ value }">
                    <StatusBadge :label="typeLabel(value)" :tone="typeTone(value)" />
                </template>
            </DataTable>
        </div>
    </div>
</template>
