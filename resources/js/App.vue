<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const loading = ref(false);

// Tampilkan progress bar tipis di atas selama navigasi (termasuk saat
// lazy-load chunk halaman masih diunduh).
router.beforeEach(() => {
    loading.value = true;
});
router.afterEach(() => {
    loading.value = false;
});
router.onError(() => {
    loading.value = false;
});
</script>

<template>
    <div>
        <div v-if="loading" class="page-progress">
            <span class="page-progress__bar"></span>
        </div>
        <RouterView />
    </div>
</template>

<style scoped>
.page-progress {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    z-index: 99999;
    overflow: hidden;
    pointer-events: none;
}

.page-progress__bar {
    display: block;
    width: 40%;
    height: 100%;
    border-radius: 999px;
    background: #0a0a0a;
    animation: page-progress-slide 1s ease-in-out infinite;
}

:global(html.dark) .page-progress__bar {
    background: #ffffff;
}

@keyframes page-progress-slide {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(360%);
    }
}
</style>
