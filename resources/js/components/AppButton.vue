<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { cn } from '../utils/format';

const props = defineProps({
    to: { type: String, default: null },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    block: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

// Semua tombol memakai gaya outline (hitam/putih saja).
const variants = {
    primary: 'border-neutral-900 text-neutral-900 hover:bg-neutral-900 hover:text-white dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-neutral-950',
    outline: 'border-neutral-300 text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800',
};

const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-sm',
    lg: 'px-5 py-2.5 text-base',
};

const classes = computed(() =>
    cn(
        'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border font-medium transition-colors',
        variants[props.variant] ?? variants.primary,
        sizes[props.size] ?? sizes.md,
        props.block && 'w-full',
        props.disabled && 'pointer-events-none opacity-50',
    ),
);
</script>

<template>
    <component
        :is="to ? RouterLink : 'button'"
        :to="to || undefined"
        :type="to ? undefined : 'button'"
        :disabled="to ? undefined : disabled"
        :class="classes"
    >
        <slot />
    </component>
</template>
