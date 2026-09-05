<script setup>
import { ref, onMounted } from 'vue';
import { Upload, Trash2, Type, Coins } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import AppButton from '../../components/AppButton.vue';
import { listCustomFonts, addCustomFont, removeCustomFont, registerAllCustomFonts } from '../../utils/fontManager';
import { request } from '../../utils/http';
import { toast } from '../../utils/toast';
import { creditPricing, loadCreditPricing } from '../../utils/creditPricing';

const fonts = ref([]);
const uploading = ref(false);
const fileInput = ref(null);

function refresh() {
    fonts.value = listCustomFonts();
}

onMounted(() => {
    registerAllCustomFonts();
    loadCreditPricing();
    refresh();
});

function openUpload() {
    fileInput.value?.click();
}

async function onFileChange(e) {
    const files = Array.from(e.target.files || []);
    e.target.value = '';
    if (!files.length) return;

    const credits = files.length * creditPricing.value.font;
    uploading.value = true;
    try {
        const res = await request('/api/wallet/spend', {
            method: 'POST',
            body: JSON.stringify({ credits, reason: 'font_upload' }),
        });
        if (!res.ok) {
            toast(res.data?.error || 'Saldo koin tidak mencukupi.', 'error');
            return;
        }
        for (const file of files) {
            await addCustomFont(file);
        }
        refresh();
    } finally {
        uploading.value = false;
    }
}

function remove(family) {
    removeCustomFont(family);
    refresh();
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="File Font" description="Kelola font kustom (TTF/OTF/WOFF) untuk dokumen kamu.">
            <template #action>
                <AppButton :disabled="uploading" @click="openUpload">
                    <Upload class="h-4 w-4" />
                    Unggah Font
                </AppButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
            <span class="inline-flex items-center gap-1.5">
                <Coins class="h-4 w-4" />
                {{ creditPricing.font }} koin/font
            </span>
        </div>

        <div v-if="uploading" class="flex items-center justify-center py-16 text-sm text-neutral-500 dark:text-neutral-400">
            Mengunggah…
        </div>

        <EmptyState
            v-else-if="fonts.length === 0"
            title="Belum ada font"
            description="Unggah font kustom dari kampus kamu, misalnya file .ttf."
        >
            <template #icon>
                <Type class="h-6 w-6" />
            </template>
            <template #action>
                <AppButton variant="outline" @click="openUpload">Unggah Font Pertama</AppButton>
            </template>
        </EmptyState>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="font in fonts"
                :key="font.family"
                class="group flex items-center gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <Type class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold" :style="{ fontFamily: `'${font.family}'` }">{{ font.family }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ font.format }}</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-neutral-400 transition-colors hover:text-red-600 dark:text-neutral-500 dark:hover:text-red-400"
                    title="Hapus"
                    @click="remove(font.family)"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>
        </div>

        <input ref="fileInput" type="file" accept=".ttf,.otf,.woff,.woff2,font/ttf,font/otf,font/woff,font/woff2" multiple class="hidden" @change="onFileChange" />
    </div>
</template>
