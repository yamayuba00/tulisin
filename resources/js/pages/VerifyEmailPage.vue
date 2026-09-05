<script setup>
import { computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { CheckCircle2, XCircle, MailCheck } from 'lucide-vue-next';
import AuthLayout from './AuthLayout.vue';

const route = useRoute();
const status = computed(() => (Array.isArray(route.query.status) ? route.query.status[0] : route.query.status) || '');

const view = computed(() => {
    switch (status.value) {
        case 'success':
            return { icon: CheckCircle2, tone: 'text-emerald-500', title: 'Email Terverifikasi', desc: 'Email kamu berhasil diverifikasi. Sekarang akunmu sudah aktif sepenuhnya.' };
        case 'already':
            return { icon: MailCheck, tone: 'text-emerald-500', title: 'Sudah Terverifikasi', desc: 'Email ini sudah diverifikasi sebelumnya. Tidak perlu tindakan lebih lanjut.' };
        default:
            return { icon: XCircle, tone: 'text-red-500', title: 'Tautan Tidak Valid', desc: 'Tautan verifikasi tidak valid atau sudah kedaluwarsa. Silakan minta kirim ulang email verifikasi.' };
    }
});
</script>

<template>
    <AuthLayout>
        <div class="flex flex-col items-center text-center">
            <component :is="view.icon" class="h-14 w-14" :class="view.tone" />
            <h2 class="mt-4 text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white">{{ view.title }}</h2>
            <p class="mt-2 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">{{ view.desc }}</p>
            <RouterLink to="/login" class="mt-6 font-medium text-neutral-900 hover:underline dark:text-white">Kembali ke Login</RouterLink>
        </div>
    </AuthLayout>
</template>
