<script setup>
import { onMounted, ref } from 'vue';
import { Save, Plus, Trash2, Sparkles } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const engines = ref([]);
const newEngine = ref('');

async function load() {
    loading.value = true;
    try {
        const data = await getJson('/api/admin/ai-settings');
        engines.value = Array.isArray(data.engines) ? data.engines : ['DeepSeek'];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

function addEngine() {
    const value = newEngine.value.trim();
    if (!value) {
        toast('Masukkan nama mesin AI terlebih dahulu.', 'warning');
        return;
    }
    if (value.length > 40) {
        toast('Nama mesin AI maksimal 40 karakter.', 'error');
        return;
    }
    if (engines.value.some((e) => e.toLowerCase() === value.toLowerCase())) {
        toast('Mesin AI sudah ada di daftar.', 'warning');
        return;
    }
    engines.value = [...engines.value, value];
    newEngine.value = '';
}

function removeEngine(index) {
    engines.value = engines.value.filter((_, i) => i !== index);
}

async function save() {
    if (engines.value.length === 0) {
        toast('Minimal satu mesin AI diperlukan.', 'error');
        return;
    }

    saving.value = true;
    try {
        const res = await request('/api/admin/ai-settings', {
            method: 'PUT',
            body: JSON.stringify({ engines: engines.value }),
        });
        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal menyimpan mesin AI.', 'error');
            return;
        }
        engines.value = Array.isArray(res.data?.engines) ? res.data.engines : engines.value;
        toast(res.data?.message || 'Mesin AI berhasil disimpan.', 'success');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Mesin AI" description="Kelola daftar mesin AI yang menjadi tenaga Agent Tulisin. Daftar ini ditampilkan di homepage.">
            <template #action>
                <AppButton :disabled="saving || loading" @click="save">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </AppButton>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Daftar mesin -->
            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                    <Sparkles class="h-4 w-4" />
                    Daftar Mesin AI
                </div>

                <div v-if="loading" class="mt-4 text-sm text-neutral-400 dark:text-neutral-500">Memuat…</div>

                <ul v-else class="mt-4 space-y-2">
                    <li
                        v-for="(engine, i) in engines"
                        :key="engine + i"
                        class="flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-800"
                    >
                        <span class="text-sm font-medium text-neutral-700 dark:text-neutral-200">{{ engine }}</span>
                        <button
                            type="button"
                            title="Hapus"
                            aria-label="Hapus mesin"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg text-neutral-400 transition-colors hover:text-red-500 dark:hover:text-red-400"
                            @click="removeEngine(i)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </li>
                </ul>

                <div class="mt-4 flex gap-2">
                    <input
                        v-model="newEngine"
                        type="text"
                        maxlength="40"
                        placeholder="Nama mesin, mis. GPT-4o"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                        @keyup.enter="addEngine"
                    />
                    <AppButton variant="outline" size="sm" @click="addEngine">
                        <Plus class="h-4 w-4" />
                        Tambah
                    </AppButton>
                </div>
            </div>

            <!-- Info -->
            <div class="rounded-xl border border-neutral-200 p-6 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-300">
                <p class="font-medium text-neutral-800 dark:text-neutral-200">Aturan</p>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-neutral-500 dark:text-neutral-400">
                    <li>Minimal satu mesin AI harus tetap ada.</li>
                    <li>Nama mesin maksimal 40 karakter.</li>
                    <li>Daftar ini tampil di homepage sebagai "Ditenagai oleh".</li>
                </ul>
            </div>
        </div>
    </div>
</template>
