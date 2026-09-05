<script setup>
import { Sun, Moon } from 'lucide-vue-next';
import { useTheme } from '../utils/theme';

const { theme, toggle } = useTheme();
</script>

<template>
    <button
        type="button"
        class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
        :aria-label="theme === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
        @click="toggle"
    >
        <Sun v-if="theme === 'dark'" class="h-5 w-5" />
        <Moon v-else class="h-5 w-5" />
    </button>
</template>
