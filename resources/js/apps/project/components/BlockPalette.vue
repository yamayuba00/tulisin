<script setup>
import { computed } from 'vue';
import { Search, X, GripVertical, Library, Layers } from 'lucide-vue-next';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    workspaceCount: { type: Number, default: 0 },
});

const open = defineModel('open', { type: Boolean, default: false });
const search = defineModel('search', { type: String, default: '' });

const emit = defineEmits(['dragstart', 'insert', 'workspace-open', 'insert-section']);

// Filter struktur dokumen mengikuti kata kunci pencarian agar placeholder
// "Cari blok atau struktur..." berfungsi konsisten.
const filteredSections = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.sections;
    return props.sections
        .map((doc) => ({
            ...doc,
            sections: doc.sections.filter(
                (s) => s.label.toLowerCase().includes(q) || s.id.toLowerCase().includes(q)
            ),
        }))
        .filter((doc) => doc.sections.length > 0 || doc.label.toLowerCase().includes(q));
});
</script>

<template>
    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-72 max-w-[85vw] flex-col border-r border-neutral-200 bg-white transition-transform duration-200 print:hidden dark:border-neutral-800 dark:bg-neutral-950 lg:static lg:z-auto lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <!-- Header -->
        <div class="flex items-start justify-between gap-2 border-b border-neutral-200 px-4 py-3.5 dark:border-neutral-800">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-950">
                    <Layers class="h-4 w-4" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Blok Konten</p>
                    <p class="mt-0.5 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">Geser untuk posisi bebas, atau klik untuk sisipkan.</p>
                </div>
            </div>
            <button
                type="button"
                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800 dark:hover:text-white lg:hidden"
                aria-label="Tutup"
                @click="open = false"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Search -->
        <div class="border-b border-neutral-200 p-3 dark:border-neutral-800">
            <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-600" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari blok atau struktur..."
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 py-2 pl-8 pr-8 text-sm outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:border-neutral-400 dark:focus:bg-neutral-950"
                />
                <button
                    v-if="search"
                    type="button"
                    class="absolute right-2 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 cursor-pointer items-center justify-center rounded text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200"
                    aria-label="Hapus pencarian"
                    @click="search = ''"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <!-- Konten scroll -->
        <div class="flex-1 space-y-5 overflow-y-auto p-3">
            <!-- Grup blok -->
            <div v-for="group in groups" :key="group.id">
                <p class="mb-1.5 px-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">{{ group.label }}</p>
                <div class="space-y-1">
                    <div
                        v-for="type in group.types"
                        :key="type.id"
                        draggable="true"
                        class="group flex w-full cursor-grab items-center gap-2.5 rounded-lg border border-transparent px-2 py-1.5 text-left transition-colors hover:border-neutral-200 hover:bg-neutral-50 active:cursor-grabbing dark:hover:border-neutral-800 dark:hover:bg-neutral-900"
                        @dragstart="emit('dragstart', $event, type)"
                        @click="emit('insert', type)"
                    >
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-500 transition-colors group-hover:bg-white group-hover:text-neutral-900 dark:bg-neutral-800 dark:text-neutral-400 dark:group-hover:bg-neutral-950 dark:group-hover:text-white">
                            <component :is="type.icon" class="h-3.5 w-3.5" />
                        </span>
                        <span class="truncate text-[13px] text-neutral-700 dark:text-neutral-200">{{ type.label }}</span>
                        <GripVertical class="ml-auto h-4 w-4 shrink-0 text-neutral-300 opacity-0 transition-opacity group-hover:opacity-100 dark:text-neutral-600" />
                    </div>
                </div>
            </div>

            <!-- Struktur dokumen (preset per tipe) -->
            <div v-if="filteredSections.length">
                <p class="mb-1.5 px-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Struktur Dokumen</p>
                <div v-for="doc in filteredSections" :key="doc.id" class="mb-3">
                    <div class="mb-1 flex items-center gap-1.5 px-1">
                        <component :is="doc.icon" class="h-3.5 w-3.5 text-neutral-400 dark:text-neutral-600" />
                        <p class="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">{{ doc.label }}</p>
                    </div>
                    <div class="space-y-1">
                        <button
                            v-for="s in doc.sections"
                            :key="s.id"
                            type="button"
                            class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg border border-transparent px-2 py-1.5 text-left transition-colors hover:border-neutral-200 hover:bg-neutral-50 dark:hover:border-neutral-800 dark:hover:bg-neutral-900"
                            @click="emit('insert-section', s.id)"
                        >
                            <component :is="s.icon" class="h-3.5 w-3.5 shrink-0 text-neutral-400 dark:text-neutral-600" />
                            <span class="truncate text-[13px] text-neutral-700 dark:text-neutral-200">{{ s.label }}</span>
                            <span class="ml-auto shrink-0 rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">{{ s.blocks.length }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Integration -->
            <div>
                <p class="mb-1.5 px-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">Integration</p>
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg border border-neutral-200 bg-neutral-50 px-2.5 py-2 text-left transition-colors hover:border-neutral-300 hover:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-neutral-700 dark:hover:bg-neutral-950"
                    @click="emit('workspace-open')"
                >
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white text-neutral-600 shadow-sm dark:bg-neutral-800 dark:text-neutral-300">
                        <Library class="h-3.5 w-3.5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[13px] font-medium text-neutral-700 dark:text-neutral-200">Workspace</span>
                        <span class="block text-[11px] text-neutral-400 dark:text-neutral-500">{{ workspaceCount }} referensi</span>
                    </span>
                </button>
            </div>

            <p v-if="groups.length === 0 && sections.length === 0" class="px-1 py-6 text-center text-xs text-neutral-400 dark:text-neutral-500">
                Blok tidak ditemukan.
            </p>
        </div>
    </aside>
</template>
