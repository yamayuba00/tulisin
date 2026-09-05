<script setup>
import { ref, watch, computed } from 'vue';
import { Link2, Copy, Check, X, Loader2, Unlink, RefreshCw } from 'lucide-vue-next';
import AppButton from '../../../components/AppButton.vue';
import { request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    name: { type: String, default: '' },
    payload: { type: Object, default: () => ({}) },
    projectId: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const uuid = ref('');
const state = ref('');
const timeView = ref(1440);
const expiresAt = ref('');
const notCopy = ref(true);
const copied = ref(false);

const expiresInfo = computed(() => {
    if (!expiresAt.value) return '';
    const d = new Date(expiresAt.value);
    if (Number.isNaN(d.getTime())) return '';
    return `Berlaku sampai ${d.toLocaleString('id-ID')}`;
});

function storageKey() {
    return `tulisin:share:${props.projectId || 'default'}`;
}

function isExpired(iso) {
    if (!iso) return false;
    const d = new Date(iso);
    return !Number.isNaN(d.getTime()) && d.getTime() < Date.now();
}

function loadStored() {
    uuid.value = '';
    state.value = '';
    timeView.value = 1440;
    expiresAt.value = '';
    try {
        const raw = localStorage.getItem(storageKey());
        const data = raw ? JSON.parse(raw) : null;
        if (data && data.uuid && data.state && !isExpired(data.expiresAt)) {
            uuid.value = data.uuid;
            state.value = data.state || '';
            timeView.value = data.timeView || 1440;
            expiresAt.value = data.expiresAt || '';
        }
    } catch {
        // abaikan bila localStorage tidak tersedia
    }
}

function persist() {
    try {
        localStorage.setItem(storageKey(), JSON.stringify({
            uuid: uuid.value,
            state: state.value,
            timeView: timeView.value,
            expiresAt: expiresAt.value,
        }));
    } catch {
        // abaikan
    }
}

function clearStored() {
    try {
        localStorage.removeItem(storageKey());
    } catch {
        // abaikan
    }
}

watch(open, (val) => {
    if (val) {
        copied.value = false;
        loadStored();
    }
});

async function createShare() {
    if (loading.value) return;
    loading.value = true;
    try {
        const res = await request('/api/shared', {
            method: 'POST',
            body: JSON.stringify({
                name: props.name || 'Dokumen Tanpa Judul',
                payload: props.payload,
                project_uuid: props.projectId || null,
                time_view: 1440,
            }),
        });
        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal membuat link.', 'error');
            return;
        }
        uuid.value = res.data.uuid;
        state.value = res.data.state;
        timeView.value = res.data.timeView || 1440;
        expiresAt.value = res.data.expiresAt || '';
        persist();
    } catch (e) {
        toast(e?.message || 'Gagal membuat link.', 'error');
    } finally {
        loading.value = false;
    }
}

async function updateShare() {
    if (!uuid.value || loading.value) return;
    loading.value = true;
    try {
        const res = await request(`/api/shared/${uuid.value}`, {
            method: 'PUT',
            body: JSON.stringify({
                name: props.name || 'Dokumen Tanpa Judul',
                payload: props.payload,
                project_uuid: props.projectId || null,
                time_view: timeView.value || 1440,
            }),
        });
        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal memperbarui link.', 'error');
            return;
        }
        state.value = res.data.state || state.value;
        timeView.value = res.data.timeView || timeView.value;
        expiresAt.value = res.data.expiresAt || '';
        persist();
    } catch (e) {
        toast(e?.message || 'Gagal memperbarui link.', 'error');
    } finally {
        loading.value = false;
    }
}

async function disableShare() {
    if (!uuid.value || loading.value) return;
    loading.value = true;
    try {
        const res = await request(`/api/shared/${uuid.value}`, { method: 'DELETE' });
        if (!res.ok) {
            toast(res.data?.error || 'Gagal menonaktifkan link.', 'error');
            return;
        }
        uuid.value = '';
        state.value = '';
        expiresAt.value = '';
        clearStored();
    } catch (e) {
        toast(e?.message || 'Gagal menonaktifkan link.', 'error');
    } finally {
        loading.value = false;
    }
}

function fullUrl() {
    if (!uuid.value) return '';
    const params = new URLSearchParams({
        shared: uuid.value,
        state: state.value,
        timeView: String(timeView.value),
        view: 'true',
        notcopy: String(notCopy.value),
    });
    return `${window.location.origin}/share?${params.toString()}`;
}

async function copy() {
    const link = fullUrl();
    if (!link) return;
    try {
        await navigator.clipboard.writeText(link);
    } catch {
        const ta = document.createElement('textarea');
        ta.value = link;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
    }
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function close() {
    open.value = false;
    emit('close');
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="close"></div>

        <div class="relative z-10 flex w-full max-w-lg flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
            <div class="flex items-start justify-between border-b border-neutral-200 px-5 py-3 dark:border-neutral-800">
                <div>
                    <h2 class="text-base font-semibold">Bagikan Dokumen</h2>
                    <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                        Buat link publik agar dokumen bisa dilihat siapa pun tanpa login.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    aria-label="Tutup"
                    @click="close"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex flex-col gap-4 p-5">
                <template v-if="!uuid">
                    <AppButton :disabled="loading" block @click="createShare">
                        <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                        <Link2 v-else class="h-4 w-4" />
                        {{ loading ? 'Membuat link…' : 'Buat Link Bagikan' }}
                    </AppButton>
                </template>

                <template v-else>
                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Link Publik</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input
                                :value="fullUrl()"
                                readonly
                                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs text-neutral-600 outline-none dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300"
                            />
                            <button
                                type="button"
                                class="inline-flex h-9 shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-300 px-3 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="copy"
                            >
                                <Check v-if="copied" class="h-4 w-4 text-emerald-600" />
                                <Copy v-else class="h-4 w-4" />
                                {{ copied ? 'Tersalin' : 'Salin' }}
                            </button>
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                        <input v-model="notCopy" type="checkbox" class="h-4 w-4 rounded border-neutral-300" />
                        Nonaktifkan salin (pembaca tidak bisa copy teks)
                    </label>

                    <p class="text-xs text-neutral-400 dark:text-neutral-500">
                        Link bersifat publik dan otomatis kedaluwarsa setelah 24 jam.
                        Setelah dokumen diubah, klik <span class="font-medium">Perbarui Link</span> agar perubahan terbaru ikut tampil.
                        <span v-if="expiresInfo" class="block mt-1">{{ expiresInfo }}</span>
                    </p>

                    <div class="flex items-center gap-2">
                        <AppButton variant="outline" :disabled="loading" @click="updateShare">
                            <RefreshCw v-if="loading" class="h-4 w-4 animate-spin" />
                            <RefreshCw v-else class="h-4 w-4" />
                            Perbarui Link
                        </AppButton>
                        <AppButton variant="outline" :disabled="loading" @click="disableShare">
                            <Unlink class="h-4 w-4" />
                            Nonaktifkan
                        </AppButton>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
