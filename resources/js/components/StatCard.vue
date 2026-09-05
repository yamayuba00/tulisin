<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-lg border border-neutral-200 p-5 transition hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700">
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ label }}</p>
        <p class="mt-2 text-2xl font-bold">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">{{ hint }}</p>
    </div>
</template>
