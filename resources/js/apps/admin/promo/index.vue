<script setup>
import { onMounted, ref } from 'vue';
import { Megaphone, Save, Sparkles } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import appName from '../../../utils/appName';

const loading = ref(true);
const saving = ref(false);

const promo = ref({
    enabled: false,
    badge: 'Promo Hari Ini',
    highlight: '',
    subtitle: '',
    title: '',
    description: '',
    cta_label: 'Klaim Promo',
    cta_link: '/apps/u/topup',
    cta_secondary_label: 'Nanti saja',
});

const fields = [
    { key: 'badge', label: 'Badge (kiri atas banner)', placeholder: 'Promo Hari Ini' },
    { key: 'highlight', label: 'Sorotan utama (teks besar)', placeholder: 'Diskon 20%' },
    { key: 'subtitle', label: 'Subjudul banner', placeholder: `Top-up Koin ${appName}` },
    { key: 'title', label: 'Judul konten', placeholder: 'Hemat 20% untuk semua fitur AI' },
    { key: 'description', label: 'Deskripsi', placeholder: 'Jelaskan penawaran kamu…', textarea: true },
    { key: 'cta_label', label: 'Teks tombol utama', placeholder: 'Klaim Promo' },
    { key: 'cta_link', label: 'Link tombol utama', placeholder: '/apps/u/topup atau https://…' },
    { key: 'cta_secondary_label', label: 'Teks tombol sekunder', placeholder: 'Nanti saja' },
];

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/promo-settings');
        promo.value = { ...promo.value, ...(data.promo || {}) };
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    try {
        const res = await request('/api/admin/promo-settings', {
            method: 'PUT',
            body: JSON.stringify(promo.value),
        });
        if (res.ok) {
            promo.value = { ...promo.value, ...(res.data?.promo || {}) };
            toast(res.data?.message || 'Banner promo berhasil disimpan.', 'success');
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
        <PageHeader title="Banner Promo" description="Atur isi popup promo harian yang muncul di aplikasi. Aktifkan/nonaktifkan kapan saja.">
            <template #action>
                <AppButton :disabled="saving || loading" @click="save">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </AppButton>
            </template>
        </PageHeader>

        <div v-if="loading" class="space-y-4">
            <div class="h-40 animate-pulse rounded-xl border border-neutral-200 dark:border-neutral-800"></div>
            <div class="h-64 animate-pulse rounded-xl border border-neutral-200 dark:border-neutral-800"></div>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-5">
            <!-- Form -->
            <div class="lg:col-span-3">
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 p-5 dark:border-neutral-800">
                        <div>
                            <h2 class="font-semibold">Konten Promo</h2>
                            <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Semua teks bebas diubah sesuai kebutuhan.</p>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                            <input v-model="promo.enabled" type="checkbox" class="h-4 w-4 rounded border-neutral-300" />
                            Aktifkan popup
                        </label>
                    </div>

                    <div class="space-y-4 p-5">
                        <label v-for="f in fields" :key="f.key" class="block">
                            <span class="mb-1.5 block text-sm font-medium">{{ f.label }}</span>
                            <textarea
                                v-if="f.textarea"
                                v-model="promo[f.key]"
                                rows="3"
                                :placeholder="f.placeholder"
                                class="w-full resize-y rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                            ></textarea>
                            <input
                                v-else
                                v-model="promo[f.key]"
                                type="text"
                                :placeholder="f.placeholder"
                                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                            />
                        </label>
                    </div>
                </div>
            </div>

            <!-- Pratinjau -->
            <div class="lg:col-span-2">
                <div class="sticky top-20">
                    <h3 class="mb-2 flex items-center gap-1.5 text-sm font-semibold">
                        <Megaphone class="h-4 w-4" />
                        Pratinjau
                    </h3>
                    <div class="overflow-hidden rounded-2xl border border-neutral-200 shadow-lg dark:border-neutral-800">
                        <div class="flex items-center justify-center bg-neutral-900 px-6 py-8">
                            <div class="text-center text-white">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold">
                                    <Sparkles class="h-3.5 w-3.5" />
                                    {{ promo.badge || 'Promo Hari Ini' }}
                                </span>
                                <p v-if="promo.highlight" class="mt-4 text-2xl font-bold leading-tight">{{ promo.highlight }}</p>
                                <p v-if="promo.subtitle" class="mt-1 text-sm text-white/70">{{ promo.subtitle }}</p>
                            </div>
                        </div>
                        <div class="bg-white px-6 py-5 dark:bg-neutral-900">
                            <h2 v-if="promo.title" class="text-lg font-semibold">{{ promo.title }}</h2>
                            <p v-if="promo.description" class="mt-1.5 text-sm leading-relaxed text-neutral-500 dark:text-neutral-400">{{ promo.description }}</p>
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-lg bg-neutral-900 px-3 py-2 text-xs font-semibold text-white dark:bg-white dark:text-neutral-950">
                                    {{ promo.cta_label || 'Lihat' }}
                                </span>
                                <span class="inline-flex items-center rounded-lg px-2 py-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                    {{ promo.cta_secondary_label || 'Nanti saja' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
