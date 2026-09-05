<script setup>
import { ref } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { Mail, Lock, User, Eye, EyeOff, GraduationCap, Building2, Phone, Target } from 'lucide-vue-next';
import AuthLayout from './AuthLayout.vue';
import SearchableSelect from '../components/SearchableSelect.vue';
import { useAuth } from '../utils/auth';

const router = useRouter();
const route = useRoute();
const { register } = useAuth();

const referralRef = ref(typeof route.query.ref === 'string' ? route.query.ref : '');

const name = ref('');
const email = ref('');
const phone = ref('');
const accountType = ref('individual'); // 'individual' | 'agency'
const university = ref('');
const agencyName = ref('');
const interest = ref('');
const subscribeInfo = ref(false);
const subscribeProduct = ref(false);
const password = ref('');
const confirm = ref('');
const showPassword = ref(false);
const error = ref('');
const loading = ref(false);

// Daftar kampus umum. Jika tidak ada, pengguna pilih "Lainnya" lalu isi manual.
const UNIVERSITIES = [
    'Universitas Indonesia',
    'Universitas Gadjah Mada',
    'Institut Teknologi Bandung',
    'Universitas Airlangga',
    'Universitas Diponegoro',
    'Universitas Padjadjaran',
    'Institut Teknologi Sepuluh Nopember',
    'Universitas Brawijaya',
    'Universitas Sebelas Maret',
    'Universitas Hasanuddin',
    'Universitas Sumatera Utara',
    'Universitas Andalas',
    'Universitas Bina Nusantara',
    'Universitas Telkom',
    'Universitas Gunadarma',
];

// Kebutuhan / produk yang dicari. Jika tidak ada, pilih "Lainnya".
const INTERESTS = [
    'Skripsi',
    'Tesis',
    'Disertasi',
    'Jurnal / Paper',
    'Makalah',
    'Olah Data',
    'Parafrase & Cek Plagiarisme',
    'Turnitin AI Optimizer',
];

async function submit() {
    error.value = '';

    if (!name.value.trim() || !email.value.trim() || !password.value) {
        error.value = 'Nama, email, dan password wajib diisi.';
        return;
    }
    const phoneDigits = phone.value.replace(/\D/g, '');
    if (!phoneDigits) {
        error.value = 'Nomor telepon wajib diisi.';
        return;
    }
    if (phoneDigits.length < 9 || phoneDigits.length > 15) {
        error.value = 'Nomor telepon tidak valid.';
        return;
    }
    if (!university.value) {
        error.value = 'Pilih kampus / universitas kamu.';
        return;
    }
    if (accountType.value === 'agency' && !agencyName.value.trim()) {
        error.value = 'Isi nama instansi / agency kamu.';
        return;
    }
    if (!interest.value) {
        error.value = 'Pilih kebutuhan / produk kamu.';
        return;
    }
    if (password.value.length < 6) {
        error.value = 'Password minimal 6 karakter.';
        return;
    }
    if (password.value !== confirm.value) {
        error.value = 'Konfirmasi password tidak cocok.';
        return;
    }

    const resolvedUniversity = university.value;
    const resolvedInterest = interest.value;

    loading.value = true;
    const result = await register({
        name: name.value,
        email: email.value,
        phone: phone.value.trim(),
        password: password.value,
        password_confirmation: confirm.value,
        accountType: accountType.value,
        university: resolvedUniversity,
        agencyName: accountType.value === 'agency' ? agencyName.value.trim() : '',
        interest: resolvedInterest,
        subscribeInfo: subscribeInfo.value,
        subscribeProduct: subscribeProduct.value,
        ref: referralRef.value || undefined,
    });
    loading.value = false;

    if (!result.ok) {
        error.value = result.error;
        return;
    }

    router.push('/apps/u/dashboard');
}
</script>

<template>
    <AuthLayout>
        <h2 class="text-center text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white">Buat akun</h2>
        <p class="mt-1.5 text-center text-sm text-neutral-500 dark:text-neutral-400">Mulai tulis dokumen pertamamu hari ini.</p>

        <p v-if="referralRef" class="mt-3 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-center text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
            Kamu mendaftar lewat link referral <span class="font-mono">{{ referralRef }}</span>
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nama Lengkap <span class="text-red-500">*</span></label>
                <div class="relative mt-1">
                    <User class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    <input
                        v-model="name"
                        type="text"
                        placeholder="Nama kamu"
                        class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                    />
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Email <span class="text-red-500">*</span></label>
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
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nomor Telepon <span class="text-red-500">*</span></label>
                <div class="relative mt-1">
                    <Phone class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    <input
                        v-model="phone"
                        type="tel"
                        inputmode="tel"
                        placeholder="08xx xxxx xxxx"
                        class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                    />
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Tipe Akun <span class="text-red-500">*</span></label>
                <div class="mt-1 grid grid-cols-2 gap-1 rounded-xl border border-neutral-200 p-1 dark:border-neutral-800">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg py-2 text-sm font-medium transition-colors"
                        :class="accountType === 'individual' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'"
                        @click="accountType = 'individual'"
                    >
                        <User class="h-4 w-4" />
                        Individual
                    </button>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg py-2 text-sm font-medium transition-colors"
                        :class="accountType === 'agency' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'"
                        @click="accountType = 'agency'"
                    >
                        <Building2 class="h-4 w-4" />
                        Agency
                    </button>
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kampus / Universitas <span class="text-red-500">*</span></label>
                <SearchableSelect v-model="university" :options="UNIVERSITIES" placeholder="Pilih atau ketik kampus" class="mt-1">
                    <template #icon>
                        <GraduationCap class="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    </template>
                </SearchableSelect>
            </div>

            <div v-if="accountType === 'agency'">
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nama Instansi / Agency <span class="text-red-500">*</span></label>
                <div class="relative mt-1">
                    <Building2 class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    <input
                        v-model="agencyName"
                        type="text"
                        placeholder="Nama agency kamu"
                        class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                    />
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kebutuhan / Produk <span class="text-red-500">*</span></label>
                <SearchableSelect v-model="interest" :options="INTERESTS" placeholder="Pilih atau ketik kebutuhan" class="mt-1">
                    <template #icon>
                        <Target class="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    </template>
                </SearchableSelect>
            </div>

            <div>
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Password <span class="text-red-500">*</span></label>
                <div class="relative mt-1">
                    <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    <input
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="Minimal 6 karakter"
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
                <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Konfirmasi Password <span class="text-red-500">*</span></label>
                <div class="relative mt-1">
                    <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                    <input
                        v-model="confirm"
                        type="password"
                        placeholder="Ulangi password"
                        class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                    />
                </div>
            </div>

            <div class="space-y-2 rounded-xl border border-neutral-200 p-3 dark:border-neutral-800">
                <p class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Langganan (opsional)</p>
                <label class="flex cursor-pointer items-start gap-2 text-sm">
                    <input
                        v-model="subscribeInfo"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 cursor-pointer rounded border-neutral-300 accent-neutral-900 dark:accent-white"
                    />
                    <span class="text-neutral-600 dark:text-neutral-300">Dapatkan info & tips penulisan akademik</span>
                </label>
                <label class="flex cursor-pointer items-start gap-2 text-sm">
                    <input
                        v-model="subscribeProduct"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 cursor-pointer rounded border-neutral-300 accent-neutral-900 dark:accent-white"
                    />
                    <span class="text-neutral-600 dark:text-neutral-300">Dapatkan info promo & produk terbaru</span>
                </label>
            </div>

            <p class="text-xs text-neutral-400 dark:text-neutral-500">Kolom bertanda <span class="text-red-500">*</span> wajib diisi.</p>

            <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400">
                {{ error }}
            </p>

            <button
                type="submit"
                :disabled="loading"
                class="w-full cursor-pointer rounded-xl border border-neutral-900 bg-neutral-900 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-neutral-800 disabled:pointer-events-none disabled:opacity-60 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
            >
                {{ loading ? 'Memproses...' : 'Daftar' }}
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
            Sudah punya akun?
            <RouterLink to="/login" class="font-medium text-neutral-900 hover:underline dark:text-white">Masuk</RouterLink>
        </p>
    </AuthLayout>
</template>
