<script setup>
import { onMounted, ref } from 'vue';
import { Save, Plus, Trash2, ArrowUp, ArrowDown, Upload } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const uploading = ref(false);
const usingDefaults = ref(false);
const items = ref([]);
const fileInput = ref(null);

function triggerUpload() {
    fileInput.value?.click();
}

async function onFileChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    await upload(file);
}

async function upload(file) {
    uploading.value = true;
    try {
        const form = new FormData();
        form.append('file', file);
        form.append('alt', '');
        form.append('caption', '');

        const res = await request('/api/admin/showcase-settings', {
            method: 'POST',
            body: form,
        });

        if (res.ok) {
            items.value.push(res.data);
            usingDefaults.value = false;
            toast('Gambar berhasil diunggah.', 'success');
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal mengunggah gambar.', 'error');
        }
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        uploading.value = false;
    }
}

async function remove(index) {
    const item = items.value[index];
    if (!item?.id) return;

    try {
        const res = await request(`/api/admin/showcase-settings/${item.id}`, { method: 'DELETE' });
        if (res.ok) {
            items.value.splice(index, 1);
            usingDefaults.value = !!res.data?.using_defaults;
            toast(res.data?.message || 'Gambar dihapus.', 'success');
        } else {
            toast(res.data?.error || 'Gagal menghapus gambar.', 'error');
        }
    } catch (err) {
        toast(err.message, 'error');
    }
}

function move(index, dir) {
    const next = index + dir;
    if (next < 0 || next >= items.value.length) return;
    const arr = items.value;
    [arr[index], arr[next]] = [arr[next], arr[index]];
}

async function save() {
    saving.value = true;
    try {
        const res = await request('/api/admin/showcase-settings', {
            method: 'PUT',
            body: JSON.stringify({
                screenshots: items.value.map((it) => ({
                    id: it.id,
                    alt: it.alt,
                    caption: it.caption,
                })),
            }),
        });

        if (res.ok) {
            items.value = Array.isArray(res.data?.screenshots) ? res.data.screenshots : items.value;
            usingDefaults.value = !!res.data?.using_defaults;
            toast(res.data?.message || 'Tampilan homepage berhasil disimpan.', 'success');
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal menyimpan.', 'error');
        }
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/showcase-settings');
        items.value = Array.isArray(data.screenshots) ? data.screenshots : [];
        usingDefaults.value = !!data.using_defaults;
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Tampilan Homepage" description="Kelola gambar pada section 'Tampilan' di halaman utama. Gambar diunggah dan disimpan ke object storage (S3).">
            <template #action>
                <AppButton :disabled="saving || loading" @click="save">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </AppButton>
            </template>
        </PageHeader>

        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFileChange" />

        <div v-if="loading" class="space-y-4">
            <div class="h-40 animate-pulse rounded-xl border border-neutral-200 dark:border-neutral-800"></div>
            <div class="h-40 animate-pulse rounded-xl border border-neutral-200 dark:border-neutral-800"></div>
        </div>

        <div v-else class="space-y-4">
            <div
                v-if="usingDefaults"
                class="rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-4 text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400"
            >
                Belum ada gambar yang diunggah. Homepage saat ini memakai gambar bawaan — unggah gambar untuk menggantikannya.
            </div>

            <div
                v-for="(shot, i) in items"
                :key="shot.id || i"
                class="rounded-xl border border-neutral-200 dark:border-neutral-800"
            >
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 p-4 dark:border-neutral-800">
                    <span class="text-sm font-semibold text-neutral-500 dark:text-neutral-400">Gambar {{ i + 1 }}</span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-md p-1.5 text-neutral-500 hover:bg-neutral-100 disabled:opacity-30 dark:hover:bg-neutral-800"
                            :disabled="i === 0"
                            title="Naik"
                            @click="move(i, -1)"
                        >
                            <ArrowUp class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-md p-1.5 text-neutral-500 hover:bg-neutral-100 disabled:opacity-30 dark:hover:bg-neutral-800"
                            :disabled="i === items.length - 1"
                            title="Turun"
                            @click="move(i, 1)"
                        >
                            <ArrowDown class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-1.5 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40"
                            @click="remove(i)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Hapus
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 p-4 sm:grid-cols-[160px_1fr]">
                    <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900">
                        <img :src="shot.src" :alt="shot.alt" class="h-full w-full object-cover" />
                    </div>

                    <div class="space-y-3">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium">Teks alternatif</span>
                            <input
                                v-model="shot.alt"
                                type="text"
                                placeholder="Editor blok Tulissin"
                                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium">Keterangan</span>
                            <input
                                v-model="shot.caption"
                                type="text"
                                placeholder="Susun bab dengan blok"
                                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                            />
                        </label>
                    </div>
                </div>
            </div>

            <AppButton variant="outline" block :disabled="uploading" @click="triggerUpload">
                <Upload v-if="!uploading" class="h-4 w-4" />
                <span v-else class="inline-flex items-center gap-2">
                    <Plus class="h-4 w-4 animate-spin" />
                    Mengunggah…
                </span>
                {{ uploading ? '' : 'Tambah Gambar' }}
            </AppButton>
        </div>
    </div>
</template>
