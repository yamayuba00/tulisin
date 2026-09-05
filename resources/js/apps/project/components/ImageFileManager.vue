<script setup>
import { ref, onMounted } from 'vue';
import { X, Upload, Image as ImageIcon, Coins } from 'lucide-vue-next';
import { listImages, addImage, getImageUsage, recordImageUse } from '../../../utils/imageLibrary';
import { request } from '../../../utils/http';
import { toast } from '../../../utils/toast';
import { creditPricing, imageCostPerItem } from '../../../utils/creditPricing';

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['select', 'close']);

const images = ref([]);
const uploading = ref(false);
const usage = ref({ used: 0, creditsSpent: 0 });
const fileInput = ref(null);

async function refresh() {
    images.value = await listImages();
    usage.value = getImageUsage();
}

onMounted(refresh);

function openUpload() {
    fileInput.value?.click();
}

async function onFileChange(e) {
    const files = Array.from(e.target.files || []).filter((f) => f.type.startsWith('image/'));
    e.target.value = '';
    if (!files.length) return;
    uploading.value = true;
    try {
        const credits = files.length * imageCostPerItem();
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

function pick(item) {
    emit('select', item);
}

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')"></div>

        <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start justify-between border-b border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <div>
                    <h2 class="text-base font-semibold">Manajer Gambar</h2>
                    <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                        Pilih gambar yang sudah diunggah, atau unggah yang baru untuk langsung dipakai.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    aria-label="Tutup"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Info kuota/kredit (struktur siap untuk enforcement nanti) -->
            <div class="flex items-center gap-3 border-b border-neutral-200 bg-neutral-50 px-5 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
                <span class="inline-flex items-center gap-1.5">
                    <Coins class="h-4 w-4" />
                    {{ creditPricing.image_package_size }} gambar = {{ creditPricing.image_package_credits }} koin
                    ({{ imageCostPerItem() }} koin/gambar)
                </span>
                <span class="text-neutral-300 dark:text-neutral-600">•</span>
                <span>Terpakai: {{ usage.used }} gambar · {{ usage.creditsSpent }} koin</span>
            </div>

            <div class="flex-1 overflow-auto p-4">
                <div v-if="uploading" class="flex items-center justify-center py-16 text-sm text-neutral-500 dark:text-neutral-400">
                    Mengunggah…
                </div>

                <div v-else-if="images.length === 0" class="flex flex-col items-center justify-center py-16 text-center text-neutral-400 dark:text-neutral-500">
                    <ImageIcon class="h-10 w-10" />
                    <p class="mt-3 text-sm">Belum ada gambar.</p>
                    <p class="mt-1 text-xs">Unggah gambar untuk mulai mengelola koleksi.</p>
                </div>

                <div v-else class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                    <button
                        v-for="item in images"
                        :key="item.id"
                        type="button"
                        class="group relative cursor-pointer overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50 p-2 text-left transition-colors hover:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-neutral-600"
                        @click="pick(item)"
                    >
                        <div class="flex h-24 items-center justify-center overflow-hidden rounded-md bg-white dark:bg-neutral-950">
                            <img :src="item.url" :alt="item.name" class="h-full w-full object-contain" draggable="false" />
                        </div>
                        <p class="mt-2 truncate text-xs font-medium text-neutral-700 dark:text-neutral-300">{{ item.name }}</p>
                        <p class="text-[11px] text-neutral-400 dark:text-neutral-500">{{ formatSize(item.size) }}</p>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <span class="text-xs text-neutral-400 dark:text-neutral-500">
                    {{ images.length }} gambar tersimpan
                </span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="emit('close')"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="uploading"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-900 px-4 py-2 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-900 hover:text-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950"
                        @click="openUpload"
                    >
                        <Upload class="h-4 w-4" />
                        Unggah Gambar
                    </button>
                </div>
            </div>

            <!-- Input file tersembunyi milik manajer -->
            <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onFileChange" />
        </div>
    </div>
</template>
