<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ description }}</p>
        </div>
        <slot name="action" />
    </header>
</template>
