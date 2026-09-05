<script setup>
import { computed, onMounted, ref } from 'vue';
import { Plus, Save, Trash2, Pencil, X, Ticket } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { formatDate, formatCurrency } from '../../../utils/format';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const coupons = ref([]);
const types = ref({});

const showForm = ref(false);
const editingId = ref(null);
const form = ref({
    code: '',
    type: 'bonus_percent',
    value: 0,
    max_uses: null,
    expires_at: '',
    is_active: true,
});

const columns = [
    { key: 'code', label: 'Kode' },
    { key: 'type_label', label: 'Tipe' },
    { key: 'value', label: 'Nilai' },
    { key: 'usage', label: 'Pemakaian' },
    { key: 'expires_at', label: 'Kadaluarsa' },
    { key: 'is_active', label: 'Status' },
];

function valueLabel(row) {
    if (!row) return '-';
    const val = Number(row.value || 0);
    if (row.type === 'bonus_percent' || row.type === 'discount_percent') return `${val}%`;
    if (row.type === 'bonus_fixed') return `${val} koin`;
    return formatCurrency(val);
}

async function load() {
    loading.value = true;
    try {
        const data = await getJson('/api/admin/coupons');
        coupons.value = data.coupons || [];
        types.value = data.types || {};
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    form.value = { code: '', type: 'bonus_percent', value: 0, max_uses: null, expires_at: '', is_active: true };
    showForm.value = true;
}

function openEdit(row) {
    editingId.value = row.id;
    form.value = {
        code: row.code,
        type: row.type,
        value: Number(row.value),
        max_uses: row.max_uses ?? null,
        expires_at: row.expires_at ? row.expires_at.slice(0, 16) : '',
        is_active: !!row.is_active,
    };
    showForm.value = true;
}

function cancelForm() {
    showForm.value = false;
    editingId.value = null;
}

async function save() {
    if (!form.value.code.trim()) {
        toast('Kode promo wajib diisi.', 'error');
        return;
    }
    if (Number(form.value.value) < 0) {
        toast('Nilai promo tidak boleh negatif.', 'error');
        return;
    }

    saving.value = true;
    try {
        const payload = {
            code: form.value.code,
            type: form.value.type,
            value: Number(form.value.value),
            max_uses: form.value.max_uses ? Number(form.value.max_uses) : null,
            expires_at: form.value.expires_at || null,
            is_active: form.value.is_active,
        };

        const url = editingId.value ? `/api/admin/coupons/${editingId.value}` : '/api/admin/coupons';
        const method = editingId.value ? 'PUT' : 'POST';
        const res = await request(url, { method, body: JSON.stringify(payload) });

        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal menyimpan promo.', 'error');
            return;
        }

        toast(res.data?.message || 'Promo berhasil disimpan.', 'success');
        showForm.value = false;
        editingId.value = null;
        await load();
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}

async function remove(row) {
    if (!window.confirm(`Hapus promo "${row.code}"?`)) return;
    try {
        const res = await request(`/api/admin/coupons/${row.id}`, { method: 'DELETE' });
        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal menghapus promo.', 'error');
            return;
        }
        toast(res.data?.message || 'Promo dihapus.', 'success');
        await load();
    } catch (e) {
        toast(e.message, 'error');
    }
}

const isEditing = computed(() => editingId.value !== null);

onMounted(load);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Promo" description="Kelola promo spesial untuk bonus koin atau diskon topup pengguna.">
            <template #action>
                <AppButton @click="openCreate">
                    <Plus class="h-4 w-4" />
                    Tambah Promo
                </AppButton>
            </template>
        </PageHeader>

        <!-- Form tambah/edit -->
        <div v-if="showForm" class="mb-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold">{{ isEditing ? 'Edit Promo' : 'Tambah Promo' }}</h2>
                <button type="button" class="cursor-pointer text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200" @click="cancelForm">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="flex flex-col gap-1.5 text-sm">
                    <span class="font-medium">Kode Promo</span>
                    <input
                        v-model="form.code"
                        type="text"
                        placeholder="SKRIPSI10"
                        class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                    />
                </label>

                <label class="flex flex-col gap-1.5 text-sm">
                    <span class="font-medium">Tipe</span>
                    <select
                        v-model="form.type"
                        class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                    >
                        <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>

                <label class="flex flex-col gap-1.5 text-sm">
                    <span class="font-medium">Nilai</span>
                    <input
                        v-model.number="form.value"
                        type="number"
                        min="0"
                        step="1"
                        class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                    />
                </label>

                <label class="flex flex-col gap-1.5 text-sm">
                    <span class="font-medium">Batas Pemakaian (opsional)</span>
                    <input
                        v-model.number="form.max_uses"
                        type="number"
                        min="1"
                        placeholder="Kosongkan untuk tanpa batas"
                        class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                    />
                </label>

                <label class="flex flex-col gap-1.5 text-sm">
                    <span class="font-medium">Kadaluarsa (opsional)</span>
                    <input
                        v-model="form.expires_at"
                        type="datetime-local"
                        class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                    />
                </label>

                <label class="flex items-center gap-2 pt-6 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4" />
                    <span class="font-medium">Aktif</span>
                </label>
            </div>

            <div class="mt-5 flex gap-2">
                <AppButton :disabled="saving" @click="save">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </AppButton>
                <AppButton variant="outline" @click="cancelForm">Batal</AppButton>
            </div>
        </div>

        <DataTable :columns="columns" :rows="coupons" :loading="loading" empty-text="Belum ada promo.">
            <template #cell-code="{ value }">
                <span class="inline-flex items-center gap-1.5 font-medium">
                    <Ticket class="h-4 w-4 text-neutral-400" />
                    {{ value }}
                </span>
            </template>
            <template #cell-value="{ row }">
                <span class="font-medium">{{ valueLabel(row) }}</span>
            </template>
            <template #cell-usage="{ row }">
                <span class="text-neutral-600 dark:text-neutral-300">
                    {{ row.used_count }} / {{ row.max_uses ?? '∞' }}
                </span>
            </template>
            <template #cell-expires_at="{ value }">
                <span>{{ value ? formatDate(value, { withTime: true }) : '-' }}</span>
            </template>
            <template #cell-is_active="{ value }">
                <StatusBadge :label="value ? 'Aktif' : 'Nonaktif'" :tone="value ? 'success' : 'neutral'" />
            </template>
            <template #actions="{ row }">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="openEdit(row)"
                >
                    <Pencil class="h-3.5 w-3.5" /> Edit
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950/40"
                    @click="remove(row)"
                >
                    <Trash2 class="h-3.5 w-3.5" /> Hapus
                </button>
            </template>
        </DataTable>
    </div>
</template>
