<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Bold, Italic, Underline, Strikethrough, List, ListOrdered, Type } from 'lucide-vue-next';
import FilterSelect from './FilterSelect.vue';

const props = defineProps({
    block: { type: Object, default: null },
    fontOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:font-family', 'update:font-size']);

const active = ref({});

const idleBtn = 'flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white';
const activeBtn = 'flex h-8 w-8 cursor-pointer items-center justify-center rounded-md bg-neutral-100 text-neutral-900 dark:bg-neutral-800 dark:text-white';

const fieldCls = 'h-8 cursor-pointer rounded-md border border-neutral-200 bg-transparent px-2 text-xs text-neutral-700 outline-none transition-colors focus:border-neutral-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-800 dark:text-neutral-300 dark:focus:border-neutral-400';

const toolbarFontOptions = computed(() => [
    { value: '', label: 'Font (Ikuti dokumen)' },
    ...props.fontOptions.map((f) => ({ value: f, label: f })),
]);

// Cari elemen contenteditable milik blok terpilih. Blok di-render dua kali
// (mirror untuk pengukuran + canvas asli); mirror bersifat contenteditable=false,
// jadi lewati dan ambil yang contenteditable=true.
function findEditable() {
    if (props.block?.uid) {
        const roots = document.querySelectorAll(`[data-block-uid="${props.block.uid}"]`);
        // Utamakan isi blok utama (.editor). Judul section (mis. blankPage) punya
        // contenteditable sendiri dan tidak perlu diformat lewat toolbar.
        for (const root of roots) {
            const editor = root.querySelector('[contenteditable="true"].editor');
            if (editor) return editor;
        }
        for (const root of roots) {
            const el = root.querySelector('[contenteditable="true"]');
            if (el) return el;
        }
    }
    const ae = document.activeElement;
    return ae && ae.isContentEditable ? ae : null;
}

// Pilih seluruh isi blok (untuk memformat blok secara utuh saat tidak ada seleksi teks).
function selectAll(el) {
    const sel = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(el);
    sel.removeAllRanges();
    sel.addRange(range);
}

// Letakkan kursor di akhir blok agar blok tidak tetap ter-highlight penuh.
function collapseToEnd(el) {
    const sel = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
}

function exec(command, wholeBlock = true) {
    const el = findEditable();
    if (!el) return;
    const sel = window.getSelection();
    const hasSelection = Boolean(sel && !sel.isCollapsed && el.contains(sel.anchorNode));

    el.focus();
    if (wholeBlock && !hasSelection) {
        // Tidak ada teks terpilih: terapkan ke seluruh isi blok.
        selectAll(el);
    }

    document.execCommand(command, false, null);

    if (wholeBlock && !hasSelection) collapseToEnd(el);

    // Pastikan konten tersimpan (sebagian browser tidak memicu input saat execCommand).
    el.dispatchEvent(new Event('input', { bubbles: true }));
    refreshActive();
}

function refreshActive() {
    active.value = {
        bold: document.queryCommandState('bold'),
        italic: document.queryCommandState('italic'),
        underline: document.queryCommandState('underline'),
        strikeThrough: document.queryCommandState('strikeThrough'),
        listBullet: document.queryCommandState('insertUnorderedList'),
        listOrdered: document.queryCommandState('insertOrderedList'),
    };
}

function onSizeChange(e) {
    emit('update:font-size', e.target.value ? Number(e.target.value) : 0);
}

// Saat blok berpindah, segarkan status inline agar toolbar mencerminkan blok yang diklik.
watch(() => props.block?.uid, () => refreshActive());

onMounted(() => {
    document.addEventListener('selectionchange', refreshActive);
    document.addEventListener('mouseup', refreshActive);
    document.addEventListener('keyup', refreshActive);
});

onBeforeUnmount(() => {
    document.removeEventListener('selectionchange', refreshActive);
    document.removeEventListener('mouseup', refreshActive);
    document.removeEventListener('keyup', refreshActive);
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-1 border-b border-neutral-200 px-3 py-2 dark:border-neutral-800">
        <template v-if="block">
            <FilterSelect
                :model-value="block.fontFamily || ''"
                :options="toolbarFontOptions"
                placeholder="Font"
                size="sm"
                :block="false"
                @update:model-value="emit('update:font-family', $event)"
            />

            <input
                :value="block.fontSize || ''"
                type="number"
                min="8"
                max="72"
                step="1"
                placeholder="pt"
                :class="fieldCls"
                style="width: 4.5rem"
                title="Ukuran font blok"
                @input="onSizeChange"
            />

            <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-800"></span>
        </template>

        <span v-else class="inline-flex items-center gap-1.5 text-xs text-neutral-400 dark:text-neutral-500">
            <Type class="h-3.5 w-3.5" />
            Klik blok di canvas untuk memformat
        </span>

        <button type="button" title="Tebal" :class="active.bold ? activeBtn : idleBtn" @mousedown.prevent="exec('bold')">
            <Bold class="h-4 w-4" />
        </button>
        <button type="button" title="Miring" :class="active.italic ? activeBtn : idleBtn" @mousedown.prevent="exec('italic')">
            <Italic class="h-4 w-4" />
        </button>
        <button type="button" title="Garis bawah" :class="active.underline ? activeBtn : idleBtn" @mousedown.prevent="exec('underline')">
            <Underline class="h-4 w-4" />
        </button>
        <button type="button" title="Coret" :class="active.strikeThrough ? activeBtn : idleBtn" @mousedown.prevent="exec('strikeThrough')">
            <Strikethrough class="h-4 w-4" />
        </button>

        <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-800"></span>

        <button type="button" title="List Poin" :class="active.listBullet ? activeBtn : idleBtn" @mousedown.prevent="exec('insertUnorderedList', false)">
            <List class="h-4 w-4" />
        </button>
        <button type="button" title="List Nomor" :class="active.listOrdered ? activeBtn : idleBtn" @mousedown.prevent="exec('insertOrderedList', false)">
            <ListOrdered class="h-4 w-4" />
        </button>
    </div>
</template>
