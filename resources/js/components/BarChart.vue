<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ label, value }]
    items: { type: Array, default: () => [] },
    color: { type: String, default: 'bg-blue-500/80' },
    hoverColor: { type: String, default: 'hover:bg-blue-500' },
    height: { type: String, default: '180px' },
    labelStep: { type: Number, default: 1 },
});

const max = computed(() => Math.max(1, ...props.items.map((p) => Number(p.value) || 0)));

function barHeight(v) {
    return `${Math.max(2, ((Number(v) || 0) / max.value) * 100)}%`;
}

function showLabel(i) {
    const total = props.items.length;
    if (props.labelStep <= 1) return true;
    return i % props.labelStep === 0 || i === total - 1;
}
</script>

<template>
    <div v-if="items.length">
        <div class="flex items-end gap-1" :style="{ height }">
            <div
                v-for="(p, i) in items"
                :key="i"
                class="flex h-full flex-1 flex-col justify-end"
                :title="`${p.label}: ${p.value}`"
            >
                <div class="w-full rounded-t-sm transition-colors" :class="[color, hoverColor]" :style="{ height: barHeight(p.value) }"></div>
            </div>
        </div>
        <div class="mt-1 flex gap-1">
            <div
                v-for="(p, i) in items"
                :key="i"
                class="flex-1 truncate text-center text-[10px] text-neutral-400 dark:text-neutral-500"
            >
                {{ showLabel(i) ? p.label : '' }}
            </div>
        </div>
    </div>
    <p v-else class="py-6 text-center text-sm text-neutral-400 dark:text-neutral-500">Belum ada data.</p>
</template>
