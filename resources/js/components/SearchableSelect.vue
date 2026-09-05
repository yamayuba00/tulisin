<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Check, ChevronDown, Plus } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih atau ketik...' },
    allowCreate: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const root = ref(null);

// Filter opsi sesuai kata kunci yang diketik.
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => String(o).toLowerCase().includes(q));
});

// Tampilkan opsi "Gunakan '...'" jika kata kunci tidak cocok persis.
const canCreate = computed(() => {
    if (!props.allowCreate) return false;
    const q = query.value.trim();
    if (!q) return false;
    return !props.options.some((o) => String(o).toLowerCase() === q.toLowerCase());
});

function openList() {
    query.value = props.modelValue || '';
    open.value = true;
}

function pick(value) {
    emit('update:modelValue', value);
    query.value = value;
    open.value = false;
}

function create() {
    const value = query.value.trim();
    emit('update:modelValue', value);
    query.value = value;
    open.value = false;
}

function close() {
    open.value = false;
    query.value = props.modelValue || '';
}

function onDocClick(e) {
    if (root.value && !root.value.contains(e.target)) close();
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div ref="root" class="relative">
        <slot name="icon" />
        <input
            v-model="query"
            type="text"
            :placeholder="placeholder"
            class="w-full rounded-xl border border-neutral-200 bg-transparent py-2.5 pl-9 pr-8 text-sm text-neutral-900 outline-none transition focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 dark:border-neutral-800 dark:bg-neutral-950 dark:text-white dark:focus:border-neutral-500 dark:focus:ring-neutral-800"
            @focus="openList"
            @input="open = true"
        />
        <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />

        <div
            v-if="open"
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
        >
            <p v-if="!filtered.length && !canCreate" class="px-3 py-2 text-xs text-neutral-400 dark:text-neutral-500">
                Tidak ditemukan
            </p>
            <button
                v-for="o in filtered"
                :key="o"
                type="button"
                class="flex w-full cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-neutral-900 transition-colors hover:bg-neutral-100 dark:text-white dark:hover:bg-neutral-800"
                @click="pick(o)"
            >
                <span>{{ o }}</span>
                <Check v-if="o === modelValue" class="h-4 w-4 text-neutral-900 dark:text-white" />
            </button>
            <button
                v-if="canCreate"
                type="button"
                class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
                @click="create"
            >
                <Plus class="h-4 w-4" />
                Gunakan "{{ query.trim() }}"
            </button>
        </div>
    </div>
</template>
