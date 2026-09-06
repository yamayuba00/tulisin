<script setup>
import { ref, computed, onMounted } from 'vue';
import { Send, Users, Search, Mail, PenLine, Eye } from 'lucide-vue-next';
import PageHeader from '../../../components/PageHeader.vue';
import AppButton from '../../../components/AppButton.vue';
import RichTextEditor from './components/RichTextEditor.vue';
import { getJson, request } from '../../../utils/http';
import { toast } from '../../../utils/toast';

const sending = ref(false);
const loadingRecipients = ref(false);

const subject = ref('');
const title = ref('');
const message = ref('');

const recipients = ref([]);
const sendToAll = ref(true);
const selectedIds = ref([]);
const recipientSearch = ref('');

const filteredRecipients = computed(() => {
    const q = recipientSearch.value.trim().toLowerCase();
    if (!q) return recipients.value;
    return recipients.value.filter(
        (u) =>
            (u.name || '').toLowerCase().includes(q) ||
            (u.email || '').toLowerCase().includes(q),
    );
});

const recipientCount = computed(() =>
    sendToAll.value ? recipients.value.length : selectedIds.value.length,
);

const previewSubject = () => subject.value.trim() || 'Subjek email belum diisi';
const previewTitle = () => title.value.trim() || subject.value.trim() || 'Judul email belum diisi';

onMounted(async () => {
    loadingRecipients.value = true;
    try {
        const data = await getJson('/api/admin/broadcast-recipients');
        recipients.value = data.users || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loadingRecipients.value = false;
    }
});

function validBroadcast() {
    const text = message.value.replace(/<[^>]*>/g, '').trim();
    const hasImage = /<img\b/i.test(message.value);
    return !!subject.value.trim() && (!!text || hasImage);
}

async function sendBroadcast() {
    if (!validBroadcast()) {
        toast('Subjek dan isi email wajib diisi.', 'error');
        return;
    }
    if (!sendToAll.value && selectedIds.value.length === 0) {
        toast('Pilih minimal satu penerima.', 'error');
        return;
    }

    sending.value = true;
    try {
        const res = await request('/api/admin/email-blast', {
            method: 'POST',
            body: JSON.stringify({
                subject: subject.value,
                title: title.value || subject.value,
                message: message.value,
                all: sendToAll.value,
                user_ids: selectedIds.value,
            }),
        });
        if (!res.ok) {
            toast(res.data?.error || res.data?.message || 'Gagal mengirim email.', 'error');
            return;
        }
        toast(res.data?.message || `Email dikirim ke ${res.data?.recipients ?? 0} pengguna.`, 'success');
        subject.value = '';
        title.value = '';
        message.value = '';
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-7xl p-6 lg:p-8">
        <PageHeader
            title="Email Broadcast"
            description="Kirim email massal untuk promo, pengumuman, atau informasi lainnya. Pratinjau diperbarui langsung saat mengetik."
        />

        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Komposer -->
            <div class="lg:col-span-3">
                <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900">
                        <PenLine class="h-3.5 w-3.5" />
                    </span>
                    Komposer
                </div>

                <div class="space-y-5 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                            Subjek Email <span class="text-red-500">*</span>
                        </span>
                        <input
                            v-model="subject"
                            type="text"
                            maxlength="120"
                            placeholder="Contoh: Diskon 20% untuk semua topup!"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3.5 py-2.5 text-sm outline-none transition focus:border-neutral-400 focus:bg-white focus:ring-2 focus:ring-neutral-200 dark:border-neutral-700 dark:bg-neutral-800 dark:focus:border-neutral-500 dark:focus:bg-neutral-900 dark:focus:ring-neutral-700"
                        />
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                            Judul Email <span class="font-normal normal-case text-neutral-400">(opsional)</span>
                        </span>
                        <input
                            v-model="title"
                            type="text"
                            maxlength="120"
                            placeholder="Contoh: Halo, ada kabar baik!"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3.5 py-2.5 text-sm outline-none transition focus:border-neutral-400 focus:bg-white focus:ring-2 focus:ring-neutral-200 dark:border-neutral-700 dark:bg-neutral-800 dark:focus:border-neutral-500 dark:focus:bg-neutral-900 dark:focus:ring-neutral-700"
                        />
                    </label>

                    <div>
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                            Isi Email <span class="text-red-500">*</span>
                        </span>
                        <RichTextEditor v-model="message" />
                    </div>

                    <!-- Penerima -->
                    <div class="rounded-xl border border-neutral-200 dark:border-neutral-800">
                        <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                            <span class="flex items-center gap-2 text-sm font-medium text-neutral-700 dark:text-neutral-200">
                                <Users class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                                Penerima
                            </span>
                            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                {{ recipientCount }} pengguna
                            </span>
                        </div>

                        <div class="p-4">
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    :class="sendToAll
                                        ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                        : 'border border-neutral-200 text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                                    class="cursor-pointer rounded-lg px-3.5 py-2 text-xs font-medium transition-colors"
                                    @click="sendToAll = true"
                                >
                                    Semua pengguna
                                </button>
                                <button
                                    type="button"
                                    :class="!sendToAll
                                        ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                        : 'border border-neutral-200 text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                                    class="cursor-pointer rounded-lg px-3.5 py-2 text-xs font-medium transition-colors"
                                    @click="sendToAll = false"
                                >
                                    Pilih pengguna
                                </button>
                            </div>

                            <div v-if="!sendToAll" class="mt-3">
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                                    <input
                                        v-model="recipientSearch"
                                        type="text"
                                        placeholder="Cari nama atau email…"
                                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-neutral-400 focus:bg-white dark:border-neutral-700 dark:bg-neutral-800 dark:focus:bg-neutral-900"
                                    />
                                </div>

                                <div class="mt-2 max-h-52 overflow-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                                    <p v-if="loadingRecipients" class="px-3 py-5 text-center text-xs text-neutral-400">Memuat…</p>
                                    <p v-else-if="filteredRecipients.length === 0" class="px-3 py-5 text-center text-xs text-neutral-400">
                                        Tidak ada pengguna.
                                    </p>
                                    <label
                                        v-for="u in filteredRecipients"
                                        :key="u.id"
                                        class="flex cursor-pointer items-center gap-3 border-b border-neutral-100 px-3.5 py-2.5 text-sm transition-colors last:border-0 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/60"
                                    >
                                        <input
                                            v-model="selectedIds"
                                            type="checkbox"
                                            :value="u.id"
                                            class="h-4 w-4 rounded border-neutral-300 accent-neutral-900 dark:accent-neutral-100"
                                        />
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-medium text-neutral-800 dark:text-neutral-100">{{ u.name || 'Tanpa nama' }}</span>
                                            <span class="block truncate text-xs text-neutral-400">{{ u.email }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-neutral-100 pt-5 dark:border-neutral-800">
                        <p class="text-xs text-neutral-400">
                            Pastikan isi sudah benar sebelum dikirim.
                        </p>
                        <AppButton :disabled="sending" @click="sendBroadcast">
                            <Send class="h-4 w-4" />
                            {{ sending ? 'Mengirim…' : 'Kirim Email' }}
                        </AppButton>
                    </div>
                </div>
            </div>

            <!-- Pratinjau langsung -->
            <div class="lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <span class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900">
                            <Eye class="h-3.5 w-3.5" />
                        </span>
                        Pratinjau
                    </span>
                    <span class="text-xs text-neutral-400">diperbarui otomatis</span>
                </div>

                <div class="overflow-hidden rounded-2xl border border-neutral-200 shadow-sm dark:border-neutral-800">
                    <!-- Header email -->
                    <div class="flex items-center justify-between bg-neutral-900 px-6 py-4">
                        <span class="flex items-center gap-2 text-base font-bold text-white">
                            <Mail class="h-4 w-4 text-neutral-300" />
                            Tulisin
                        </span>
                        <span class="text-[11px] text-neutral-400">no-reply</span>
                    </div>

                    <!-- Subjek -->
                    <div class="border-b border-neutral-200 px-6 py-4 dark:border-neutral-800">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">Subjek</div>
                        <div class="mt-1 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ previewSubject() }}
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="min-h-[320px] bg-white px-6 py-6 dark:bg-neutral-950">
                        <h2 class="mb-4 text-xl font-bold leading-snug text-neutral-900 dark:text-neutral-100">
                            {{ previewTitle() }}
                        </h2>
                        <div
                            v-if="message"
                            class="rt-preview text-sm leading-relaxed text-neutral-800 dark:text-neutral-200"
                            v-html="message"
                        ></div>
                        <p v-else class="text-sm text-neutral-400">Isi email akan tampil di sini.</p>
                    </div>

                    <!-- Footer -->
                    <div class="border-t border-neutral-200 bg-neutral-50 px-6 py-4 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
                        <p class="mb-1">Best Regards,</p>
                        <p class="font-semibold text-neutral-700 dark:text-neutral-200">Tim Tulisin</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rt-preview table {
    border-collapse: collapse;
    width: 100%;
    margin: 12px 0;
}
.rt-preview td,
.rt-preview th {
    border: 1px solid #e4e4e7;
    padding: 8px;
}
.rt-preview img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
}
</style>
