<script setup>
import { computed } from 'vue';

const props = defineProps({
    watermark: { type: Object, default: () => ({}) },
});

const enabled = computed(() => !!props.watermark?.enabled);
const isImage = computed(() => props.watermark?.type === 'image');

const opacity = computed(() => {
    const v = Number(props.watermark?.opacity);
    if (Number.isNaN(v)) return 0.15;
    return Math.max(0, Math.min(1, v));
});

const rotation = computed(() => {
    const v = Number(props.watermark?.rotation);
    return Number.isNaN(v) ? 0 : v;
});

const textStyle = computed(() => ({
    color: props.watermark?.color || '#b0b0b0',
    fontSize: `${Number(props.watermark?.fontSize) || 48}pt`,
    transform: `rotate(${rotation.value}deg)`,
}));

const imageStyle = computed(() => ({
    width: `${Number(props.watermark?.imageWidth) || 300}px`,
    transform: `rotate(${rotation.value}deg)`,
}));
</script>

<template>
    <div
        v-if="enabled"
        class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center overflow-hidden"
        :style="{ opacity }"
        aria-hidden="true"
    >
        <img
            v-if="isImage && watermark.image"
            :src="watermark.image"
            alt=""
            :style="imageStyle"
            class="select-none"
        />
        <span
            v-else
            :style="textStyle"
            class="select-none whitespace-pre-wrap text-center font-semibold leading-none"
        >{{ watermark.text }}</span>
    </div>
</template>
