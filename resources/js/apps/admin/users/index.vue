<script setup>
import { computed, onMounted, ref } from 'vue';
import { Users, Ban, CircleCheck, Pencil } from 'lucide-vue-next';
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
const roleOptions = ref([]);
const query = ref('');

const editing = ref(null);
const savingUser = ref(false);

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'email', label: 'Email' },
    { key: 'roles', label: 'Role' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Bergabung' },
];

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return users.value;
    return users.value.filter((u) => (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q));
});

function statusTone(status) {
    switch (status) {
        case 'active':
            return 'success';
        case 'suspended':
            return 'danger';
        case 'pending':
            return 'warning';
        default:
            return 'neutral';
    }
}

async function load() {
    loading.value = true;
    try {
        const [userData, roleData] = await Promise.all([
            getJson('/api/admin/users'),
            getJson('/api/admin/roles'),
        ]);
        total.value = userData.total || 0;
        users.value = userData.users || [];
        roleOptions.value = roleData.roles || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

function openEditor(user) {
    editing.value = {
        id: user.id,
        name: user.name,
        status: user.status,
        roles: [...(user.roles || [])],
    };
}

function toggleRole(roleName) {
    const roles = editing.value.roles;
    const idx = roles.indexOf(roleName);
    if (idx >= 0) roles.splice(idx, 1);
    else roles.push(roleName);
}

async function saveUser() {
    if (!editing.value) return;
    savingUser.value = true;
    try {
        const res = await request(`/api/admin/users/${editing.value.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ status: editing.value.status, roles: editing.value.roles }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Pengguna diperbarui.', 'success');
            editing.value = null;
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal memperbarui.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        savingUser.value = false;
    }
}

async function toggleStatus(user) {
    const next = user.status === 'suspended' ? 'active' : 'suspended';
    try {
        const res = await request(`/api/admin/users/${user.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ status: next }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Status diperbarui.', 'success');
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal memperbarui.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Users" :description="`${total} akun terdaftar`" />

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="sm:w-72">
                <SearchInput v-model="query" placeholder="Cari nama atau email..." />
            </div>
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
                            <p v-if="row.is_super_admin" class="text-xs text-neutral-400 dark:text-neutral-500">Super Admin</p>
                        </div>
                    </div>
                </template>
                <template #cell-roles="{ value }">
                    <div class="flex flex-wrap gap-1">
                        <StatusBadge v-for="r in value" :key="r" :label="r" tone="info" />
                    </div>
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :label="value || 'unknown'" :tone="statusTone(value)" />
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value) }}</span>
                </template>

                <template #actions="{ row }">
                    <button
                        v-if="!row.is_super_admin"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                        :class="row.status === 'suspended'
                            ? 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40'
                            : 'border-red-300 text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/40'"
                        @click="toggleStatus(row)"
                    >
                        <Ban v-if="row.status !== 'suspended'" class="h-3.5 w-3.5" />
                        <CircleCheck v-else class="h-3.5 w-3.5" />
                        {{ row.status === 'suspended' ? 'Aktifkan' : 'Suspend' }}
                    </button>
                    <button
                        v-if="!row.is_super_admin"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="openEditor(row)"
                    >
                        <Pencil class="h-3.5 w-3.5" /> Role
                    </button>
                </template>
            </DataTable>
        </div>

        <!-- Modal ubah role & status -->
        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-950">
                <h3 class="text-lg font-semibold">Ubah {{ editing.name }}</h3>

                <label class="mt-4 block text-sm font-medium">Status</label>
                <select
                    v-model="editing.status"
                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <option value="active">Aktif</option>
                    <option value="suspended">Suspend</option>
                    <option value="pending">Pending</option>
                </select>

                <label class="mt-4 block text-sm font-medium">Role</label>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="r in roleOptions"
                        :key="r.name"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                        :class="editing.roles.includes(r.name)
                            ? 'border-neutral-900 bg-neutral-900 text-white dark:border-white dark:bg-white dark:text-neutral-950'
                            : 'border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                        @click="toggleRole(r.name)"
                    >
                        {{ r.name }}
                    </button>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="editing = null"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="savingUser"
                        class="rounded-lg border border-neutral-900 px-4 py-2 text-sm font-medium text-neutral-900 hover:bg-neutral-900 hover:text-white disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                        @click="saveUser"
                    >
                        {{ savingUser ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
