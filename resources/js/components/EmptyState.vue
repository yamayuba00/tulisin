<script setup>
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, default: 'Tidak ada data' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-neutral-300 px-6 py-16 text-center dark:border-neutral-700">
        <div
            v-if="icon"
            class="flex h-12 w-12 items-center justify-center rounded-full border border-neutral-200 text-neutral-400 dark:border-neutral-800 dark:text-neutral-500"
        >
            <component :is="icon" class="h-5 w-5" />
        </div>
        <p class="mt-4 text-sm font-medium text-neutral-600 dark:text-neutral-300">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-neutral-400 dark:text-neutral-500">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
