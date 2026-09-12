<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { cn } from '../utils/format';

const props = defineProps({
    to: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Object, Function], required: true },
    soon: { type: Boolean, default: false },
});

const route = useRoute();

const active = computed(() => route.path === props.to || route.path.startsWith(props.to + '/'));

const classes = computed(() =>
    cn(
        'mx-2 flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
        active.value
            ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
            : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-900 dark:hover:text-white',
    ),
);
</script>

<template>
    <RouterLink :to="to" :class="classes">
        <component :is="icon" class="h-5 w-5 shrink-0" />
        <span class="truncate">{{ label }}</span>
        <span
            v-if="soon"
            class="ml-auto shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"
        >
            Segera
        </span>
    </RouterLink>
</template>
