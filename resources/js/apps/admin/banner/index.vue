<script setup>
import { onMounted, ref } from 'vue';
import { Info, Save } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const saving = ref(false);

const banner = ref({
    enabled: false,
    text: '',
    link_text: '',
    link_url: '',
});

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/information-banner-settings');
        banner.value = { ...banner.value, ...(data.banner || {}) };
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    try {
        const res = await request('/api/admin/information-banner-settings', {
            method: 'PUT',
            body: JSON.stringify(banner.value),
        });
        if (res.ok) {
            banner.value = { ...banner.value, ...(res.data?.banner || {}) };
            toast(res.data?.message || 'Banner informasi berhasil disimpan.', 'success');
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
        <PageHeader title="Banner Informasi" description="Atur bar informasi di atas header homepage. Aktifkan/nonaktifkan kapan saja.">
            <template #action>
                <AppButton :disabled="saving || loading" @click="save">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </AppButton>
            </template>
        </PageHeader>

        <div v-if="loading" class="space-y-4">
            <div class="h-40 animate-pulse rounded-xl border border-neutral-200 dark:border-neutral-800"></div>
            <div class="h-40 animate-pulse rounded-xl border border-neutral-200 dark:border-neutral-800"></div>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-5">
            <!-- Form -->
            <div class="lg:col-span-3">
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 p-5 dark:border-neutral-800">
                        <div>
                            <h2 class="font-semibold">Konten Banner</h2>
                            <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Teks bebas diubah sesuai kebutuhan.</p>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                            <input v-model="banner.enabled" type="checkbox" class="h-4 w-4 rounded border-neutral-300" />
                            Aktifkan banner
                        </label>
                    </div>

                    <div class="space-y-4 p-5">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium">Pesan informasi</span>
                            <textarea
                                v-model="banner.text"
                                rows="3"
                                placeholder="Contoh: Pendaftaran program kemitraan kampus sudah dibuka…"
                                class="w-full resize-y rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                            ></textarea>
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium">Teks tautan (opsional)</span>
                                <input
                                    v-model="banner.link_text"
                                    type="text"
                                    placeholder="Lihat selengkapnya"
                                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                                />
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium">URL tautan (opsional)</span>
                                <input
                                    v-model="banner.link_url"
                                    type="text"
                                    placeholder="/apps/u/register atau https://…"
                                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                                />
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pratinjau -->
            <div class="lg:col-span-2">
                <div class="sticky top-20">
                    <h3 class="mb-2 flex items-center gap-1.5 text-sm font-semibold">
                        <Info class="h-4 w-4" />
                        Pratinjau
                    </h3>
                    <div class="overflow-hidden rounded-xl border border-neutral-200 shadow-lg dark:border-neutral-800">
                        <div class="flex items-center justify-center gap-2 bg-neutral-900 px-6 py-4 text-sm text-white dark:bg-white dark:text-neutral-950">
                            <Info class="h-4 w-4 shrink-0 opacity-70" />
                            <p class="min-w-0">
                                {{ banner.text || 'Pesan informasi akan tampil di sini.' }}
                                <span v-if="banner.link_text && banner.link_url" class="ml-1 font-semibold underline underline-offset-4">
                                    {{ banner.link_text }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-neutral-400 dark:text-neutral-500">
                        Banner ini muncul sebagai bar tipis di atas header homepage. Pengunjung bisa menutupnya sementara.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
