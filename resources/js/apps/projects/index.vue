<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import {
    FileText, Trash2, Clock, Plus, Search, Globe, Lock, ChevronLeft, ChevronRight, FolderOpen,
} from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import DeleteConfirmModal from '../../components/DeleteConfirmModal.vue';
import SkeletonCard from '../../components/SkeletonCard.vue';
import AppButton from '../../components/AppButton.vue';
import { buildProjectPreview } from '../../utils/projectIndex';
import { getJson, request } from '../../utils/http';
import { formatDate } from '../../utils/format';
import { toast } from '../../utils/toast';

const router = useRouter();
const projects = ref([]);
const deleteTarget = ref(null);
const deleting = ref(false);
const loading = ref(false);
const query = ref('');
const statusFilter = ref('semua');
const page = ref(1);

const PER_PAGE = 8;

const STATUS_FILTERS = [
    { value: 'semua', label: 'Semua' },
    { value: 'published', label: 'Published' },
    { value: 'draft', label: 'Draft' },
];

// Hasil yang lolos filter pencarian + status.
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return projects.value.filter((p) => {
        const matchStatus =
            statusFilter.value === 'semua' ||
            (statusFilter.value === 'published' ? p.published : !p.published);
        const matchQ =
            !q ||
            p.name.toLowerCase().includes(q) ||
            (p.category || '').toLowerCase().includes(q);
        return matchStatus && matchQ;
    });
});

// Pagination (8 per halaman).
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));
const paged = computed(() => {
    const start = (page.value - 1) * PER_PAGE;
    return filtered.value.slice(start, start + PER_PAGE);
});

const publishedCount = computed(() => projects.value.filter((p) => p.published).length);
const draftCount = computed(() => projects.value.length - publishedCount.value);

async function refresh() {
    loading.value = true;
    try {
        const data = await getJson('/api/projects');
        const list = Array.isArray(data?.projects) ? data.projects : [];
        projects.value = list
            .map((p) => ({
                ...p,
                published: !!p.published,
                publishedAt: p.publishedAt || null,
                preview: buildProjectPreview(Array.isArray(p.blocks) ? p.blocks : []),
            }))
            .sort((a, b) => (Number(b.lastEdited) || 0) - (Number(a.lastEdited) || 0));
        page.value = 1;
    } catch {
        projects.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(refresh);

function createProject() {
    const builderId = crypto.randomUUID();
    router.push({ path: '/apps/u/project', query: { builder: builderId } });
}

function openProject(id) {
    router.push({ path: '/apps/u/project', query: { builder: id } });
}

function statusBtnClass(value) {
    return statusFilter.value === value
        ? 'border-neutral-900 bg-neutral-900 text-white dark:border-white dark:bg-white dark:text-neutral-950'
        : 'border-neutral-200 text-neutral-600 hover:border-neutral-300 dark:border-neutral-700 dark:text-neutral-300 dark:hover:border-neutral-600';
}

function setStatus(v) {
    statusFilter.value = v;
    page.value = 1;
}

function prevPage() {
    if (page.value > 1) page.value -= 1;
}

function nextPage() {
    if (page.value < totalPages.value) page.value += 1;
}

function remove(id) {
    deleteTarget.value = id;
}

async function confirmDelete() {
    if (!deleteTarget.value || deleting.value) return;
    const id = deleteTarget.value;
    deleting.value = true;
    try {
        const res = await request(`/api/projects/${encodeURIComponent(id)}`, { method: 'DELETE' });
        if (res.ok) {
            toast('Dokumen berhasil dihapus.', 'success');
            deleteTarget.value = null;
        } else {
            toast(res.data?.error || 'Gagal menghapus dokumen.', 'error');
        }
    } catch {
        toast('Gagal menghapus dokumen.', 'error');
    } finally {
        deleting.value = false;
    }
    refresh();
}

function cancelDelete() {
    if (deleting.value) return;
    deleteTarget.value = null;
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Dokumen Saya" description="Kelola dan periksa status publikasi dokumen kamu.">
            <template #action>
                <AppButton @click="createProject">
                    <Plus class="h-4 w-4" />
                    Buat Dokumen
                </AppButton>
            </template>
        </PageHeader>

        <!-- Bilah pencarian & filter -->
        <div class="mb-6 flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] dark:border-neutral-800 dark:bg-neutral-900/60 lg:flex-row lg:items-center">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <button
                    v-for="f in STATUS_FILTERS"
                    :key="f.value"
                    type="button"
                    class="cursor-pointer rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors"
                    :class="statusBtnClass(f.value)"
                    @click="setStatus(f.value)"
                >
                    {{ f.label }}
                </button>
                <span v-if="projects.length" class="ml-1 text-xs text-neutral-400 dark:text-neutral-500">
                    {{ publishedCount }} published · {{ draftCount }} draft
                </span>
            </div>

            <div class="relative w-full lg:w-80">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                <input
                    v-model="query"
                    type="text"
                    placeholder="Cari judul atau kategori..."
                    class="w-full rounded-lg border border-neutral-200 bg-transparent py-2 pl-9 pr-3 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                    @input="page = 1"
                />
            </div>
        </div>

        <!-- Loading -->
        <SkeletonCard v-if="loading" :count="8" gap="gap-5" />

        <!-- Belum ada dokumen sama sekali -->
        <EmptyState
            v-else-if="projects.length === 0"
            title="Belum ada dokumen"
            description="Buat dokumen pertamamu dan mulai menyusun dengan AI."
        >
            <template #icon>
                <FileText class="h-6 w-6" />
            </template>
            <template #action>
                <AppButton variant="outline" @click="createProject">Buat Dokumen Pertama</AppButton>
            </template>
        </EmptyState>

        <!-- Filter tidak ada yang cocok -->
        <div
            v-else-if="filtered.length === 0"
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-neutral-300 py-16 text-center dark:border-neutral-700"
        >
            <FolderOpen class="h-8 w-8 text-neutral-300 dark:text-neutral-600" />
            <p class="mt-3 text-sm text-neutral-500 dark:text-neutral-400">Tidak ada dokumen yang cocok dengan filter atau pencarian.</p>
            <button
                type="button"
                class="mt-4 cursor-pointer text-sm font-medium text-neutral-900 underline underline-offset-2 hover:text-neutral-600 dark:text-white"
                @click="(() => { query = ''; statusFilter = 'semua'; })"
            >
                Hapus filter
            </button>
        </div>

        <!-- Grid dokumen -->
        <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="project in paged"
                :key="project.id"
                class="group relative cursor-pointer overflow-hidden rounded-xl border border-neutral-200 bg-white transition-all hover:-translate-y-0.5 hover:border-neutral-400 hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900/60 dark:hover:border-neutral-600"
                @click="openProject(project.id)"
            >
                <!-- Badge status publikasi -->
                <div
                    class="absolute left-3 top-3 z-10 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium shadow-sm"
                    :class="project.published
                        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/70 dark:text-emerald-300 dark:ring-emerald-800'
                        : 'bg-white/95 text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-900/95 dark:text-neutral-300 dark:ring-neutral-700'"
                >
                    <Globe v-if="project.published" class="h-3 w-3" />
                    <Lock v-else class="h-3 w-3" />
                    {{ project.published ? 'Published' : 'Draft' }}
                </div>

                <!-- Pratinjau dokumen (snapshot isi paper) -->
                <div class="flex items-start justify-center bg-neutral-50 px-6 pb-2 pt-6 dark:bg-neutral-900">
                    <div class="relative aspect-[3/4] w-36 shrink-0 overflow-hidden rounded-sm border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                        <div class="p-3">
                            <p class="text-center text-[11px] font-bold leading-tight text-neutral-800 dark:text-neutral-100">{{ project.name }}</p>
                            <div class="mt-2 space-y-1.5">
                                <template v-for="(line, i) in project.preview" :key="i">
                                    <p v-if="line.kind === 'heading'" class="text-center text-[8px] font-bold tracking-wide text-neutral-700 dark:text-neutral-200">{{ line.text }}</p>
                                    <p v-else-if="line.kind === 'subheading'" class="text-[8px] font-semibold leading-tight text-neutral-700 dark:text-neutral-200">{{ line.text }}</p>
                                    <p v-else class="truncate text-[7px] leading-snug text-neutral-400 dark:text-neutral-500">{{ line.text }}</p>
                                </template>
                                <template v-if="project.preview.length === 0">
                                    <div class="h-1.5 w-full rounded bg-neutral-100 dark:bg-neutral-700"></div>
                                    <div class="h-1.5 w-4/5 rounded bg-neutral-100 dark:bg-neutral-700"></div>
                                    <div class="h-1.5 w-full rounded bg-neutral-100 dark:bg-neutral-700"></div>
                                </template>
                            </div>
                        </div>
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-10 bg-gradient-to-t from-neutral-100 to-transparent dark:from-neutral-900"></div>
                    </div>
                </div>

                <!-- Meta -->
                <div class="border-t border-neutral-100 p-4 dark:border-neutral-800">
                    <h2 class="truncate text-sm font-semibold">{{ project.name }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                        <span class="rounded-full border border-neutral-200 px-2 py-0.5 dark:border-neutral-800">{{ project.category || 'Lainnya' }}</span>
                        <span class="inline-flex items-center gap-1 text-neutral-400 dark:text-neutral-500">
                            <Clock class="h-3.5 w-3.5" />
                            {{ formatDate(project.lastEdited, { withTime: true }) }}
                        </span>
                    </div>
                </div>

                <button
                    type="button"
                    class="absolute right-2 top-2 inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md bg-white/90 text-red-600 opacity-0 shadow-sm transition-opacity hover:bg-red-50 group-hover:opacity-100 dark:bg-neutral-900/90 dark:hover:bg-red-950/40"
                    title="Hapus"
                    @click.stop="remove(project.id)"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="mt-8 flex items-center justify-between gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-800">
            <span class="text-xs text-neutral-400 dark:text-neutral-500">
                Menampilkan {{ filtered.length }} dokumen
            </span>
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white"
                    :disabled="page <= 1"
                    aria-label="Halaman sebelumnya"
                    @click="prevPage"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <span class="min-w-16 text-center text-sm text-neutral-600 dark:text-neutral-300">{{ page }} / {{ totalPages }}</span>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md border border-neutral-200 text-neutral-600 transition-colors hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:text-neutral-300 dark:hover:text-white"
                    :disabled="page >= totalPages"
                    aria-label="Halaman berikutnya"
                    @click="nextPage"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Konfirmasi hapus project -->
        <DeleteConfirmModal
            :open="!!deleteTarget"
            title="Hapus dokumen ini?"
            message="Dokumen beserta isinya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan."
            :busy="deleting"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        >
            <template #icon><Trash2 class="h-5 w-5" /></template>
            <template #confirm-icon><Trash2 class="h-4 w-4" /></template>
        </DeleteConfirmModal>
    </div>
</template>