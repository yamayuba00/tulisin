<script setup>
import Skeleton from './Skeleton.vue';

defineProps({
    // Jumlah baris placeholder.
    rows: { type: Number, default: 5 },
    // Sembunyikan kolom aksi di kanan.
    actions: { type: Boolean, default: true },
    // Jumlah kolom teks sebelum kolom aksi.
    columns: { type: Number, default: 4 },
});
</script>

<template>
    <div class="divide-y divide-neutral-100 dark:divide-neutral-800" aria-busy="true">
        <div
            v-for="r in rows"
            :key="r"
            class="flex items-center gap-4 px-4 py-3.5"
        >
            <Skeleton class="h-9 w-9 shrink-0" circle />
            <Skeleton
                v-for="c in columns"
                :key="c"
                class="h-4"
                :class="c === 1 ? 'flex-1 min-w-0' : 'w-24 shrink-0'"
            />
            <Skeleton v-if="actions" class="ml h-8 w-20 shrink-0" />
        </div>
    </div>
</template>