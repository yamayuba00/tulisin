<script setup>
import { ref } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { Mail, Lock, Eye, EyeOff } from 'lucide-vue-next';
import AuthLayout from './AuthLayout.vue';
import Altcha from '../components/Altcha.vue';
import { useAuth } from '../utils/auth';

const router = useRouter();
const route = useRoute();
const { login, currentUser } = useAuth();

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const altchaVerified = ref(false);
const error = ref('');
const loading = ref(false);

async function submit() {
    error.value = '';

    if (!email.value.trim() || !password.value) {
        error.value = 'Email dan password wajib diisi.';
        return;
    }
    if (!altchaVerified.value) {
        error.value = 'Selesaikan verifikasi (Altcha) terlebih dahulu.';
        return;
    }

    loading.value = true;
    const result = await login({ email: email.value, password: password.value });
    loading.value = false;

    if (!result.ok) {
        error.value = result.error;
        return;
    }

    const isAdmin = !!currentUser.value?.is_super_admin;
    const fallback = isAdmin ? '/apps/u/admin/dashboard' : '/apps/u/dashboard';
    router.push(route.query.redirect || fallback);
}
</script>

<template>
    <AuthLayout>
        <h2 class="text-center text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white">Selamat datang kembali</h2>
        <p class="mt-1.5 text-center text-sm text-neutral-500 dark:text-neutral-400">Masuk untuk melanjutkan dokumenmu.</p>

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

            <div>
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Password</label>
                <div class="relative mt-1">
                    <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    <input
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="Password kamu"
                        class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-10 text-sm outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                    />
                    <button
                        type="button"
                        class="absolute right-2 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200"
                        aria-label="Tampilkan password"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Verifikasi (Altcha)</label>
                <div class="mt-1">
                    <Altcha v-model:verified="altchaVerified" />
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
                {{ loading ? 'Memproses...' : 'Masuk' }}
            </button>
        </form>

        <div class="mt-4 flex items-center justify-between text-sm">
            <RouterLink to="/forgot-password" class="text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">Lupa password?</RouterLink>
            <RouterLink to="/register" class="font-medium text-neutral-900 hover:underline dark:text-white">Daftar</RouterLink>
        </div>
    </AuthLayout>
</template>
