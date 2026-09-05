<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link2, Clock, Gift, Eye, AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { getJson, request } from '../../utils/http';
import { toast } from '../../utils/toast';
import { formatDate } from '../../utils/format';

const MIN_VIEWS = 300;

const loading = ref(true);
const submitting = ref(false);
const url = ref('');
const notes = ref('');
const views = ref(0);
const submissions = ref([]);

const hasSubmitted = computed(() => submissions.value.length > 0);
const viewsQualified = computed(() => Number(views.value) >= MIN_VIEWS);
const canSubmit = computed(() => url.value.trim().length > 0 && !hasSubmitted.value);

const steps = [
    { icon: Link2, title: 'Kirim URL', desc: 'Isi tautan kontenmu beserta jumlah views.' },
    { icon: Clock, title: 'Verifikasi Admin', desc: 'Admin memeriksa keaslian tautanmu.' },
    { icon: Gift, title: 'Koin Masuk', desc: 'Setelah disetujui, koin ditambahkan ke saldo.' },
];

function isValidUrl(value) {
    try {
        const u = new URL(value);
        return u.protocol === 'http:' || u.protocol === 'https:';
    } catch {
        return false;
    }
}

function statusMeta(status) {
    switch (status) {
        case 'approved':
            return { label: 'Disetujui', tone: 'success' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'danger' };
        default:
            return { label: 'Menunggu Verifikasi', tone: 'warning' };
    }
}

async function load() {
    loading.value = true;
    try {
        const data = await getJson('/api/credit-submissions');
        submissions.value = data.submissions || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

async function submit() {
    const trimmed = url.value.trim();
    if (!trimmed) {
        toast('Masukkan URL terlebih dahulu.', 'warning');
        return;
    }
    if (!isValidUrl(trimmed)) {
        toast('URL tidak valid. Pastikan diawali http:// atau https://.', 'error');
        return;
    }

    submitting.value = true;
    try {
        const res = await request('/api/credit-submissions', {
            method: 'POST',
            body: JSON.stringify({ url: trimmed, notes: notes.value.trim(), views: Number(views.value) || 0 }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Kiriman berhasil dikirim.', 'success');
            url.value = '';
            notes.value = '';
            views.value = 0;
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal mengirim.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Dapatkan Koin" description="Kirim tautan kontenmu sekali saja, dapatkan koin setelah diverifikasi admin." />

        <!-- Langkah -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="(s, i) in steps" :key="s.title" class="rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                        <component :is="s.icon" class="h-4 w-4" />
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Langkah {{ i + 1 }}</span>
                </div>
                <h3 class="mt-3 font-semibold">{{ s.title }}</h3>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ s.desc }}</p>
            </div>
        </div>

        <!-- Info syarat views -->
        <div class="mt-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
            <AlertCircle class="h-4 w-4 shrink-0" />
            <p>
                Konten kamu harus memiliki lebih dari <strong>{{ MIN_VIEWS }} views</strong> agar memenuhi syarat untuk diverifikasi.
                Pengajuan hanya bisa dikirim <strong>satu kali</strong>.
            </p>
        </div>

        <!-- Sudah pernah kirim -->
        <div v-if="hasSubmitted && !loading" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
            <div class="flex items-center gap-2 font-medium">
                <CheckCircle2 class="h-4 w-4" />
                Kamu sudah mengirim tautan.
            </div>
            <p class="mt-1">Status pengajuanmu bisa dilihat di bagian "Kiriman Saya" di bawah.</p>
        </div>

        <!-- Form -->
        <div v-if="!hasSubmitted" class="mt-6 rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
            <h2 class="font-semibold">Kirim URL</h2>

            <div class="mt-4">
                <label for="submit-url" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">URL Konten</label>
                <input
                    id="submit-url"
                    v-model="url"
                    type="url"
                    placeholder="https://contoh.com/artikel-kamu"
                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                />
            </div>

            <div class="mt-4">
                <label for="submit-views" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Jumlah Views</label>
                <div class="mt-1 flex items-center gap-2">
                    <Eye class="h-4 w-4 shrink-0 text-neutral-400 dark:text-neutral-600" />
                    <input
                        id="submit-views"
                        v-model.number="views"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                    />
                </div>
                <p v-if="viewsQualified" class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                    Memenuhi syarat lebih dari {{ MIN_VIEWS }} views.
                </p>
                <p v-else class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">
                    Minimal {{ MIN_VIEWS }} views agar memenuhi syarat verifikasi.
                </p>
            </div>

            <div class="mt-4">
                <label for="submit-notes" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Catatan <span class="text-neutral-400">(opsional)</span>
                </label>
                <textarea
                    id="submit-notes"
                    v-model="notes"
                    rows="3"
                    placeholder="Jelaskan singkat isi kontenmu..."
                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                ></textarea>
            </div>

            <div class="mt-5">
                <AppButton block :disabled="!canSubmit || submitting" @click="submit">
                    {{ submitting ? 'Mengirim…' : 'Kirim untuk Verifikasi' }}
                </AppButton>
            </div>
        </div>

        <!-- Riwayat -->
        <div class="mt-8">
            <h2 class="mb-4 font-semibold">Kiriman Saya</h2>

            <div v-if="loading" class="rounded-lg border border-dashed border-neutral-300 px-6 py-12 text-center dark:border-neutral-700">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Memuat…</p>
            </div>

            <div v-else-if="submissions.length === 0" class="rounded-lg border border-dashed border-neutral-300 px-6 py-12 text-center dark:border-neutral-700">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Belum ada kiriman. Kirim URL pertamamu di atas.</p>
            </div>

            <div v-else class="space-y-3">
                <div v-for="sub in submissions" :key="sub.id" class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <a
                            :href="sub.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="min-w-0 flex-1 truncate text-sm font-medium text-neutral-900 underline-offset-2 hover:underline dark:text-neutral-100"
                        >
                            {{ sub.url }}
                        </a>
                        <StatusBadge :label="statusMeta(sub.status).label" :tone="statusMeta(sub.status).tone" />
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                        <span>{{ formatDate(sub.created_at, { withTime: true }) }}</span>
                        <span class="inline-flex items-center gap-1"><Eye class="h-3.5 w-3.5" /> {{ sub.views }} views</span>
                        <span v-if="sub.status === 'approved'" class="font-medium text-neutral-900 dark:text-white">+{{ sub.credits_awarded }} koin</span>
                    </div>
                    <p v-if="sub.notes" class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{{ sub.notes }}</p>
                    <p v-if="sub.review_note" class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Catatan admin: {{ sub.review_note }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
