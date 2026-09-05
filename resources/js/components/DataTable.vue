<script setup>
import { computed, ref, watch, useSlots } from 'vue';
import { Loader2, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    // [{ key, label, align?, width? }]
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyText: { type: String, default: 'Tidak ada data.' },
    keyField: { type: String, default: 'id' },
    perPage: { type: Number, default: 10 },
});

const slots = useSlots();
const page = ref(1);

function alignClass(align) {
    return align === 'center' ? 'text-center' : align === 'right' ? 'text-right' : 'text-left';
}

const total = computed(() => props.rows.length);
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / props.perPage)));
const visibleRows = computed(() => {
    const start = (page.value - 1) * props.perPage;
    return props.rows.slice(start, start + props.perPage);
});

const from = computed(() => (total.value === 0 ? 0 : (page.value - 1) * props.perPage + 1));
const to = computed(() => Math.min(page.value * props.perPage, total.value));

// Saat data/filter berubah, kembali ke halaman pertama.
watch(
    () => props.rows,
    () => {
        if (page.value > totalPages.value) page.value = totalPages.value;
        else if (page.value < 1) page.value = 1;
    },
);

function prevPage() {
    if (page.value > 1) page.value -= 1;
}

function nextPage() {
    if (page.value < totalPages.value) page.value += 1;
}

const totalCols = () => props.columns.length + (slots.actions ? 1 : 0);
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900/50">
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400"
                            :class="alignClass(col.align)"
                        >
                            {{ col.label }}
                        </th>
                        <th
                            v-if="$slots.actions"
                            class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td :colspan="totalCols()" class="px-4 py-14 text-center text-neutral-400 dark:text-neutral-500">
                            <Loader2 class="mx-auto h-5 w-5 animate-spin" />
                        </td>
                    </tr>

                    <tr v-else-if="visibleRows.length === 0">
                        <td :colspan="totalCols()" class="px-4 py-14 text-center text-neutral-400 dark:text-neutral-500">
                            {{ emptyText }}
                        </td>
                    </tr>

                    <tr
                        v-for="row in visibleRows"
                        :key="row[keyField]"
                        class="border-b border-neutral-100 transition-colors last:border-0 hover:bg-neutral-50 dark:border-neutral-900 dark:hover:bg-neutral-900/40"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-3 text-neutral-700 dark:text-neutral-200"
                            :class="alignClass(col.align)"
                        >
                            <slot :name="'cell-' + col.key" :row="row" :value="row[col.key]">
                                {{ col.format ? col.format(row[col.key], row) : row[col.key] }}
                            </slot>
                        </td>
                        <td v-if="$slots.actions" class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <slot name="actions" :row="row" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="total > perPage"
            class="flex flex-col gap-2 border-t border-neutral-200 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800"
        >
            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                Menampilkan {{ from }}–{{ to }} dari {{ total }} data
            </p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    :disabled="page <= 1"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-100 disabled:pointer-events-none disabled:opacity-40 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="prevPage"
                >
                    <ChevronLeft class="h-3.5 w-3.5" /> Sebelumnya
                </button>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ page }} / {{ totalPages }}</span>
                <button
                    type="button"
                    :disabled="page >= totalPages"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:bg-neutral-100 disabled:pointer-events-none disabled:opacity-40 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="nextPage"
                >
                    Berikutnya <ChevronRight class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </div>
</template>
