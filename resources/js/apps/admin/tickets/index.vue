<script setup>
import { onMounted, ref } from 'vue';
import { MessageSquare, CheckCircle2, RotateCcw } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { formatDate } from '../../../utils/format';

const loading = ref(true);
const total = ref(0);
const tickets = ref([]);
const users = ref([]);
const updating = ref(null);

const columns = [
    { key: 'subject', label: 'Subjek' },
    { key: 'user_name', label: 'Pengguna' },
    { key: 'priority', label: 'Prioritas' },
    { key: 'status', label: 'Status' },
    { key: 'assignee_name', label: 'Ditugaskan' },
    { key: 'created_at', label: 'Tanggal' },
];

function statusMeta(status) {
    switch (status) {
        case 'closed':
            return { label: 'Selesai', tone: 'success' };
        case 'pending':
            return { label: 'Pending', tone: 'info' };
        default:
            return { label: 'Terbuka', tone: 'warning' };
    }
}

function priorityTone(priority) {
    switch (priority) {
        case 'high':
            return 'danger';
        case 'low':
            return 'info';
        default:
            return 'neutral';
    }
}

async function load() {
    loading.value = true;
    try {
        const [ticketData, userData] = await Promise.all([
            getJson('/api/admin/tickets'),
            getJson('/api/admin/users'),
        ]);
        total.value = ticketData.total || 0;
        tickets.value = ticketData.tickets || [];
        users.value = userData.users || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

async function toggleStatus(ticket) {
    updating.value = ticket.id;
    const next = ticket.status === 'closed' ? 'open' : 'closed';
    try {
        const res = await request(`/api/admin/tickets/${ticket.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ status: next }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Tiket diperbarui.', 'success');
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal memperbarui.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        updating.value = null;
    }
}

async function assign(ticket, assignedTo) {
    try {
        const res = await request(`/api/admin/tickets/${ticket.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ assigned_to: assignedTo ? Number(assignedTo) : null }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Penugasan diperbarui.', 'success');
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
        <PageHeader title="Tickets" :description="`${total} tiket dukungan`" />

        <div class="mt-6">
            <DataTable :columns="columns" :rows="tickets" :loading="loading" empty-text="Belum ada tiket.">
                <template #cell-subject="{ value }">
                    <span class="inline-flex items-center gap-2 font-medium">
                        <MessageSquare class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        {{ value }}
                    </span>
                </template>
                <template #cell-priority="{ value }">
                    <StatusBadge :label="value" :tone="priorityTone(value)" />
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :label="statusMeta(value).label" :tone="statusMeta(value).tone" />
                </template>
                <template #cell-assignee_name="{ value }">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ value || '-' }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
                </template>

                <template #actions="{ row }">
                    <select
                        :value="row.assigned_to || ''"
                        class="rounded-lg border border-neutral-300 bg-neutral-50 px-2 py-1.5 text-xs outline-none focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-900"
                        @change="assign(row, $event.target.value)"
                    >
                        <option value="">Belum ditugaskan</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <button
                        type="button"
                        :disabled="updating === row.id"
                        class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors disabled:opacity-50"
                        :class="row.status === 'closed'
                            ? 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-950/40'
                            : 'border-neutral-300 text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                        @click="toggleStatus(row)"
                    >
                        <RotateCcw v-if="row.status === 'closed'" class="h-3.5 w-3.5" />
                        <CheckCircle2 v-else class="h-3.5 w-3.5" />
                        {{ row.status === 'closed' ? 'Buka' : 'Selesai' }}
                    </button>
                </template>
            </DataTable>
        </div>
    </div>
</template>
