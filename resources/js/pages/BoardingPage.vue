<script setup>
import { computed, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { PenLine, ArrowRight, ArrowLeft, MailCheck, FolderOpen, Sparkles, Check, GraduationCap, Phone, IdCard } from 'lucide-vue-next';
import SearchableSelect from '../components/SearchableSelect.vue';
import { useAuth } from '../utils/auth';
import appName from '../utils/appName';

const router = useRouter();
const route = useRoute();
const { currentUser, updateProfile } = useAuth();

const step = ref(route.query.step === '2' ? 2 : 1);
const university = ref(currentUser.value?.profile?.university || '');
const interests = ref(splitInterests(currentUser.value?.profile?.major));
const phone = ref(currentUser.value?.phone || '');
const nim = ref(currentUser.value?.profile?.nim || '');
const degree = ref(currentUser.value?.profile?.degree || '');
const refCode = ref('');
const loading = ref(false);
const error = ref('');

// "Kebutuhanmu" mendukung pilihan ganda (Skripsi, Tesis, Disertasi, dst).
function splitInterests(value) {
    if (!value) return [];
    return String(value).split(',').map((s) => s.trim()).filter(Boolean);
}

function toggleInterest(i) {
    const idx = interests.value.indexOf(i);
    if (idx === -1) interests.value.push(i);
    else interests.value.splice(idx, 1);
}

const name = computed(() => currentUser.value?.name?.split(' ')[0] || '');
const isVerified = computed(() => !!currentUser.value?.email_verified);
const dashboardPath = computed(() =>
    currentUser.value?.is_super_admin ? '/apps/u/admin/dashboard' : '/apps/u/dashboard'
);
const hasCompleteProfile = computed(() => {
    const p = currentUser.value?.profile;
    return !!(p?.university && p?.major && currentUser.value?.phone);
});

const welcomeSteps = [
    { icon: MailCheck, title: 'Verifikasi email', desc: 'Aktifkan akunmu lewat tautan yang kami kirim ke email.' },
    { icon: FolderOpen, title: 'Buat dokumen pertama', desc: 'Pilih jenis dokumen dan mulai susun kerangka.' },
    { icon: Sparkles, title: 'Tulis dengan AI', desc: 'Minta AI kembangkan ide, rapikan kalimat, dan atur sitasi.' },
];

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

const INTERESTS = ['Skripsi', 'Tesis', 'Disertasi', 'Jurnal / Paper', 'Makalah'];

const DEGREES = ['S1 / Sarjana', 'S2 / Magister', 'S3 / Doktor', 'Profesi', 'Diploma'];

function next() {
    if (step.value === 1) {
        if (hasCompleteProfile.value) {
            router.push(dashboardPath.value);
            return;
        }
        step.value = 2;
    }
}

async function submit() {
    error.value = '';

    const phoneDigits = phone.value.replace(/\D/g, '');
    if (!phoneDigits) {
        error.value = 'Nomor telepon wajib diisi.';
        return;
    }
    if (phoneDigits.length < 9 || phoneDigits.length > 15) {
        error.value = 'Nomor telepon tidak valid.';
        return;
    }

    loading.value = true;
    const result = await updateProfile({
        phone: phone.value.trim(),
        university: university.value,
        interest: interests.value.join(', '),
        nim: nim.value.trim(),
        degree: degree.value,
        ref: refCode.value.trim() || undefined,
    });
    loading.value = false;

    if (!result.ok) {
        error.value = result.error;
        return;
    }

    router.push(dashboardPath.value);
}

function skip() {
    router.push(dashboardPath.value);
}
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-neutral-50 px-4 py-12 dark:bg-neutral-950">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -top-40 left-1/2 h-[28rem] w-[28rem] -translate-x-1/2 rounded-full bg-neutral-300/30 blur-3xl dark:bg-neutral-700/20"></div>
            <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-neutral-200/40 blur-3xl dark:bg-neutral-800/30"></div>
        </div>

        <div class="relative w-full max-w-lg">
            <div class="mb-8 flex items-center justify-center gap-2 text-2xl font-bold tracking-tight text-neutral-900 dark:text-white">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                    <PenLine class="h-4 w-4" />
                </span>
                {{ appName }}
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-neutral-900 shadow-2xl shadow-neutral-200/60 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white dark:shadow-black/40">
                <!-- Langkah 1: Sambutan -->
                <template v-if="step === 1">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs font-medium text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300">
                        <Check class="h-3.5 w-3.5" />
                        Akun berhasil dibuat
                    </span>

                    <h1 class="mt-4 text-2xl font-semibold tracking-tight">
                        Selamat datang, <span class="capitalize">{{ name }}</span>!
                    </h1>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Akunmu sudah siap. Selesaikan langkah singkat di bawah ini agar kamu bisa langsung menulis dokumen pertamamu.
                    </p>

                    <div class="mt-6 space-y-3">
                        <div
                            v-for="(s, i) in welcomeSteps"
                            :key="s.title"
                            class="flex items-start gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800"
                            :class="i === 0 && !isVerified ? 'border-neutral-400 dark:border-neutral-600' : ''"
                        >
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-neutral-200 text-neutral-700 dark:border-neutral-800 dark:text-neutral-300">
                                <component :is="s.icon" class="h-4 w-4" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium">
                                    {{ i + 1 }}. {{ s.title }}
                                    <span v-if="i === 0 && isVerified" class="ml-1 text-xs font-normal text-emerald-600 dark:text-emerald-400">Selesai</span>
                                </p>
                                <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ s.desc }}</p>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-6 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-neutral-900 bg-neutral-900 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-neutral-800 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                        @click="next"
                    >
                        {{ hasCompleteProfile ? 'Lanjut ke Dashboard' : 'Lanjut' }}
                        <ArrowRight class="h-4 w-4" />
                    </button>

                    <button
                        v-if="!isVerified"
                        type="button"
                        class="mt-3 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-neutral-200 py-3 text-sm font-medium transition-colors hover:bg-neutral-100 dark:border-neutral-800 dark:hover:bg-neutral-900"
                        @click="router.push('/verify-email')"
                    >
                        Verifikasi Email Sekarang
                    </button>
                </template>

                <!-- Langkah 2: Lengkapi profil -->
                <template v-else>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                        @click="step = 1"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Kembali
                    </button>

                    <h1 class="mt-3 text-2xl font-semibold tracking-tight">Lengkapi profilmu</h1>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Sedikit info supaya rekomendasi dokumen & formatnya lebih pas buat kamu.
                    </p>

                    <form class="mt-6 space-y-5" @submit.prevent="submit">
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
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kampus / Universitas</label>
                            <div class="relative mt-1">
                                <SearchableSelect v-model="university" :options="UNIVERSITIES" placeholder="Pilih atau ketik kampus">
                                    <template #icon>
                                        <GraduationCap class="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                                    </template>
                                </SearchableSelect>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Jenjang Pendidikan</label>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="d in DEGREES"
                                    :key="d"
                                    type="button"
                                    class="cursor-pointer rounded-full border px-4 py-2 text-sm font-medium transition-colors"
                                    :class="degree === d
                                        ? 'border-neutral-900 bg-neutral-900 text-white dark:border-white dark:bg-white dark:text-neutral-900'
                                        : 'border-neutral-200 text-neutral-600 hover:border-neutral-300 dark:border-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-700'"
                                    @click="degree = d"
                                >
                                    {{ d }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">NIM (opsional)</label>
                            <div class="relative mt-1">
                                <IdCard class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                                <input
                                    v-model="nim"
                                    type="text"
                                    placeholder="Nomor induk mahasiswa"
                                    class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kebutuhanmu <span class="font-normal text-neutral-400 dark:text-neutral-500">(bisa pilih lebih dari satu)</span></label>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="i in INTERESTS"
                                    :key="i"
                                    type="button"
                                    class="cursor-pointer rounded-full border px-4 py-2 text-sm font-medium transition-colors"
                                    :class="interests.includes(i)
                                        ? 'border-neutral-900 bg-neutral-900 text-white dark:border-white dark:bg-white dark:text-neutral-900'
                                        : 'border-neutral-200 text-neutral-600 hover:border-neutral-300 dark:border-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-700'"
                                    @click="toggleInterest(i)"
                                >
                                    <span class="mr-1 inline-flex">{{ interests.includes(i) ? '✓' : '' }}</span>{{ i }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Kode Referral <span class="font-normal text-neutral-400 dark:text-neutral-500">(opsional)</span></label>
                            <input
                                v-model="refCode"
                                type="text"
                                placeholder="Masukkan kode referral temanmu"
                                class="mt-1 w-full rounded-xl border border-neutral-200 bg-transparent px-3 py-2.5 text-sm uppercase outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
                            />
                            <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">Kamu bisa hemat Rp 10.000 di langganan pertamamu.</p>
                        </div>

                        <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-neutral-900 bg-neutral-900 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white dark:bg-white dark:text-neutral-950 dark:hover:bg-neutral-200"
                        >
                            {{ loading ? 'Menyimpan…' : 'Selesai & Lanjut ke Dashboard' }}
                            <ArrowRight class="h-4 w-4" />
                        </button>

                        <button
                            type="button"
                            class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl py-2 text-sm font-medium text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                            @click="skip"
                        >
                            Lewati untuk sekarang
                        </button>
                    </form>
                </template>
            </div>
        </div>
    </div>
</template>
