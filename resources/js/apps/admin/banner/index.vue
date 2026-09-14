<script setup>
import { onMounted, ref, computed } from 'vue';
import { Info, Save, Plus, Trash2, Megaphone, Palette } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const saving = ref(false);

const banner = ref({
    enabled: false,
    mode: 'single',
    text: '',
    link_text: '',
    link_url: '',
    messages: [],
    background: '#171717',
    text_color: '#ffffff',
    speed: 40,
});

const BG_SWATCHES = ['#171717', '#0f172a', '#1f2937', '#7c3aed', '#2563eb', '#16a34a', '#dc2626', '#ffffff'];
const TEXT_SWATCHES = ['#ffffff', '#171717', '#fde047', '#0f172a'];

const isMarquee = computed(() => banner.value.mode === 'marquee');

function ensureMessages() {
    if (!Array.isArray(banner.value.messages) || banner.value.messages.length === 0) {
        banner.value.messages = [{ text: '', link_text: '', link_url: '' }];
    }
}

function addMessage() {
    ensureMessages();
    banner.value.messages.push({ text: '', link_text: '', link_url: '' });
}

function removeMessage(index) {
    banner.value.messages.splice(index, 1);
    ensureMessages();
}

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/information-banner-settings');
        banner.value = { ...banner.value, ...(data.banner || {}) };
        ensureMessages();
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
            ensureMessages();
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
        <PageHeader title="Banner Informasi" description="Bar informasi di atas header homepage. Dukung mode tunggal maupun marquee dengan warna bebas.">
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
            <div class="space-y-6 lg:col-span-3">
                <!-- Konten -->
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 p-5 dark:border-neutral-800">
                        <div>
                            <h2 class="font-semibold">Konten Banner</h2>
                            <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Pilih mode tampilan informasi.</p>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                            <input v-model="banner.enabled" type="checkbox" class="h-4 w-4 rounded border-neutral-300" />
                            Aktifkan banner
                        </label>
                    </div>

                    <div class="space-y-5 p-5">
                        <!-- Mode -->
                        <div>
                            <span class="mb-2 block text-sm font-medium">Mode Tampilan</span>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-xl border p-3 text-left transition-colors"
                                    :class="banner.mode === 'single'
                                        ? 'border-neutral-900 ring-1 ring-neutral-900 dark:border-white dark:ring-white'
                                        : 'border-neutral-200 hover:border-neutral-300 dark:border-neutral-700 dark:hover:border-neutral-600'"
                                    @click="banner.mode = 'single'"
                                >
                                    <Info class="h-4 w-4" />
                                    <p class="mt-1 text-sm font-semibold">Informasi Tunggal</p>
                                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Satu pesan statis di tengah.</p>
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-xl border p-3 text-left transition-colors"
                                    :class="banner.mode === 'marquee'
                                        ? 'border-neutral-900 ring-1 ring-neutral-900 dark:border-white dark:ring-white'
                                        : 'border-neutral-200 hover:border-neutral-300 dark:border-neutral-700 dark:hover:border-neutral-600'"
                                    @click="banner.mode = 'marquee'; ensureMessages()"
                                >
                                    <Megaphone class="h-4 w-4" />
                                    <p class="mt-1 text-sm font-semibold">Marquee / Banyak Info</p>
                                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Beberapa pesan berjalan otomatis.</p>
                                </button>
                            </div>
                        </div>

                        <!-- Single -->
                        <template v-if="!isMarquee">
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
                                        placeholder="/register atau https://…"
                                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                                    />
                                </label>
                            </div>
                        </template>

                        <!-- Marquee -->
                        <template v-else>
                            <div class="space-y-3">
                                <div
                                    v-for="(m, i) in banner.messages"
                                    :key="i"
                                    class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Pesan {{ i + 1 }}</span>
                                        <button
                                            type="button"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-1 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40"
                                            @click="removeMessage(i)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                            Hapus
                                        </button>
                                    </div>
                                    <input
                                        v-model="m.text"
                                        type="text"
                                        placeholder="Isi pesan informasi…"
                                        class="mt-2 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                                    />
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <input
                                            v-model="m.link_text"
                                            type="text"
                                            placeholder="Teks tautan (opsional)"
                                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                                        />
                                        <input
                                            v-model="m.link_url"
                                            type="text"
                                            placeholder="URL tautan (opsional)"
                                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                                        />
                                    </div>
                                </div>
                                <AppButton variant="outline" block @click="addMessage">
                                    <Plus class="h-4 w-4" />
                                    Tambah Pesan
                                </AppButton>
                            </div>

                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium">Kecepatan berjalan: {{ banner.speed }} detik / putaran</span>
                                <input
                                    v-model.number="banner.speed"
                                    type="range"
                                    min="5"
                                    max="120"
                                    step="5"
                                    class="w-full accent-neutral-900 dark:accent-white"
                                />
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tampilan & pratinjau -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Warna -->
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center gap-2">
                        <Palette class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                        <h2 class="text-sm font-semibold">Warna & Tampilan</h2>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Warna Latar</span>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <button
                                    v-for="c in BG_SWATCHES"
                                    :key="c"
                                    type="button"
                                    class="h-7 w-7 cursor-pointer rounded-full border border-neutral-200 ring-offset-2 dark:border-neutral-700 dark:ring-offset-neutral-900"
                                    :class="banner.background === c ? 'ring-2 ring-neutral-900 dark:ring-white' : ''"
                                    :style="{ background: c }"
                                    @click="banner.background = c"
                                ></button>
                                <input
                                    v-model="banner.background"
                                    type="color"
                                    class="h-7 w-9 cursor-pointer rounded border border-neutral-200 bg-transparent dark:border-neutral-700"
                                />
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Warna Teks</span>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <button
                                    v-for="c in TEXT_SWATCHES"
                                    :key="c"
                                    type="button"
                                    class="h-7 w-7 cursor-pointer rounded-full border border-neutral-200 ring-offset-2 dark:border-neutral-700 dark:ring-offset-neutral-900"
                                    :class="banner.text_color === c ? 'ring-2 ring-neutral-900 dark:ring-white' : ''"
                                    :style="{ background: c }"
                                    @click="banner.text_color = c"
                                ></button>
                                <input
                                    v-model="banner.text_color"
                                    type="color"
                                    class="h-7 w-9 cursor-pointer rounded border border-neutral-200 bg-transparent dark:border-neutral-700"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pratinjau -->
                <div>
                    <h3 class="mb-2 flex items-center gap-1.5 text-sm font-semibold">
                        <Info class="h-4 w-4" />
                        Pratinjau
                    </h3>
                    <div
                        class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800"
                        :style="{ background: banner.background, color: banner.text_color }"
                    >
                        <div v-if="!isMarquee" class="flex items-center justify-center gap-2 px-6 py-4 text-sm">
                            <Info class="h-4 w-4 shrink-0 opacity-70" />
                            <p class="min-w-0 text-center">
                                {{ banner.text || 'Pesan informasi akan tampil di sini.' }}
                                <span v-if="banner.link_text && banner.link_url" class="ml-1 font-semibold underline underline-offset-4">
                                    {{ banner.link_text }}
                                </span>
                            </p>
                        </div>

                        <div v-else class="preview-marquee overflow-hidden px-6 py-4 text-sm">
                            <div class="preview-marquee-track flex w-max items-center gap-10">
                                <p
                                    v-for="(m, i) in [...(banner.messages.filter((x) => x.text)), ...(banner.messages.filter((x) => x.text))]"
                                    :key="i"
                                    class="flex items-center gap-2 whitespace-nowrap"
                                >
                                    <span>{{ m.text }}</span>
                                    <span v-if="m.link_text && m.link_url" class="font-semibold underline underline-offset-4">{{ m.link_text }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-neutral-400 dark:text-neutral-500">
                        Banner muncul sebagai bar tipis di atas header homepage. Pengunjung bisa menutupnya sementara.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes preview-marquee {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}
.preview-marquee-track {
    animation: preview-marquee linear infinite;
    animation-duration: 20s;
}
</style>
