<script setup>
import { onMounted, ref } from 'vue';
import { Coins, Save } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const saving = ref(false);

// Tarif aktif (hasil GET) — nilai default diisi dari balasan backend.
const pricing = ref({});

const fields = [
    {
        key: 'ai_generate',
        label: 'AI Generate (Copilot / Canvas)',
        hint: 'Koin per sekali generate konten AI.',
        min: 0,
    },
    {
        key: 'ai_plagiarism',
        label: 'AI Plagiarism Check',
        hint: 'Koin per sekali pengecekan plagiarisme.',
        min: 0,
    },
    {
        key: 'ai_turnitin',
        label: 'AI Turnitin Optimizer',
        hint: 'Koin per sekali optimasi lolos deteksi AI.',
        min: 0,
    },
    {
        key: 'template',
        label: 'Penggunaan Template',
        hint: 'Koin per project yang dibuat dari template.',
        min: 0,
    },
    {
        key: 'font',
        label: 'Upload Font',
        hint: 'Koin per file font yang diunggah.',
        min: 0,
    },
    {
        key: 'image_package_size',
        label: 'Jumlah Gambar per Paket',
        hint: 'Banyak gambar dalam satu paket kuota file.',
        min: 1,
    },
    {
        key: 'image_package_credits',
        label: 'Koin per Paket Gambar',
        hint: 'Total koin untuk satu paket gambar di atas.',
        min: 0,
    },
    {
        key: 'download_base',
        label: 'Biaya Dasar Download',
        hint: 'Koin dasar saat mengunduh dokumen.',
        min: 0,
    },
    {
        key: 'download_per_10_pages',
        label: 'Tambahan Download per 10 Halaman',
        hint: 'Koin tambahan setiap kelipatan 10 halaman.',
        min: 0,
    },
];

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/credit-settings');
        pricing.value = { ...(data.pricing || {}) };
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    try {
        const res = await request('/api/admin/credit-settings', {
            method: 'PUT',
            body: JSON.stringify(pricing.value),
        });
        if (res.ok) {
            pricing.value = { ...(res.data?.pricing || pricing.value) };
            toast(res.data?.message || 'Pengaturan koin berhasil disimpan.', 'success');
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal menyimpan.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Pengaturan Koin" description="Atur tarif koin untuk seluruh fitur platform.">
            <template #action>
                <AppButton :disabled="saving || loading" @click="save">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </AppButton>
            </template>
        </PageHeader>

        <div v-if="loading" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="i in 9" :key="i" class="h-24 animate-pulse rounded-lg border border-neutral-200 dark:border-neutral-800"></div>
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label
                v-for="f in fields"
                :key="f.key"
                class="flex flex-col gap-1.5 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800"
            >
                <span class="text-sm font-medium">{{ f.label }}</span>
                <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ f.hint }}</span>
                <div class="mt-1 flex items-center gap-2">
                    <Coins class="h-4 w-4 shrink-0 text-neutral-400 dark:text-neutral-600" />
                    <input
                        v-model.number="pricing[f.key]"
                        type="number"
                        :min="f.min"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                    />
                </div>
            </label>
        </div>
    </div>
</template>
