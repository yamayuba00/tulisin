<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import { GripVertical, Image as ImageIcon } from 'lucide-vue-next';

const props = defineProps({
    block: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    measure: { type: Boolean, default: false },
    captionPrefix: { type: String, default: '' },
    maxHeight: { type: Number, default: null },
});

const emit = defineEmits(['select', 'update:width', 'update:indent', 'dragstart', 'dragend']);

const wrapEl = ref(null);
const boxEl = ref(null);

function onTab(e) {
    e.preventDefault();
    const delta = e.shiftKey ? -1 : 1;
    const next = Math.min(6, Math.max(0, (props.block.indent || 0) + delta));
    if (next !== (props.block.indent || 0)) emit('update:indent', next);
}

const src = computed(() => {
    const value = props.block.content || '';
    return /^(https?:|data:|blob:|\/)/i.test(value) ? value : '';
});

const captionText = computed(() =>
    (props.block.caption || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(),
);

const captionLabel = computed(() => {
    const pieces = ['Gambar'];
    if (props.captionPrefix) pieces.push(props.captionPrefix);
    if (captionText.value) pieces.push(captionText.value);
    return pieces.length > 1 ? pieces.join(' ') : '';
});

const captionPosition = computed(() => props.block.captionPosition || 'below');

const showCaption = computed(() => props.block.showCaption !== false);

const rootStyle = computed(() => ({
    marginLeft: `${(props.block.indent || 0) * 1.5}em`,
}));

const alignClass = computed(() => {
    const align = props.block.align || 'left';
    if (align === 'center') return 'justify-center';
    if (align === 'right') return 'justify-end';
    return 'justify-start';
});

const boxStyle = computed(() => ({
    width: `${props.block.width || 60}%`,
}));

// Batasi tinggi gambar agar tidak melampaui halaman (seperti Google Docs).
const imgStyle = computed(() => {
    const style = { objectFit: 'contain' };
    if (props.maxHeight) style.maxHeight = `${props.maxHeight}px`;
    return style;
});

// ---- Ubah ukuran gambar dengan menarik sudut kanan-bawah ----
let startX = 0;
let startW = 0;
let startCw = 0;
let resizing = false;

function onResizeStart(e) {
    e.preventDefault();
    e.stopPropagation();
    const wrap = wrapEl.value;
    const box = boxEl.value;
    if (!wrap || !box) return;
    resizing = true;
    startX = e.clientX;
    startW = box.getBoundingClientRect().width;
    startCw = wrap.getBoundingClientRect().width;
    document.addEventListener('mousemove', onResizeMove);
    document.addEventListener('mouseup', onResizeEnd);
}

function onResizeMove(e) {
    if (!resizing) return;
    const newW = startW + (e.clientX - startX);
    const pct = Math.min(100, Math.max(5, (newW / startCw) * 100));
    emit('update:width', Math.round(pct));
}

function onResizeEnd() {
    resizing = false;
    document.removeEventListener('mousemove', onResizeMove);
    document.removeEventListener('mouseup', onResizeEnd);
}

onBeforeUnmount(onResizeEnd);
</script>

<template>
    <div
        :data-block-uid="block.uid"
        class="group relative cursor-pointer rounded-md px-3 py-1"
        :style="rootStyle"
        @click="$emit('select')"
        @keydown.tab.prevent="onTab"
    >
        <div
            v-if="!measure"
            draggable="true"
            class="absolute -left-6 top-1/2 -translate-y-1/2 cursor-grab text-neutral-300 transition-opacity active:cursor-grabbing dark:text-neutral-600"
            :class="selected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
            aria-label="Geser blok"
            @dragstart="$emit('dragstart', $event, block.uid)"
            @dragend="$emit('dragend', $event)"
        >
            <GripVertical class="h-4 w-4" />
        </div>

        <div v-if="showCaption && captionLabel && captionPosition === 'above'" class="mb-1 text-center text-sm">{{ captionLabel }}</div>

        <div v-if="src" ref="wrapEl" class="flex py-1" :class="alignClass">
            <div ref="boxEl" class="relative" :style="boxStyle">
                <img :src="src" class="block h-auto w-full rounded-md" :style="imgStyle" alt="Gambar" draggable="false" />
                <button
                    v-if="selected && !measure"
                    type="button"
                    class="absolute -bottom-1 -right-1 h-3.5 w-3.5 cursor-nwse-resize rounded-sm border border-neutral-400 bg-white shadow-sm dark:border-neutral-500 dark:bg-neutral-800"
                    aria-label="Ubah ukuran gambar"
                    title="Tarik untuk ubah ukuran"
                    @mousedown.prevent="onResizeStart"
                ></button>
            </div>
        </div>
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-md border border-dashed border-neutral-300 px-4 py-10 text-center text-neutral-400 dark:border-neutral-700 dark:text-neutral-500"
        >
            <ImageIcon class="h-8 w-8" />
            <p class="mt-2 text-sm">Tambahkan gambar</p>
        </div>

        <div v-if="showCaption && captionLabel && captionPosition === 'below'" class="mt-1 text-center text-sm">{{ captionLabel }}</div>
    </div>
</template>
