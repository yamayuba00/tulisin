<script setup>
import { computed, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { Lock, CheckCircle2 } from 'lucide-vue-next';
import AuthLayout from './AuthLayout.vue';
import { useAuth } from '../utils/auth';

const route = useRoute();
const { confirmResetPassword } = useAuth();

const token = computed(() => (Array.isArray(route.query.token) ? route.query.token[0] : route.query.token) || '');
const email = computed(() => (Array.isArray(route.query.email) ? route.query.email[0] : route.query.email) || '');

const password = ref('');
const passwordConfirmation = ref('');
const error = ref('');
const done = ref(false);
const loading = ref(false);

async function submit() {
    error.value = '';
    if (!password.value || !passwordConfirmation.value) {
        error.value = 'Password dan konfirmasi wajib diisi.';
        return;
    }
    if (password.value.length < 6) {
        error.value = 'Password minimal 6 karakter.';
        return;
    }
    if (password.value !== passwordConfirmation.value) {
        error.value = 'Konfirmasi password tidak cocok.';
        return;
    }

    loading.value = true;
    const result = await confirmResetPassword({
        token: token.value,
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
    });
    loading.value = false;

    if (!result.ok) {
        error.value = result.error;
        return;
    }
    done.value = true;
}
</script>

<template>
    <AuthLayout>
        <template v-if="!done">
            <h2 class="text-center text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white">Reset Password</h2>
            <p class="mt-1.5 text-center text-sm text-neutral-500 dark:text-neutral-400">Buat kata sandi baru untuk akun kamu.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Password Baru</label>
                    <div class="relative mt-1">
                        <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                        <input
                            v-model="password"
                            type="password"
                            placeholder="Minimal 6 karakter"
                            class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                        />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Konfirmasi Password</label>
                    <div class="relative mt-1">
                        <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                        <input
                            v-model="passwordConfirmation"
                            type="password"
                            placeholder="Ulangi password"
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
                    {{ loading ? 'Menyimpan...' : 'Simpan Password Baru' }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
                <RouterLink to="/login" class="font-medium text-neutral-900 hover:underline dark:text-white">Kembali ke Login</RouterLink>
            </p>
        </template>

        <template v-else>
            <div class="flex flex-col items-center text-center">
                <CheckCircle2 class="h-12 w-12 text-emerald-500" />
                <h2 class="mt-4 text-2xl font-semibold">Password Berhasil Diubah</h2>
                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Silakan masuk dengan password barumu.</p>
                <RouterLink to="/login" class="mt-6 font-medium text-neutral-900 hover:underline dark:text-white">Masuk Sekarang</RouterLink>
            </div>
        </template>
    </AuthLayout>
</template>
