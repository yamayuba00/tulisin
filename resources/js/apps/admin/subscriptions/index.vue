<script setup>
import { onMounted, ref } from 'vue';
import { Save, BadgeCheck } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import { formatCurrency } from '../../../utils/format';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const monthlyPrice = ref(30000);

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/subscription-settings');
        monthlyPrice.value = Number(data.monthly_price || 30000);
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});

async function save() {
    if (Number(monthlyPrice.value) < 1000) {
        toast('Harga langganan minimal Rp 1.000.', 'error');
        return;
    }

    saving.value = true;
    try {
        const res = await request('/api/admin/subscription-settings', {
            method: 'PUT',
            body: JSON.stringify({ monthly_price: Number(monthlyPrice.value) }),
        });
        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal menyimpan harga.', 'error');
            return;
        }
        monthlyPrice.value = Number(res.data?.monthly_price || monthlyPrice.value);
        toast(res.data?.message || 'Harga langganan berhasil disimpan.', 'success');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Langganan" description="Atur harga langganan bulanan yang wajib dibayar pengguna agar bisa mengunduh dan memakai fitur AI.">
            <template #action>
                <AppButton :disabled="saving || loading" @click="save">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </AppButton>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                    <BadgeCheck class="h-4 w-4" />
                    Harga Langganan Bulanan
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">Rp</span>
                    <input
                        v-model.number="monthlyPrice"
                        type="number"
                        min="1000"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-lg font-semibold outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                    />
                </div>
                <p class="mt-2 text-xs text-neutral-400 dark:text-neutral-500">
                    Saat ini: {{ formatCurrency(monthlyPrice) }} / 30 hari
                </p>
            </div>

            <div class="rounded-xl border border-neutral-200 p-5 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-300 lg:col-span-2">
                <p class="font-medium text-neutral-800 dark:text-neutral-200">Aturan yang diterapkan</p>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-neutral-500 dark:text-neutral-400">
                    <li>Langganan aktif selama 30 hari sejak pembayaran (periode subscribe, bukan tanggal kalender).</li>
                    <li>Tanpa langganan aktif, pengguna hanya bisa menulis project; download PDF, Agent Canvas, Turnitin, dan Plagiarism terkunci.</li>
                    <li>Email pengingat dikirim otomatis 5 hari sebelum masa langganan habis.</li>
                </ul>
            </div>
        </div>
    </div>
</template>
