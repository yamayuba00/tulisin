<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Sparkles, FileText, ListOrdered, CornerDownRight, Loader2 } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import { AGENT_DOCUMENT_TYPES, DEFAULT_CHAPTERS, buildAgentProject } from '../../utils/agentProject';
import { touchProject } from '../../utils/projectIndex';
import { request } from '../../utils/http';
import { creditPricing, loadCreditPricing } from '../../utils/creditPricing';
import { toast } from '../../utils/toast';

const router = useRouter();

const documentType = ref('Skripsi');
const title = ref('');
const description = ref('');
const chaptersInput = ref('');
const generating = ref(false);

const previewChapters = computed(() => {
    if (chaptersInput.value.trim()) {
        return chaptersInput.value
            .split(/[,;\n]/)
            .map((s) => s.trim())
            .filter(Boolean);
    }
    return DEFAULT_CHAPTERS[documentType.value] || DEFAULT_CHAPTERS.Lainnya;
});

onMounted(() => {
    loadCreditPricing();
});

async function createProject() {
    if (!title.value.trim()) {
        toast('Judul / topik wajib diisi.', 'warning');
        return;
    }

    generating.value = true;
    try {
        const cost = Number(creditPricing.value.agent_generate) || 1;
        const res = await request('/api/wallet/spend', {
            method: 'POST',
            body: JSON.stringify({ credits: cost, reason: 'agent_generate' }),
        });
        if (!res.ok) {
            toast(res.data?.error || 'Saldo koin tidak mencukupi.', 'error');
            return;
        }

        const payload = buildAgentProject({
            title: title.value,
            documentType: documentType.value,
            description: description.value,
            chapters: chaptersInput.value,
        });

        const builderId = crypto.randomUUID();
        localStorage.setItem(`tulisin:project:${builderId}`, JSON.stringify(payload));
        touchProject(builderId, { name: payload.name, category: payload.category, blocks: payload.blocks });

        router.push({ path: '/apps/u/project', query: { builder: builderId } });
    } catch (e) {
        toast(e.message || 'Gagal membuat project. Coba lagi.', 'error');
    } finally {
        generating.value = false;
    }
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Agent AI" description="Deskripsikan project kamu dan Agent AI akan menyusun kerangka dokumennya." />

        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Form -->
            <div class="lg:col-span-3">
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="border-b border-neutral-100 p-5 dark:border-neutral-800">
                        <h2 class="font-semibold">Detail Project</h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Semakin lengkap deskripsi, semakin sesuai kerangka yang dibuat.
                        </p>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Jenis Dokumen</label>
                            <select
                                v-model="documentType"
                                class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            >
                                <option v-for="t in AGENT_DOCUMENT_TYPES" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Judul / Topik <span class="text-red-500">*</span></label>
                            <input
                                v-model="title"
                                type="text"
                                placeholder="Mis. Analisis Pengaruh Media Sosial terhadap Produktivitas Mahasiswa"
                                class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Deskripsi / Tujuan Singkat</label>
                            <textarea
                                v-model="description"
                                rows="4"
                                placeholder="Jelaskan secara singkat tujuan dan ruang lingkup project ini (dipakai sebagai abstrak)."
                                class="w-full resize-y rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            ></textarea>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Daftar Bab (opsional)</label>
                            <input
                                v-model="chaptersInput"
                                type="text"
                                placeholder="Pisahkan dengan koma, mis. Pendahuluan, Tinjauan Pustaka, Metode"
                                class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                            />
                            <p class="mt-1.5 text-xs text-neutral-400 dark:text-neutral-500">
                                Kosongkan untuk memakai bab bawaan jenis {{ documentType }}.
                            </p>
                        </div>

                        <AppButton block :disabled="generating" @click="createProject">
                            <Loader2 v-if="generating" class="h-4 w-4 animate-spin" />
                            <Sparkles v-else class="h-4 w-4" />
                            {{ generating ? 'Membuat project…' : `Buat Project dengan AI · ${creditPricing.agent_generate} Koin` }}
                        </AppButton>
                    </div>
                </div>
            </div>

            <!-- Pratinjau kerangka -->
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center gap-2 border-b border-neutral-100 p-5 dark:border-neutral-800">
                        <ListOrdered class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                        <h2 class="font-semibold">Pratinjau Kerangka</h2>
                    </div>

                    <div class="p-5">
                        <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                            <div class="flex items-center gap-2">
                                <FileText class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                                <p class="truncate font-semibold">{{ title || 'Judul project' }}</p>
                            </div>
                            <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">{{ documentType }}</p>
                        </div>

                        <ul class="mt-4 space-y-2">
                            <li class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-neutral-100 text-[10px] font-semibold dark:bg-neutral-800">1</span>
                                Cover
                            </li>
                            <li class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-neutral-100 text-[10px] font-semibold dark:bg-neutral-800">2</span>
                                Abstrak
                            </li>
                            <li class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-neutral-100 text-[10px] font-semibold dark:bg-neutral-800">3</span>
                                Daftar Isi
                            </li>
                            <li
                                v-for="(c, i) in previewChapters"
                                :key="i"
                                class="flex items-center gap-2 text-sm"
                            >
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-neutral-100 text-[10px] font-semibold dark:bg-neutral-800">{{ i + 4 }}</span>
                                <CornerDownRight class="h-3.5 w-3.5 text-neutral-300 dark:text-neutral-600" />
                                <span>{{ c }}</span>
                            </li>
                            <li class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-neutral-100 text-[10px] font-semibold dark:bg-neutral-800">{{ previewChapters.length + 4 }}</span>
                                Daftar Pustaka
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
