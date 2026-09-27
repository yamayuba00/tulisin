<script setup>
import Skeleton from './Skeleton.vue';

defineProps({
    // Jumlah kartu.
    count: { type: Number, default: 4 },
    // Kelas gap antar kartu.
    gap: { type: String, default: 'gap-4' },
    // Kelas jumlah kolom grid.
    columnsClass: { type: String, default: 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' },
});
</script>

<template>
    <div
        class="grid"
        :class="[columnsClass, gap]"
        aria-busy="true"
    >
        <div
            v-for="i in count"
            :key="i"
            class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800"
        >
            <div class="flex items-start justify-between gap-2">
                <Skeleton class="h-10 w-10 shrink-0" />
                <Skeleton class="h-5 w-16 shrink-0" />
            </div>
            <Skeleton class="mt-4 h-4 w-full" />
            <Skeleton class="mt-2 h-4 w-3/4" />
            <div class="mt-4 flex items-center justify-between">
                <Skeleton class="h-4 w-20" />
                <Skeleton class="h-4 w-12" />
            </div>
        </div>
    </div>
</template>