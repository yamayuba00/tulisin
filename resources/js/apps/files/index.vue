<script setup>
import { ref, onMounted } from 'vue';
import { Upload, Trash2, Image as ImageIcon, Coins } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import AppButton from '../../components/AppButton.vue';
import {
    listImages,
    addImage,
    removeImage,
    getImageUsage,
    recordImageUse,
} from '../../utils/imageLibrary';
import { request } from '../../utils/http';
import { toast } from '../../utils/toast';
import { creditPricing, imageCostPerItem, loadCreditPricing } from '../../utils/creditPricing';

const MAX_IMAGE_SIZE = 2 * 1024 * 1024; // 2 MB

const images = ref([]);
const usage = ref({ used: 0, creditsSpent: 0 });
const uploading = ref(false);
const fileInput = ref(null);
const deleteTarget = ref(null);

async function refresh() {
    images.value = await listImages();
    usage.value = getImageUsage();
}

onMounted(() => {
    loadCreditPricing();
    refresh();
});

function openUpload() {
    fileInput.value?.click();
}

async function onFileChange(e) {
    const files = Array.from(e.target.files || []).filter((f) => f.type.startsWith('image/'));
    e.target.value = '';
    if (!files.length) return;

    const oversized = files.filter((f) => f.size > MAX_IMAGE_SIZE);
    if (oversized.length) {
        toast(`Gagal mengunggah ${oversized.length} gambar melebihi 2 MB.`, 'error');
        return;
    }

    const credits = files.length * imageCostPerItem();
    uploading.value = true;
    try {
        const res = await request('/api/wallet/spend', {
            method: 'POST',
            body: JSON.stringify({ credits, reason: 'image_upload' }),
        });
        if (!res.ok) {
            toast(res.data?.error || 'Saldo koin tidak mencukupi.', 'error');
            return;
        }
        for (const file of files) {
            await addImage(file);
        }
        recordImageUse(files.length);
        await refresh();
    } catch (err) {
        toast(err?.message || 'Gagal mengunggah gambar.', 'error');
    } finally {
        uploading.value = false;
    }
}

function remove(id) {
    deleteTarget.value = id;
}

async function confirmDelete() {
    if (!deleteTarget.value) return;
    const id = deleteTarget.value;
    deleteTarget.value = null;
    try {
        await removeImage(id);
        await refresh();
    } catch (err) {
        toast(err?.message || 'Gagal menghapus gambar.', 'error');
    }
}

function cancelDelete() {
    deleteTarget.value = null;
}

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="File Manager" description="Kelola gambar yang sudah kamu unggah.">
            <template #action>
                <AppButton :disabled="uploading" @click="openUpload">
                    <Upload class="h-4 w-4" />
                    Unggah Gambar
                </AppButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
            <span class="inline-flex items-center gap-1.5">
                <Coins class="h-4 w-4" />
                {{ creditPricing.image_package_size }} gambar = {{ creditPricing.image_package_credits }} koin
                ({{ imageCostPerItem() }} koin/gambar)
            </span>
            <span class="text-neutral-300 dark:text-neutral-600">•</span>
            <span>Maks. 2 MB/gambar</span>
            <span class="text-neutral-300 dark:text-neutral-600">•</span>
            <span>Terpakai: {{ usage.used }} gambar · {{ usage.creditsSpent }} koin</span>
        </div>

        <div v-if="uploading" class="flex items-center justify-center py-16 text-sm text-neutral-500 dark:text-neutral-400">
            Mengunggah…
        </div>

        <EmptyState
            v-else-if="images.length === 0"
            title="Belum ada file"
            description="Unggah gambar untuk mulai mengelola koleksi file kamu."
        >
            <template #icon>
                <ImageIcon class="h-6 w-6" />
            </template>
            <template #action>
                <AppButton variant="outline" @click="openUpload">Unggah Gambar Pertama</AppButton>
            </template>
        </EmptyState>

        <div v-else class="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <div
                v-for="item in images"
                :key="item.id"
                class="group relative rounded-lg border border-neutral-200 p-3 dark:border-neutral-800"
            >
                <div class="flex h-28 items-center justify-center overflow-hidden rounded-md bg-neutral-50 dark:bg-neutral-900">
                    <img :src="item.url" :alt="item.name" class="h-full w-full object-contain" draggable="false" />
                </div>
                <p class="mt-2 truncate text-xs font-medium">{{ item.name }}</p>
                <p class="text-[11px] text-neutral-400 dark:text-neutral-500">{{ formatSize(item.size) }}</p>
                <button
                    type="button"
                    class="absolute right-2 top-2 inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md bg-white/90 text-red-600 opacity-0 shadow-sm transition-opacity hover:bg-red-50 group-hover:opacity-100 dark:bg-neutral-900/90 dark:hover:bg-red-950/40"
                    title="Hapus"
                    @click="remove(item.id)"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>
        </div>

        <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onFileChange" />

        <!-- Konfirmasi hapus gambar -->
        <div v-if="deleteTarget" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="cancelDelete"></div>
            <div class="relative z-10 w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600 dark:bg-red-950/60 dark:text-red-400">
                        <Trash2 class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Hapus gambar ini?</h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Gambar akan dihapus dari file manager dan cloud storage. Koin yang sudah terpakai tidak dikembalikan.
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="cancelDelete"
                    >
                        Batal
                    </button>
                    <AppButton @click="confirmDelete">
                        <Trash2 class="h-4 w-4" />
                        Hapus
                    </AppButton>
                </div>
            </div>
        </div>
    </div>
</template>
