<script setup>
import { onMounted, ref } from 'vue';
import { ShieldCheck } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const roles = ref([]);

const columns = [
    { key: 'name', label: 'Role' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'permissions_count', label: 'Permission', align: 'right' },
    { key: 'users_count', label: 'Pengguna', align: 'right' },
];

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/roles');
        roles.value = data.roles || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Roles & Permissions" description="Kelola role dan hak akses pengguna." />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="roles" :loading="loading" empty-text="Belum ada role.">
                <template #cell-name="{ value }">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        <StatusBadge :label="value" :tone="value === 'super-admin' ? 'warning' : 'neutral'" />
                    </div>
                </template>
                <template #cell-permissions_count="{ value }">
                    <span class="text-sm font-medium">{{ value }}</span>
                </template>
                <template #cell-users_count="{ value }">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ value }}</span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
