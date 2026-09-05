<script setup>
import { Construction } from 'lucide-vue-next';

defineProps({
    title: { type: String, default: 'Coming Soon' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-neutral-300 px-6 py-20 text-center dark:border-neutral-700">
        <div class="flex h-14 w-14 items-center justify-center rounded-full border border-neutral-200 text-neutral-400 dark:border-neutral-800 dark:text-neutral-500">
            <Construction class="h-7 w-7" />
        </div>
        <h2 class="mt-5 text-xl font-bold">{{ title }}</h2>
        <p v-if="description" class="mt-2 max-w-md text-sm text-neutral-500 dark:text-neutral-400">{{ description }}</p>
        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </div>
</template>
