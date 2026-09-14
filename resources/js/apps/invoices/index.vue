<script setup>
import { onMounted, ref } from 'vue';
import { ReceiptText, Printer, X } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import DataTable from '../../components/DataTable.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { getJson } from '../../utils/http';
import { formatCurrency, formatDate } from '../../utils/format';
import { toast } from '../../utils/toast';
import appName from '../../utils/appName';
import { useAuth } from '../../utils/auth';

const { currentUser } = useAuth();

const loading = ref(true);
const invoices = ref([]);

const selected = ref(null);
const detailLoading = ref(false);

const columns = [
    { key: 'invoice_number', label: 'Invoice' },
    { key: 'item', label: 'Item' },
    { key: 'created_at', label: 'Tanggal' },
    { key: 'total', label: 'Total', align: 'right' },
    { key: 'status', label: 'Status' },
];

function statusTone(status) {
    switch (status) {
        case 'paid':
            return 'success';
        case 'pending':
            return 'warning';
        case 'failed':
        case 'expired':
            return 'danger';
        default:
            return 'neutral';
    }
}

function statusLabel(status) {
    switch (status) {
        case 'paid':
            return 'Lunas';
        case 'pending':
            return 'Menunggu';
        case 'failed':
            return 'Gagal';
        case 'expired':
            return 'Kedaluwarsa';
        default:
            return status || '-';
    }
}

async function loadInvoices() {
    loading.value = true;
    try {
        const data = await getJson('/api/payments');
        invoices.value = data.invoices || [];
    } catch (e) {
        toast(e.message, 'error');
        invoices.value = [];
    } finally {
        loading.value = false;
    }
}

async function openDetail(uuid) {
    detailLoading.value = true;
    selected.value = null;
    try {
        const data = await getJson(`/api/payments/${uuid}`);
        selected.value = { ...(data.invoice || {}), user: data.user || null };
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    selected.value = null;
}

function printInvoice() {
    const inv = selected.value;
    if (!inv) return;

    const company = 'PT Kerja Tanpa Batas';
    const rows = [
        ['Item', inv.item || '-'],
        ['Invoice', inv.invoice_number || '-'],
        ['Tanggal', inv.created_at ? formatDate(inv.created_at, { withTime: true }) : '-'],
        ['Status', statusLabel(inv.status)],
        ['Metode', inv.method || '-'],
        ['Subtotal', formatCurrency(inv.subtotal)],
    ];
    if (inv.discount > 0) rows.push(['Diskon', `- ${formatCurrency(inv.discount)}`]);
    rows.push(['Biaya Layanan', formatCurrency(inv.fee)]);
    rows.push(['Total', formatCurrency(inv.total)]);
    if (inv.credits > 0) rows.push(['Koin Didapat', `${inv.credits} koin`]);

    const rowsHtml = rows
        .map(([k, v]) => `<tr><td style="padding:8px 0;color:#525252;">${k}</td><td style="padding:8px 0;text-align:right;font-weight:600;">${v}</td></tr>`)
        .join('');

    const html = `<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<title>Invoice ${inv.invoice_number}</title>
<style>
  body { font-family: Arial, sans-serif; color: #171717; padding: 40px; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #171717; padding-bottom: 20px; }
  h1 { font-size: 24px; margin: 0; }
  .muted { color: #737373; font-size: 13px; }
  table { width: 100%; border-collapse: collapse; margin-top: 24px; }
  td { border-bottom: 1px solid #e5e5e5; font-size: 14px; }
  .footer { margin-top: 32px; color: #737373; font-size: 12px; }
  @media print { body { padding: 0; } }
</style>
</head>
<body>
  <div class="head">
    <div>
      <h1>${company}</h1>
      <div class="muted">${appName}</div>
    </div>
    <div style="text-align:right;">
      <div style="font-weight:700;">INVOICE</div>
      <div class="muted">${inv.invoice_number || '-'}</div>
    </div>
  </div>

  <div style="margin-top:24px;font-size:14px;">
    <div class="muted">Ditagihkan kepada</div>
    <div style="font-weight:600;">${(inv.user?.name) || currentUser?.name || '-'}</div>
    <div class="muted">${(inv.user?.email) || currentUser?.email || '-'}</div>
  </div>

  <table>${rowsHtml}</table>

  <div class="footer">Terima kasih telah menggunakan ${appName}. Dokumen ini diterbitkan secara elektronik.</div>
</body>
</html>`;

    const win = window.open('', '_blank', 'width=720,height=900');
    if (!win) {
        toast('Pop-up diblokir browser. Izinkan pop-up untuk mencetak invoice.', 'error');
        return;
    }
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 300);
}

onMounted(loadInvoices);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Invoice" description="Riwayat faktur pembelian koin dan langganan kamu." />

        <DataTable
            :columns="columns"
            :rows="invoices"
            :loading="loading"
            empty-text="Belum ada invoice. Lakukan top-up koin atau berlangganan dulu."
        >
            <template #cell-invoice_number="{ value }">
                <span class="font-mono text-xs font-semibold">{{ value }}</span>
            </template>
            <template #cell-item="{ value }">
                <span>{{ value }}</span>
            </template>
            <template #cell-created_at="{ value }">
                <span class="whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(value, { withTime: true }) }}</span>
            </template>
            <template #cell-total="{ value }">
                <span class="font-semibold">{{ formatCurrency(value) }}</span>
            </template>
            <template #cell-status="{ value }">
                <StatusBadge :label="statusLabel(value)" :tone="statusTone(value)" />
            </template>
            <template #actions="{ row }">
                <AppButton variant="outline" size="sm" @click="openDetail(row.uuid)">
                    <ReceiptText class="h-4 w-4" />
                    Lihat
                </AppButton>
            </template>
        </DataTable>
    </div>

    <!-- Overlay detail invoice -->
    <Teleport to="body">
        <div v-if="selected || detailLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeDetail">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 text-neutral-900 shadow-xl dark:bg-neutral-900 dark:text-neutral-100">
                <div v-if="detailLoading" class="py-10 text-center text-sm text-neutral-500">Memuat invoice…</div>

                <template v-else>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Detail Invoice</h2>
                        <button type="button" class="cursor-pointer rounded-md p-1.5 hover:bg-neutral-100 dark:hover:bg-neutral-800" @click="closeDetail">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="mt-5 flex items-center justify-between border-b border-neutral-200 pb-4 dark:border-neutral-800">
                        <div>
                            <p class="text-sm font-medium">{{ selected.item }}</p>
                            <p class="mt-0.5 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ selected.invoice_number }}</p>
                        </div>
                        <StatusBadge :label="statusLabel(selected.status)" :tone="statusTone(selected.status)" />
                    </div>

                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-neutral-500 dark:text-neutral-400">Tanggal</dt>
                            <dd class="font-medium">{{ formatDate(selected.created_at, { withTime: true }) }}</dd>
                        </div>
                        <div v-if="selected.paid_at" class="flex justify-between">
                            <dt class="text-neutral-500 dark:text-neutral-400">Dibayar</dt>
                            <dd class="font-medium">{{ formatDate(selected.paid_at, { withTime: true }) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500 dark:text-neutral-400">Metode</dt>
                            <dd class="font-medium">{{ selected.method || '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500 dark:text-neutral-400">Subtotal</dt>
                            <dd class="font-medium">{{ formatCurrency(selected.subtotal) }}</dd>
                        </div>
                        <div v-if="selected.discount > 0" class="flex justify-between text-emerald-600 dark:text-emerald-400">
                            <dt>Diskon</dt>
                            <dd class="font-medium">- {{ formatCurrency(selected.discount) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500 dark:text-neutral-400">Biaya Layanan</dt>
                            <dd class="font-medium">{{ formatCurrency(selected.fee) }}</dd>
                        </div>
                        <div v-if="selected.credits > 0" class="flex justify-between">
                            <dt class="text-neutral-500 dark:text-neutral-400">Koin Didapat</dt>
                            <dd class="font-medium">{{ selected.credits }} koin</dd>
                        </div>
                        <div class="flex justify-between border-t border-neutral-200 pt-3 dark:border-neutral-800">
                            <dt class="font-semibold">Total</dt>
                            <dd class="text-lg font-bold">{{ formatCurrency(selected.total) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 flex gap-2">
                        <AppButton block @click="printInvoice">
                            <Printer class="h-4 w-4" />
                            Cetak / Simpan PDF
                        </AppButton>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>
