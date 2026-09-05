<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { Mail, CheckCircle2 } from 'lucide-vue-next';
import AuthLayout from './AuthLayout.vue';
import { useAuth } from '../utils/auth';

const { resetPassword } = useAuth();

const email = ref('');
const error = ref('');
const sent = ref(false);
const loading = ref(false);

async function submit() {
    error.value = '';
    if (!email.value.trim()) {
        error.value = 'Email wajib diisi.';
        return;
    }
    loading.value = true;
    const result = await resetPassword(email.value);
    loading.value = false;

    if (!result.ok) {
        error.value = result.error;
        return;
    }
    sent.value = true;
}
</script>

<template>
    <AuthLayout>
        <template v-if="!sent">
            <h2 class="text-center text-2xl font-semibold tracking-tight">Lupa Password</h2>
            <p class="mt-1.5 text-center text-sm text-neutral-500 dark:text-neutral-400">Masukkan email, kami akan kirim link reset.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Email</label>
                    <div class="relative mt-1">
                        <Mail class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                        <input
                            v-model="email"
                            type="email"
                            placeholder="nama@email.com"
                            class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                        />
                    </div>
                </div>

                <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400">
                    {{ error }}
                </p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full cursor-pointer rounded-xl border border-neutral-900 bg-neutral-900 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-neutral-800 disabled:pointer-events-none disabled:opacity-60 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                >
                    {{ loading ? 'Mengirim...' : 'Kirim Link Reset' }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
                <RouterLink to="/login" class="font-medium text-neutral-900 hover:underline dark:text-white">Kembali ke Login</RouterLink>
            </p>
        </template>

        <template v-else>
            <div class="flex flex-col items-center text-center">
                <CheckCircle2 class="h-12 w-12 text-emerald-500" />
                <h2 class="mt-4 text-2xl font-semibold">Cek Email Kamu</h2>
                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                    Link reset password (simulasi) telah dikirim ke <span class="font-medium text-neutral-900 dark:text-white">{{ email }}</span>.
                </p>
                <RouterLink to="/login" class="mt-6 font-medium text-neutral-900 hover:underline dark:text-white">Kembali ke Login</RouterLink>
            </div>
        </template>
    </AuthLayout>
</template>
