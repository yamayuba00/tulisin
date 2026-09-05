<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { FileText, Trash2, Clock, Plus } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import AppButton from '../../components/AppButton.vue';
import { listProjects, removeProject, getProjectPreview } from '../../utils/projectIndex';
import { formatDate } from '../../utils/format';

const router = useRouter();
const projects = ref([]);
const deleteTarget = ref(null);

function refresh() {
    projects.value = listProjects().map((p) => ({
        ...p,
        preview: (Array.isArray(p.preview) && p.preview.length) ? p.preview : getProjectPreview(p.id),
    }));
}

onMounted(refresh);

function createProject() {
    const builderId = crypto.randomUUID();
    router.push({ path: '/apps/u/project', query: { builder: builderId } });
}

function openProject(id) {
    router.push({ path: '/apps/u/project', query: { builder: id } });
}

function remove(id) {
    deleteTarget.value = id;
}

function confirmDelete() {
    if (!deleteTarget.value) return;
    removeProject(deleteTarget.value);
    deleteTarget.value = null;
    refresh();
}

function cancelDelete() {
    deleteTarget.value = null;
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Projects" description="Kelola project dokumen kamu.">
            <template #action>
                <AppButton @click="createProject">
                    <Plus class="h-4 w-4" />
                    Buat Project
                </AppButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="projects.length === 0"
            title="Belum ada project"
            description="Buat project pertamamu dan mulai menyusun dokumen dengan AI."
        >
            <template #icon>
                <FileText class="h-6 w-6" />
            </template>
            <template #action>
                <AppButton variant="outline" @click="createProject">Buat Project Pertama</AppButton>
            </template>
        </EmptyState>

        <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="project in projects"
                :key="project.id"
                class="group relative cursor-pointer overflow-hidden rounded-xl border border-neutral-200 transition-colors hover:border-neutral-400 dark:border-neutral-800 dark:hover:border-neutral-600"
                @click="openProject(project.id)"
            >
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
                    <div class="mt-1.5 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                        <span class="rounded-full border border-neutral-200 px-2 py-0.5 dark:border-neutral-800">{{ project.category }}</span>
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

        <!-- Konfirmasi hapus project -->
        <div v-if="deleteTarget" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="cancelDelete"></div>
            <div class="relative z-10 w-full max-w-sm rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-950">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600 dark:bg-red-950/60 dark:text-red-400">
                        <Trash2 class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Hapus project ini?</h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Project beserta isinya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.
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
