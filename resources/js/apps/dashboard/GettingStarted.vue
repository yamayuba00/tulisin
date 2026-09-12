<script setup>
import { computed, ref } from 'vue';
import { MailCheck, GraduationCap, FolderOpen, Check, X } from 'lucide-vue-next';
import { useAuth } from '../../utils/auth';

const props = defineProps({
    projectCount: { type: Number, default: 0 },
});

const { currentUser } = useAuth();

const STORAGE_KEY = 'tulisin.getting-started.dismissed';
const dismissed = ref(localStorage.getItem(STORAGE_KEY) === '1');

const emailVerified = computed(() => !!currentUser.value?.email_verified);
const profileComplete = computed(() => {
    const p = currentUser.value?.profile;
    return !!(p?.university && p?.major);
});

const items = computed(() => [
    {
        icon: MailCheck,
        title: 'Verifikasi email',
        desc: 'Aktifkan akun lewat tautan di email.',
        done: emailVerified.value,
        to: '/verify-email',
    },
    {
        icon: GraduationCap,
        title: 'Lengkapi profil',
        desc: 'Isi kampus & kebutuhanmu.',
        done: profileComplete.value,
        to: '/boarding?step=2',
    },
    {
        icon: FolderOpen,
        title: 'Buat dokumen pertama',
        desc: 'Mulai project atau pakai template.',
        done: props.projectCount > 0,
        to: '/apps/u/projects',
    },
]);

const allDone = computed(() => items.value.every((i) => i.done));

function dismiss() {
    dismissed.value = true;
    localStorage.setItem(STORAGE_KEY, '1');
}
</script>

<template>
    <div v-if="!dismissed && !allDone" class="rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold">Mulai di sini</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Tiga langkah singkat untuk mulai menulis.</p>
            </div>
            <button
                type="button"
                class="inline-flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-md text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-900 dark:hover:text-neutral-200"
                aria-label="Tutup"
                @click="dismiss"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <RouterLink
                v-for="(item, i) in items"
                :key="item.title"
                :to="item.to"
                class="group flex items-start gap-3 rounded-lg border border-neutral-200 p-4 transition-colors hover:border-neutral-300 dark:border-neutral-800 dark:hover:border-neutral-700"
                :class="item.done ? 'opacity-60' : ''"
            >
                <component :is="item.icon" class="mt-0.5 h-4 w-4 shrink-0 text-neutral-500 dark:text-neutral-400" />
                <div class="min-w-0">
                    <p class="flex items-center gap-1.5 text-sm font-medium">
                        {{ i + 1 }}. {{ item.title }}
                        <Check v-if="item.done" class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                    </p>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ item.desc }}</p>
                </div>
            </RouterLink>
        </div>
    </div>
</template>
