<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { cn } from '../utils/format';

const props = defineProps({
    to: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Object, Function], required: true },
});

const route = useRoute();

const active = computed(() => route.path === props.to || route.path.startsWith(props.to + '/'));

const classes = computed(() =>
    cn(
        'flex cursor-pointer items-center gap-3 border-r-2 px-4 py-2.5 text-sm font-medium transition-colors',
        active.value
            ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white'
            : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white',
    ),
);
</script>

<template>
    <RouterLink :to="to" :class="classes">
        <component :is="icon" class="h-5 w-5 shrink-0" />
        {{ label }}
    </RouterLink>
</template>
