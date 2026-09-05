<script setup>
// Captcha sederhana buatan sendiri (pertambahan acak) — pengganti library ALTCHA.
// Nantinya bisa diganti dengan ALTCHA/backend seiring tersedianya server.
import { computed, ref, watch } from 'vue';
import { RefreshCw, ShieldCheck } from 'lucide-vue-next';

const verified = defineModel('verified', { type: Boolean, default: false });

const a = ref(randomDigit());
const b = ref(randomDigit());
const answer = ref('');

const correct = computed(() => Number(answer.value) === a.value + b.value);

watch(correct, (val) => {
    verified.value = val;
});

function randomDigit() {
    return Math.floor(Math.random() * 9) + 1; // 1..9
}

function refresh() {
    a.value = randomDigit();
    b.value = randomDigit();
    answer.value = '';
    verified.value = false;
}

defineExpose({ refresh });
</script>

<template>
    <div class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 dark:border-neutral-800 dark:bg-neutral-900">
        <div class="flex select-none items-center gap-2 font-mono text-lg font-semibold tracking-wider text-neutral-900 dark:text-white">
            <span>{{ a }}</span>
            <span class="text-neutral-400">+</span>
            <span>{{ b }}</span>
            <span class="text-neutral-400">=</span>
        </div>
        <input
            v-model="answer"
            type="number"
            inputmode="numeric"
            placeholder="?"
            class="w-20 rounded-md border border-neutral-200 bg-white px-2 py-1.5 text-center text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-400"
        />
        <button
            type="button"
            title="Ganti soal"
            aria-label="Ganti soal"
            class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md border border-neutral-200 text-neutral-500 transition-colors hover:text-neutral-900 dark:border-neutral-800 dark:text-neutral-400 dark:hover:text-white"
            @click="refresh"
        >
            <RefreshCw class="h-4 w-4" />
        </button>
        <span
            v-if="correct"
            class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
        >
            <ShieldCheck class="h-4 w-4" /> Benar
        </span>
    </div>
</template>
