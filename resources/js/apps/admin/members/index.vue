<script setup>
import { computed, onMounted, ref } from 'vue';
import { CalendarPlus, Coins, Crown } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import SearchInput from '../../../components/SearchInput.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const users = ref([]);
const query = ref('');

const subscribing = ref(null);
const crediting = ref(null);
const busy = ref(false);

const subscribeDays = ref(30);
const creditAmount = ref(100);
const creditNote = ref('');

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'subscription', label: 'Langganan' },
    { key: 'balance', label: 'Saldo Koin' },
    { key: 'ambassador', label: 'Brand Ambassador' },
];

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return users.value;
    return users.value.filter(
        (u) =>
            (u.name || '').toLowerCase().includes(q) ||
            (u.email || '').toLowerCase().includes(q),
    );
});

async function load() {
    loading.value = true;
    try {
        const data = await getJson('/api/admin/user-manage');
        total.value = data.total || 0;
        users.value = data.users || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

function openSubscribe(user) {
    subscribeDays.value = 30;
    subscribing.value = user;
}

async function confirmSubscribe() {
    if (!subscribing.value) return;
    if (Number(subscribeDays.value) < 1) {
        toast('Durasi minimal 1 hari.', 'error');
        return;
    }

    busy.value = true;
    try {
        const res = await request(`/api/admin/user-manage/${subscribing.value.id}/subscribe`, {
            method: 'POST',
            body: JSON.stringify({ days: Number(subscribeDays.value) }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Langganan diperbarui.', 'success');
            subscribing.value = null;
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal memperbarui langganan.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

function openCredit(user) {
    creditAmount.value = 100;
    creditNote.value = '';
    crediting.value = user;
}

async function confirmCredit() {
    if (!crediting.value) return;
    if (Number(creditAmount.value) < 1) {
        toast('Jumlah koin minimal 1.', 'error');
        return;
    }

    busy.value = true;
    try {
        const res = await request(`/api/admin/user-manage/${crediting.value.id}/credit`, {
            method: 'POST',
            body: JSON.stringify({
                amount: Number(creditAmount.value),
                note: creditNote.value.trim() || null,
            }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Koin berhasil ditambahkan.', 'success');
            crediting.value = null;
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal menambah koin.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

async function toggleAmbassador(user) {
    const enabled = !user.is_brand_ambassador;
    try {
        const res = await request(`/api/admin/user-manage/${user.id}/brand-ambassador`, {
            method: 'POST',
            body: JSON.stringify({ enabled }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Role diperbarui.', 'success');
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal memperbarui role.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader
            title="Kelola Member"
            :description="`${total} akun · atur langganan, koin, dan role Brand Ambassador.`"
        />

        <div class="mt-6 sm:w-72">
            <SearchInput v-model="query" placeholder="Cari nama atau email..." />
        </div>

        <div class="mt-4">
            <DataTable :columns="columns" :rows="filtered" :loading="loading" empty-text="Belum ada pengguna.">
                <template #cell-name="{ row }">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-neutral-200 bg-neutral-50 text-xs font-semibold text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
                            {{ (row.name || 'U').trim()[0].toUpperCase() }}
                        </div>
                        <div>
                            <p class="font-medium">{{ row.name }}</p>
                            <p class="text-xs text-neutral-400 dark:text-neutral-500">{{ row.email }}</p>
                        </div>
                    </div>
                </template>

                <template #cell-subscription="{ row }">
                    <StatusBadge :label="row.subscription_active ? 'Aktif' : 'Tidak aktif'" :tone="row.subscription_active ? 'success' : 'neutral'" />
                    <p v-if="row.subscription_ends_at" class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">
                        s/d {{ formatDate(row.subscription_ends_at) }}
                    </p>
                </template>

                <template #cell-balance="{ row }">
                    <span class="font-medium">{{ row.wallet_balance ?? 0 }}</span>
                    <span class="ml-1 text-xs text-neutral-400 dark:text-neutral-500">koin</span>
                </template>

                <template #cell-ambassador="{ row }">
                    <StatusBadge v-if="row.is_brand_ambassador" label="Brand Ambassador" tone="info" />
                    <span v-else class="text-xs text-neutral-400 dark:text-neutral-500">—</span>
                </template>

                <template #actions="{ row }">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="openSubscribe(row)"
                    >
                        <CalendarPlus class="h-3.5 w-3.5" /> Subscribe
                    </button>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="openCredit(row)"
                    >
                        <Coins class="h-3.5 w-3.5" /> Koin
                    </button>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                        :class="row.is_brand_ambassador
                            ? 'border-neutral-300 text-neutral-500 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800'
                            : 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-950/40'"
                        @click="toggleAmbassador(row)"
                    >
                        <Crown class="h-3.5 w-3.5" />
                        {{ row.is_brand_ambassador ? 'Cabut' : 'Ambassador' }}
                    </button>
                </template>
            </DataTable>
        </div>

        <!-- Modal Subscribe -->
        <div v-if="subscribing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-950">
                <h3 class="text-lg font-semibold">Subscribe {{ subscribing.name }}</h3>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Aktifkan atau perpanjang langganan secara manual.
                </p>

                <label class="mt-4 block text-sm font-medium">Durasi (hari)</label>
                <input
                    v-model.number="subscribeDays"
                    type="number"
                    min="1"
                    max="365"
                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-900"
                />

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="subscribing = null"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="busy"
                        class="rounded-lg border border-neutral-900 px-4 py-2 text-sm font-medium text-neutral-900 hover:bg-neutral-900 hover:text-white disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                        @click="confirmSubscribe"
                    >
                        {{ busy ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Koin -->
        <div v-if="crediting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-950">
                <h3 class="text-lg font-semibold">Tambah Koin {{ crediting.name }}</h3>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Saldo saat ini: {{ crediting.wallet_balance ?? 0 }} koin.
                </p>

                <label class="mt-4 block text-sm font-medium">Jumlah koin</label>
                <input
                    v-model.number="creditAmount"
                    type="number"
                    min="1"
                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-900"
                />

                <label class="mt-4 block text-sm font-medium">Catatan (opsional)</label>
                <input
                    v-model="creditNote"
                    type="text"
                    maxlength="191"
                    placeholder="Mis. Bonus Brand Ambassador"
                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-900"
                />

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="crediting = null"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="busy"
                        class="rounded-lg border border-neutral-900 px-4 py-2 text-sm font-medium text-neutral-900 hover:bg-neutral-900 hover:text-white disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                        @click="confirmCredit"
                    >
                        {{ busy ? 'Menyimpan…' : 'Tambah' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
