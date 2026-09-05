<script setup>
import { computed, ref, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Check, ChevronDown, Search } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, required: true },
    placeholder: { type: String, default: 'Pilih...' },
    disabled: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
    block: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const rootEl = ref(null);
const menuEl = ref(null);
const searchEl = ref(null);
const menuStyle = ref({});

// Normalisasi opsi: bisa berupa string atau { value, label }.
const normalized = computed(() =>
    props.options.map((o) => {
        if (o && typeof o === 'object') {
            return { value: o.value, label: o.label ?? String(o.value) };
        }
        return { value: o, label: String(o) };
    }),
);

const currentLabel = computed(() => {
    const found = normalized.value.find((o) => isEqual(o.value, props.modelValue));
    return found ? found.label : '';
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return normalized.value;
    return normalized.value.filter((o) =>
        o.label.toLowerCase().includes(q) || String(o.value).toLowerCase().includes(q),
    );
});

function isEqual(a, b) {
    return a === b || String(a) === String(b);
}

function isSelected(opt) {
    return isEqual(opt.value, props.modelValue);
}

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) {
        query.value = '';
        nextTick(() => {
            position();
            searchEl.value?.focus();
        });
    }
}

function position() {
    const el = rootEl.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const menuW = Math.max(rect.width, 192);
    let left = rect.left;
    let top = rect.bottom + 4;
    if (left + menuW > window.innerWidth - 8) left = window.innerWidth - menuW - 8;
    left = Math.max(8, left);
    const approxH = 260;
    if (top + approxH > window.innerHeight - 8) top = Math.max(8, rect.top - approxH - 4);
    menuStyle.value = { left: `${left}px`, top: `${top}px`, width: `${menuW}px` };
}

function select(opt) {
    emit('update:modelValue', opt.value);
    open.value = false;
}

function onDocClick(e) {
    if (!open.value) return;
    if (rootEl.value?.contains(e.target)) return;
    if (menuEl.value?.contains(e.target)) return;
    open.value = false;
}

function onScroll(e) {
    if (!open.value) return;
    if (menuEl.value?.contains(e.target)) return;
    open.value = false;
}

function onResize() {
    open.value = false;
}

function onKeydown(e) {
    if (e.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('scroll', onScroll, true);
    window.addEventListener('resize', onResize);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('scroll', onScroll, true);
    window.removeEventListener('resize', onResize);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="rootEl" class="relative">
        <button
            type="button"
            :disabled="disabled"
            class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border border-neutral-200 bg-transparent text-left text-neutral-900 outline-none transition-colors focus:border-neutral-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-100 dark:focus:border-neutral-400"
            :class="[
                block ? 'w-full' : 'w-auto',
                size === 'sm' ? 'h-8 px-2 text-xs' : 'px-3 py-2 text-sm',
            ]"
            @click="toggle"
        >
            <span class="truncate" :class="{ 'text-neutral-400 dark:text-neutral-500': !currentLabel }">
                {{ currentLabel || placeholder }}
            </span>
            <ChevronDown class="h-4 w-4 shrink-0 text-neutral-400 transition-transform dark:text-neutral-500" :class="{ 'rotate-180': open }" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="menuEl"
                class="fixed z-[70] overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-xl dark:border-neutral-800 dark:bg-neutral-950"
                :style="menuStyle"
            >
                <div class="border-b border-neutral-200 p-2 dark:border-neutral-800">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" />
                        <input
                            ref="searchEl"
                            v-model="query"
                            type="text"
                            placeholder="Cari..."
                            class="w-full rounded-md border border-neutral-200 bg-transparent py-1.5 pl-7 pr-2 text-sm outline-none focus:border-neutral-500 dark:border-neutral-800 dark:bg-neutral-950 dark:focus:border-neutral-400"
                        />
                    </div>
                </div>

                <div class="max-h-56 overflow-y-auto p-1">
                    <button
                        v-for="opt in filtered"
                        :key="String(opt.value)"
                        type="button"
                        class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-sm text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="select(opt)"
                    >
                        <span class="truncate">{{ opt.label }}</span>
                        <Check v-if="isSelected(opt)" class="h-4 w-4 shrink-0 text-neutral-900 dark:text-white" />
                    </button>
                    <p v-if="filtered.length === 0" class="px-2 py-3 text-center text-xs text-neutral-400 dark:text-neutral-500">
                        Tidak ditemukan.
                    </p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
