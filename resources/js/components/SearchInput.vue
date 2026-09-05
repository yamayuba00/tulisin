<script setup>
import { Search } from 'lucide-vue-next';

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Cari...' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative">
        <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
        <input
            :value="modelValue"
            type="text"
            :placeholder="placeholder"
            class="w-full rounded-lg border border-neutral-200 bg-transparent py-2 pl-9 pr-3 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>
