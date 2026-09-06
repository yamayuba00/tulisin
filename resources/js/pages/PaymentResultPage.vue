<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRight, CheckCircle2, RefreshCw, XCircle } from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();

const isSuccess = computed(() => route.name === 'payment-success');

const countdown = ref(5);
let timer = null;

function goTopup() {
    router.push('/apps/u/topup');
}

onMounted(() => {
    timer = setInterval(() => {
        countdown.value -= 1;
        if (countdown.value <= 0) {
            clearInterval(timer);
            timer = null;
            goTopup();
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
});
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-white px-4 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <div class="w-full max-w-md text-center">
            <!-- Ikon status -->
            <div class="relative mx-auto flex h-24 w-24 items-center justify-center">
                <span
                    v-if="isSuccess"
                    class="absolute inset-0 rounded-full bg-emerald-500/30 animate-ring"
                ></span>
                <span
                    v-if="!isSuccess"
                    class="absolute inset-0 rounded-full bg-amber-500/30 animate-ring"
                ></span>

                <div
                    class="relative flex h-20 w-20 items-center justify-center rounded-full border animate-pop"
                    :class="isSuccess
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-400'
                        : 'border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-400'"
                >
                    <CheckCircle2 v-if="isSuccess" class="h-11 w-11" />
                    <XCircle v-else class="h-11 w-11" />
                </div>
            </div>

            <h1 class="mt-8 font-serif text-3xl font-bold tracking-tight">
                {{ isSuccess ? 'Pembayaran Berhasil!' : 'Pembayaran Belum Selesai' }}
            </h1>

            <p class="mx-auto mt-3 max-w-sm text-neutral-500 dark:text-neutral-400">
                <template v-if="isSuccess">
                    Terima kasih! Saldo koin kamu sedang diproses dan akan segera bertambah.
                    Selamat melanjutkan karya ilmiahmu.
                </template>
                <template v-else>
                    Sepertinya pembayaran kamu dibatalkan atau gagal. Tenang, saldo koinmu
                    tidak berkurang — kamu bisa mencobanya lagi kapan saja.
                </template>
            </p>

            <div class="mt-8 flex flex-col items-center gap-4">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-900 bg-neutral-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                    @click="goTopup"
                >
                    <RefreshCw v-if="!isSuccess" class="h-4 w-4" />
                    <ArrowRight v-else class="h-4 w-4" />
                    {{ isSuccess ? 'Kembali ke Topup' : 'Coba Lagi' }}
                </button>

                <p class="text-xs text-neutral-400 dark:text-neutral-500">
                    Mengalihkan ke halaman Topup dalam
                    <span class="font-semibold text-neutral-600 dark:text-neutral-300">{{ countdown }}</span>
                    detik…
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes pop-in {
    0% {
        transform: scale(0.3);
        opacity: 0;
    }
    60% {
        transform: scale(1.15);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}
.animate-pop {
    animation: pop-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes ring-pulse {
    0% {
        transform: scale(1);
        opacity: 0.6;
    }
    100% {
        transform: scale(1.8);
        opacity: 0;
    }
}
.animate-ring {
    animation: ring-pulse 1.6s ease-out infinite;
}
</style>
