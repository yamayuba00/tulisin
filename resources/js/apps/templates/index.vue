<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { LayoutTemplate, Coins, FileText, Plus, Trash2 } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import TemplateBuilderModal from './components/TemplateBuilderModal.vue';
import { TEMPLATES, buildProjectPayload } from '../../utils/templates';
import { touchProject } from '../../utils/projectIndex';
import { getJson, request } from '../../utils/http';
import { toast } from '../../utils/toast';
import { creditPricing, loadCreditPricing } from '../../utils/creditPricing';

const router = useRouter();

const busyId = ref(null);
const customTemplates = ref([]);
const showBuilder = ref(false);

onMounted(() => {
    loadCreditPricing();
    loadCustomTemplates();
});

async function useTemplate(template) {
    busyId.value = template.id;

    // Template kustom (buatan sendiri) tidak dipotong kredit.
    if (!template.custom) {
        const res = await request('/api/wallet/spend', {
            method: 'POST',
            body: JSON.stringify({ credits: creditPricing.value.template, reason: 'template_use' }),
        });
        if (!res.ok) {
            toast(res.data?.error || 'Saldo koin tidak mencukupi.', 'error');
            busyId.value = null;
            return;
        }
    }

    try {
        const builderId = crypto.randomUUID();
        const payload = buildProjectPayload(template);
        localStorage.setItem(`tulisin:project:${builderId}`, JSON.stringify(payload));
        touchProject(builderId, { name: template.title, category: template.category, blocks: payload.blocks });
        router.push({ path: '/apps/u/project', query: { builder: builderId } });
    } catch {
        toast('Gagal membuat project. Coba lagi.', 'error');
    } finally {
        busyId.value = null;
    }
}

async function loadCustomTemplates() {
    try {
        const data = await getJson('/api/templates');
        customTemplates.value = data.templates || [];
    } catch (e) {
        toast(e.message, 'error');
        customTemplates.value = [];
    }
}

async function onSaveCustom(template) {
    try {
        const res = await request('/api/templates', {
            method: 'POST',
            body: JSON.stringify({
                title: template.title,
                category: template.category,
                description: template.description,
                format: template.format,
                font: template.font,
                blocks: template.blocks,
            }),
        });
        if (res.ok) {
            toast('Template tersimpan.', 'success');
            await loadCustomTemplates();
        } else {
            toast(res.data?.error || 'Gagal menyimpan template.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    }
}

async function onDeleteCustom(id) {
    try {
        const res = await request(`/api/templates/${encodeURIComponent(id)}`, { method: 'DELETE' });
        if (res.ok) {
            toast('Template dihapus.', 'success');
        } else {
            toast(res.data?.error || 'Gagal menghapus template.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    }
    await loadCustomTemplates();
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <div class="flex items-start justify-between gap-4">
            <PageHeader title="Template" description="Mulai cepat dengan template siap pakai, atau buat template sendiri." />
            <AppButton class="shrink-0" @click="showBuilder = true">
                <Plus class="h-4 w-4" />
                Buat Template
            </AppButton>
        </div>

        <div class="mb-4 flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
            <Coins class="h-4 w-4" />
            Template siap pakai menghabiskan {{ creditPricing.template }} koin per project. Template buatan sendiri gratis.
        </div>

        <!-- Template siap pakai -->
        <h2 class="mb-3 text-sm font-semibold text-neutral-700 dark:text-neutral-300">Template Siap Pakai</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="t in TEMPLATES"
                :key="t.id"
                class="flex flex-col rounded-xl border border-neutral-200 p-5 dark:border-neutral-800"
            >
                <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                    <LayoutTemplate class="h-5 w-5" />
                </div>
                <h3 class="mt-3 font-semibold">{{ t.title }}</h3>
                <p class="mt-1 flex-1 text-sm text-neutral-500 dark:text-neutral-400">{{ t.description }}</p>
                <div class="mt-3 flex items-center gap-2 text-xs text-neutral-400 dark:text-neutral-500">
                    <span class="rounded-full border border-neutral-200 px-2 py-0.5 dark:border-neutral-800">{{ t.category }}</span>
                    <span class="inline-flex items-center gap-1">
                        <FileText class="h-3.5 w-3.5" />
                        {{ t.blocks.length }} blok
                    </span>
                </div>
                <AppButton block class="mt-4" :disabled="busyId === t.id" @click="useTemplate(t)">
                    <Coins class="h-4 w-4" />
                    {{ busyId === t.id ? 'Memproses…' : `Gunakan · ${creditPricing.template} Koin` }}
                </AppButton>
            </div>
        </div>

        <!-- Template buatan sendiri -->
        <div class="mt-8">
            <h2 class="mb-3 text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                Template Saya <span class="ml-1 text-neutral-400">({{ customTemplates.length }})</span>
            </h2>

            <p v-if="customTemplates.length === 0" class="rounded-lg border border-dashed border-neutral-300 p-6 text-center text-sm text-neutral-400 dark:border-neutral-700 dark:text-neutral-500">
                Belum ada template buatan sendiri. Klik "Buat Template" untuk memulai.
            </p>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="t in customTemplates"
                    :key="t.id"
                    class="flex flex-col rounded-xl border border-neutral-200 p-5 dark:border-neutral-800"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                            <LayoutTemplate class="h-5 w-5" />
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-red-600 dark:hover:bg-neutral-800 dark:hover:text-red-400"
                            aria-label="Hapus template"
                            @click="onDeleteCustom(t.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <h3 class="mt-3 font-semibold">{{ t.title }}</h3>
                    <p class="mt-1 flex-1 text-sm text-neutral-500 dark:text-neutral-400">{{ t.description }}</p>
                    <div class="mt-3 flex items-center gap-2 text-xs text-neutral-400 dark:text-neutral-500">
                        <span class="rounded-full border border-neutral-200 px-2 py-0.5 dark:border-neutral-800">{{ t.category }}</span>
                        <span class="inline-flex items-center gap-1">
                            <FileText class="h-3.5 w-3.5" />
                            {{ t.blocks.length }} blok
                        </span>
                    </div>
                    <AppButton block class="mt-4" :disabled="busyId === t.id" @click="useTemplate(t)">
                        <FileText class="h-4 w-4" />
                        {{ busyId === t.id ? 'Memproses…' : 'Gunakan · Gratis' }}
                    </AppButton>
                </div>
            </div>
        </div>

        <TemplateBuilderModal v-model:open="showBuilder" @save="onSaveCustom" />
    </div>
</template>
