<script setup>
import { computed, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { CheckCircle2, XCircle, MailCheck, Mail, Loader2 } from 'lucide-vue-next';
import AuthLayout from './AuthLayout.vue';
import { useAuth } from '../utils/auth';

const route = useRoute();
const { resendVerification } = useAuth();

const status = computed(() => (Array.isArray(route.query.status) ? route.query.status[0] : route.query.status) || '');
const email = computed(() => (Array.isArray(route.query.email) ? route.query.email[0] : route.query.email) || '');

const resending = ref(false);
const resendSent = ref(false);
const resendError = ref('');

const view = computed(() => {
    switch (status.value) {
        case 'sent':
            return {
                icon: Mail,
                tone: 'text-neutral-900 dark:text-white',
                title: 'Cek Email Kamu',
                desc: 'Kami telah mengirim email verifikasi ke',
                resend: true,
            };
        case 'success':
            return { icon: CheckCircle2, tone: 'text-emerald-500', title: 'Email Terverifikasi', desc: 'Email kamu berhasil diverifikasi. Sekarang akunmu sudah aktif sepenuhnya.' };
        case 'already':
            return { icon: MailCheck, tone: 'text-emerald-500', title: 'Sudah Terverifikasi', desc: 'Email ini sudah diverifikasi sebelumnya. Tidak perlu tindakan lebih lanjut.' };
        default:
            return {
                icon: XCircle,
                tone: 'text-red-500',
                title: 'Tautan Tidak Valid',
                desc: 'Tautan verifikasi tidak valid atau sudah kedaluwarsa. Silakan minta kirim ulang email verifikasi.',
                resend: true,
            };
    }
});

async function resend() {
    resending.value = true;
    resendError.value = '';
    resendSent.value = false;

    const result = await resendVerification();
    resending.value = false;

    if (!result.ok) {
        resendError.value = result.error;
        return;
    }

    resendSent.value = true;
}
</script>

<template>
    <AuthLayout>
        <div class="flex flex-col items-center text-center">
            <component :is="view.icon" class="h-14 w-14" :class="view.tone" />
            <h2 class="mt-4 text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white">{{ view.title }}</h2>

            <p class="mt-2 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
                {{ view.desc }}
                <span v-if="status === 'sent' && email" class="font-medium text-neutral-900 dark:text-white">{{ email }}</span>
                <template v-if="status === 'sent'">. Klik tombol verifikasi di email untuk mengaktifkan akunmu.</template>
            </p>

            <template v-if="view.resend">
                <button
                    type="button"
                    :disabled="resending"
                    class="mt-6 inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-neutral-900 bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 disabled:pointer-events-none disabled:opacity-60 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                    @click="resend"
                >
                    <Loader2 v-if="resending" class="h-4 w-4 animate-spin" />
                    {{ resending ? 'Mengirim...' : 'Kirim Ulang Email Verifikasi' }}
                </button>

                <p v-if="resendSent" class="mt-3 text-sm text-emerald-600 dark:text-emerald-400">Email verifikasi telah dikirim ulang. Cek kotak masuk kamu.</p>
                <p v-if="resendError" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ resendError }}</p>
            </template>

            <RouterLink to="/login" class="mt-6 font-medium text-neutral-900 hover:underline dark:text-white">Kembali ke Login</RouterLink>
        </div>
    </AuthLayout>
</template>
