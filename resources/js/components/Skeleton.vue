<script setup>
defineProps({
    // Jika true, blok berbentuk lingkaran (avatar/ikon).
    circle: { type: Boolean, default: false },
});
</script>

<template>
    <span
        aria-hidden="true"
        class="block animate-pulse rounded-md bg-neutral-200/80 dark:bg-neutral-800"
        :class="circle ? 'rounded-full' : ''"
    />
</template>