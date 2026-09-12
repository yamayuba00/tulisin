<script setup>
import { computed, onMounted, ref } from 'vue';
import { Star, Loader2, Info } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import AppButton from '../../components/AppButton.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { getJson, request } from '../../utils/http';
import { toast } from '../../utils/toast';
import { formatDate } from '../../utils/format';
import appName from '../../utils/appName';

const MAX_TEXT = 150;

const loading = ref(true);
const submitting = ref(false);
const rating = ref(0);
const text = ref('');
const reviews = ref([]);

const textRemaining = computed(() => MAX_TEXT - text.value.length);
const canSubmit = computed(() => rating.value >= 1 && rating.value <= 5 && text.value.trim().length > 0 && !submitting.value);

function statusMeta(status) {
    switch (status) {
        case 'published':
            return { label: 'Dipublikasikan', tone: 'success' };
        case 'rejected':
            return { label: 'Ditolak', tone: 'danger' };
        default:
            return { label: 'Menunggu Moderasi', tone: 'warning' };
    }
}

function setRating(value) {
    rating.value = value;
}

async function load() {
    loading.value = true;
    try {
        const data = await getJson('/api/reviews/mine');
        reviews.value = data.reviews || [];
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!canSubmit.value) return;

    submitting.value = true;
    try {
        const res = await request('/api/reviews', {
            method: 'POST',
            body: JSON.stringify({ rating: rating.value, text: text.value.trim() }),
        });
        if (res.ok) {
            toast(res.data?.message || 'Review terkirim untuk moderasi.', 'success');
            rating.value = 0;
            text.value = '';
            await load();
        } else {
            toast(res.data?.error || res.data?.message || 'Gagal mengirim review.', 'error');
        }
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="p-6 lg:p-8">
        <PageHeader title="Ulasan" description="Bagikan pengalamanmu. Review akan tampil di homepage setelah disetujui admin." />

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Form kirim review -->
            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                <h2 class="font-semibold">Tulis Review</h2>

                <div class="mt-4">
                    <p class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Rating</p>
                    <div class="mt-2 flex gap-1.5">
                        <button
                            v-for="i in 5"
                            :key="i"
                            type="button"
                            class="cursor-pointer transition-transform hover:scale-110"
                            :aria-label="`Beri ${i} bintang`"
                            @click="setRating(i)"
                        >
                            <Star
                                class="h-8 w-8 transition-colors"
                                :class="i <= rating ? 'fill-amber-400 text-amber-400' : 'fill-transparent text-neutral-300 dark:text-neutral-600'"
                            />
                        </button>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="review-text" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Ulasan</label>
                    <textarea
                        id="review-text"
                        v-model="text"
                        rows="4"
                        :maxlength="MAX_TEXT"
                        :placeholder="`Ceritakan pengalamanmu menggunakan ${appName}…`"
                        class="mt-1 w-full rounded-lg border border-neutral-200 bg-transparent px-3 py-2.5 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                    ></textarea>
                    <p class="mt-1 text-right text-xs text-neutral-400 dark:text-neutral-500">{{ textRemaining }} karakter tersisa</p>
                </div>

                <div class="mt-5">
                    <AppButton block :disabled="!canSubmit" @click="submit">
                        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                        {{ submitting ? 'Mengirim…' : 'Kirim Review' }}
                    </AppButton>
                </div>
            </div>

            <!-- Info -->
            <div class="flex items-start gap-3 rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                <Info class="h-5 w-5 shrink-0 text-neutral-400" />
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    <p class="font-medium text-neutral-800 dark:text-neutral-200">Bagaimana review ditampilkan?</p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-neutral-500 dark:text-neutral-400">
                        <li>Beri rating bintang 1–5 dan tulis ulasan maksimal {{ MAX_TEXT }} karakter.</li>
                        <li>Review kamu masuk antrean dan menunggu moderasi admin.</li>
                        <li>Jika disetujui, review tampil di homepage (maksimal 9) dan halaman semua rating.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Review saya -->
        <div class="mt-8">
            <h2 class="mb-4 font-semibold">Review Saya</h2>

            <div v-if="loading" class="rounded-lg border border-dashed border-neutral-300 px-6 py-12 text-center dark:border-neutral-700">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Memuat…</p>
            </div>

            <div v-else-if="reviews.length === 0" class="rounded-lg border border-dashed border-neutral-300 px-6 py-12 text-center dark:border-neutral-700">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Belum ada review. Kirim review pertamamu di atas.</p>
            </div>

            <div v-else class="space-y-3">
                <div v-for="r in reviews" :key="r.id" class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex gap-0.5 text-amber-400">
                            <Star v-for="i in 5" :key="i" class="h-4 w-4" :class="i <= r.rating ? 'fill-current' : 'fill-transparent text-neutral-300 dark:text-neutral-600'" />
                        </div>
                        <StatusBadge :label="statusMeta(r.status).label" :tone="statusMeta(r.status).tone" />
                    </div>
                    <p class="mt-2 text-sm text-neutral-700 dark:text-neutral-300">{{ r.text }}</p>
                    <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">{{ formatDate(r.created_at, { withTime: true }) }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
