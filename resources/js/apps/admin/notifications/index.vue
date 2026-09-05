<script setup>
import { onMounted, ref } from 'vue';
import { Save, Send, Mail } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const sending = ref(false);

const adminEmail = ref('');
const notifyPayment = ref(true);
const promoEnabled = ref(true);

const blastSubject = ref('');
const blastMessage = ref('');

onMounted(async () => {
    try {
        const data = await getJson('/api/admin/notification-settings');
        adminEmail.value = data.admin_email || '';
        notifyPayment.value = !!data.notify_payment;
        promoEnabled.value = !!data.promo_enabled;
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
});

async function saveSettings() {
    saving.value = true;
    try {
        const res = await request('/api/admin/notification-settings', {
            method: 'PUT',
            body: JSON.stringify({
                admin_email: adminEmail.value || null,
                notify_payment: notifyPayment.value,
                promo_enabled: promoEnabled.value,
            }),
        });
        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal menyimpan.', 'error');
            return;
        }
        toast(res.data?.message || 'Pengaturan notifikasi disimpan.', 'success');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}

async function sendBlast() {
    if (!blastSubject.value.trim() || !blastMessage.value.trim()) {
        toast('Judul dan isi email wajib diisi.', 'error');
        return;
    }

    sending.value = true;
    try {
        const res = await request('/api/admin/email-blast', {
            method: 'POST',
            body: JSON.stringify({
                subject: blastSubject.value,
                message: blastMessage.value,
            }),
        });
        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal mengirim email.', 'error');
            return;
        }
        toast(`Email promo dikirim ke ${res.data?.recipients ?? 0} pengguna.`, 'success');
        blastSubject.value = '';
        blastMessage.value = '';
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Notifikasi" description="Atur email admin untuk notifikasi pembelian dan kirim email promo ke pengguna." />

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Pengaturan notifikasi -->
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800 sm:p-6">
                <div class="flex items-center gap-2">
                    <Mail class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                    <h2 class="font-semibold">Pengaturan Notifikasi</h2>
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    Email admin dipakai saat ada pembelian masuk. Ganti kapan saja.
                </p>

                <div class="mt-5 space-y-5">
                    <label class="flex flex-col gap-1.5 text-sm">
                        <span class="font-medium">Email Admin</span>
                        <input
                            v-model="adminEmail"
                            type="email"
                            placeholder="admin@tulisin.id"
                            class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                        />
                    </label>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="notifyPayment" type="checkbox" class="h-4 w-4" />
                        <span class="font-medium">Kirim notifikasi email saat ada pembelian</span>
                    </label>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="promoEnabled" type="checkbox" class="h-4 w-4" />
                        <span class="font-medium">Aktifkan pengiriman email promo</span>
                    </label>
                </div>

                <div class="mt-6">
                    <AppButton :disabled="saving || loading" @click="saveSettings">
                        <Save class="h-4 w-4" />
                        {{ saving ? 'Menyimpan…' : 'Simpan Pengaturan' }}
                    </AppButton>
                </div>
            </div>

            <!-- Email blast -->
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800 sm:p-6">
                <div class="flex items-center gap-2">
                    <Send class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                    <h2 class="font-semibold">Email Blast Promo</h2>
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    Kirim promo spesial ke seluruh pengguna terdaftar.
                </p>

                <div class="mt-5 space-y-4">
                    <label class="flex flex-col gap-1.5 text-sm">
                        <span class="font-medium">Judul Email</span>
                        <input
                            v-model="blastSubject"
                            type="text"
                            placeholder="Diskon 20% untuk semua topup!"
                            class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                        />
                    </label>

                    <label class="flex flex-col gap-1.5 text-sm">
                        <span class="font-medium">Isi Email</span>
                        <textarea
                            v-model="blastMessage"
                            rows="6"
                            placeholder="Tulis isi promo di sini…"
                            class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 outline-none transition-colors focus:border-neutral-500 focus:bg-white dark:border-neutral-800 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                        ></textarea>
                    </label>
                </div>

                <div class="mt-6">
                    <AppButton :disabled="sending" @click="sendBlast">
                        <Send class="h-4 w-4" />
                        {{ sending ? 'Mengirim…' : 'Kirim ke Semua Pengguna' }}
                    </AppButton>
                </div>
            </div>
        </div>
    </div>
</template>
