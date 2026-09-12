<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { User, Mail, Phone, Lock, ShieldCheck, Handshake, Link2, BadgeCheck, ArrowRight, KeyRound } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import { useAuth } from '../../utils/auth';
import { getJson } from '../../utils/http';
import { formatCurrency } from '../../utils/format';
import { toast } from '../../utils/toast';

const { currentUser, updateAccount, changePassword } = useAuth();

const name = ref(currentUser.value?.name || '');
const savingName = ref(false);

const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const savingPassword = ref(false);

const affiliate = ref(null);

const isGoogle = computed(() => currentUser.value?.provider === 'google');
const emailVerified = computed(() => !!currentUser.value?.email_verified);

async function loadAffiliate() {
    try {
        affiliate.value = await getJson('/api/affiliate');
    } catch {
        affiliate.value = null;
    }
}

onMounted(loadAffiliate);

async function saveName() {
    if (!name.value.trim()) {
        toast('Nama tidak boleh kosong.', 'warning');
        return;
    }

    savingName.value = true;
    const result = await updateAccount({ name: name.value.trim() });
    savingName.value = false;

    if (!result.ok) {
        toast(result.error, 'error');
        return;
    }
    toast('Nama berhasil disimpan.', 'success');
}

async function savePassword() {
    if (!currentPassword.value) {
        toast('Password saat ini wajib diisi.', 'warning');
        return;
    }
    if (newPassword.value.length < 6) {
        toast('Password baru minimal 6 karakter.', 'warning');
        return;
    }
    if (newPassword.value !== confirmPassword.value) {
        toast('Konfirmasi password tidak cocok.', 'warning');
        return;
    }

    savingPassword.value = true;
    const result = await changePassword({
        current_password: currentPassword.value,
        password: newPassword.value,
        password_confirmation: confirmPassword.value,
    });
    savingPassword.value = false;

    if (!result.ok) {
        toast(result.error, 'error');
        return;
    }

    currentPassword.value = '';
    newPassword.value = '';
    confirmPassword.value = '';
    toast('Password berhasil diubah.', 'success');
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Profil Saya" description="Kelola informasi akun dan pantau performa afiliasimu." />

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Informasi akun -->
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800 lg:col-span-2">
                <div class="flex items-center gap-2">
                    <User class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                    <h2 class="text-sm font-semibold">Informasi Akun</h2>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium"
                        :class="isGoogle
                            ? 'border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:text-neutral-300'
                            : 'border-neutral-200 text-neutral-600 dark:border-neutral-700 dark:text-neutral-300'">
                        <BadgeCheck class="h-3.5 w-3.5" />
                        {{ isGoogle ? 'Login via Google' : 'Akun Manual' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium"
                        :class="emailVerified
                            ? 'border-emerald-200 text-emerald-600 dark:border-emerald-800 dark:text-emerald-400'
                            : 'border-amber-200 text-amber-600 dark:border-amber-800 dark:text-amber-400'">
                        <ShieldCheck class="h-3.5 w-3.5" />
                        {{ emailVerified ? 'Email Terverifikasi' : 'Email Belum Terverifikasi' }}
                    </span>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nama Lengkap</label>
                        <div class="relative mt-1">
                            <User class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                            <input
                                v-model="name"
                                type="text"
                                class="w-full rounded-lg border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 dark:border-neutral-800 dark:focus:border-neutral-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Email</label>
                        <div class="relative mt-1">
                            <Mail class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                            <input
                                :value="currentUser?.email || ''"
                                type="email"
                                disabled
                                class="w-full cursor-not-allowed rounded-lg border border-neutral-200 bg-neutral-50 py-2.5 pl-9 pr-3 text-sm text-neutral-400 outline-none dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-500"
                            />
                        </div>
                        <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">Email tidak dapat diubah.</p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Nomor Telepon</label>
                        <div class="relative mt-1">
                            <Phone class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                            <input
                                :value="currentUser?.phone || '—'"
                                type="tel"
                                disabled
                                class="w-full cursor-not-allowed rounded-lg border border-neutral-200 bg-neutral-50 py-2.5 pl-9 pr-3 text-sm text-neutral-500 outline-none dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400"
                            />
                        </div>
                    </div>

                    <div class="pt-1">
                        <AppButton :disabled="savingName" @click="saveName">
                            {{ savingName ? 'Menyimpan…' : 'Simpan Perubahan' }}
                        </AppButton>
                    </div>
                </div>
            </div>

            <!-- Ringkasan afiliasi -->
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex items-center gap-2">
                    <Handshake class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                    <h2 class="text-sm font-semibold">Ringkasan Afiliasi</h2>
                </div>

                <div v-if="affiliate" class="mt-4 space-y-3">
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-900">
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Kode Referral</p>
                        <div class="mt-1 flex items-center gap-1.5">
                            <Link2 class="h-3.5 w-3.5 text-neutral-400" />
                            <span class="font-mono text-sm font-semibold">{{ affiliate.code }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">Teman Terdaftar</span>
                        <span class="text-sm font-semibold">{{ affiliate.total_referred }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">Saldo Komisi</span>
                        <span class="text-sm font-semibold">{{ formatCurrency(affiliate.commission_balance) }}</span>
                    </div>
                    <RouterLink
                        to="/apps/u/affiliate"
                        class="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    >
                        Kelola Afiliasi
                        <ArrowRight class="h-3.5 w-3.5" />
                    </RouterLink>
                </div>
                <p v-else class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">Memuat data afiliasi…</p>
            </div>
        </div>

        <!-- Ganti password -->
        <div v-if="!isGoogle" class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
            <div class="flex items-center gap-2">
                <KeyRound class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                <h2 class="text-sm font-semibold">Ganti Password</h2>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Password Saat Ini</label>
                    <div class="relative mt-1">
                        <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                        <input
                            v-model="currentPassword"
                            type="password"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 dark:border-neutral-800 dark:focus:border-neutral-500"
                        />
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Password Baru</label>
                    <div class="relative mt-1">
                        <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                        <input
                            v-model="newPassword"
                            type="password"
                            placeholder="Minimal 6 karakter"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 dark:border-neutral-800 dark:focus:border-neutral-500"
                        />
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Konfirmasi Password</label>
                    <div class="relative mt-1">
                        <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                        <input
                            v-model="confirmPassword"
                            type="password"
                            class="w-full rounded-lg border border-neutral-200 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 dark:border-neutral-800 dark:focus:border-neutral-500"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <AppButton variant="outline" :disabled="savingPassword" @click="savePassword">
                    {{ savingPassword ? 'Menyimpan…' : 'Ubah Password' }}
                </AppButton>
            </div>
        </div>

        <div v-else class="mt-6 rounded-xl border border-neutral-200 p-5 text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
            Akun ini login melalui Google, sehingga pengaturan password tidak tersedia.
        </div>
    </div>
</template>
