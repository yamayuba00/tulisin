<script setup>
const props = defineProps({
    label: { type: String, required: true },
    tone: { type: String, default: 'neutral' }, // neutral | success | warning | danger | info
});

const tones = {
    neutral: 'border-neutral-300 text-neutral-600 dark:border-neutral-600 dark:text-neutral-300',
    success: 'border-emerald-300 text-emerald-700 dark:border-emerald-700 dark:text-emerald-300',
    warning: 'border-amber-300 text-amber-700 dark:border-amber-700 dark:text-amber-300',
    danger: 'border-red-300 text-red-700 dark:border-red-700 dark:text-red-300',
    info: 'border-blue-300 text-blue-700 dark:border-blue-700 dark:text-blue-300',
};
</script>

<template>
    <span
        class="inline-flex whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="tones[tone] || tones.neutral"
    >
        {{ label }}
    </span>
</template>
