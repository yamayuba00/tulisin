<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import {
    Bold,
    Italic,
    Underline,
    Table2,
    Image as ImageIcon,
    Plus,
    Minus,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Trash2,
    RefreshCw,
} from 'lucide-vue-next';
import { request } from '../../../../utils/http';
import { toast } from '../../../../utils/toast';

const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const editor = ref(null);
const fileInput = ref(null);
const uploading = ref(false);
const activeTable = ref(false);
const activeImage = ref(false);
const currentImage = ref(null);
const showPlaceholder = ref(true);
const replacingImage = ref(false);
const focused = ref(false);

const MAX_IMAGE_SIZE = 2 * 1024 * 1024; // 2 MB

function isEmptyHtml(html) {
    const h = (html || '').trim();
    return h === '' || h === '<br>' || h === '<p><br></p>' || h === '<div><br></div>' || h === '<p></p>' || h === '<div></div>';
}

function updatePlaceholder() {
    const el = editor.value;
    showPlaceholder.value = el ? isEmptyHtml(el.innerHTML) && !focused.value : true;
}

function emitInput() {
    const el = editor.value;
    if (!el) return;
    if (isEmptyHtml(el.innerHTML) && el.innerHTML !== '<p><br></p>') {
        el.innerHTML = '<p><br></p>';
    }
    updatePlaceholder();
    emit('update:modelValue', el.innerHTML);
}

function resolveEl(node) {
    return node?.nodeType === Node.ELEMENT_NODE ? node : node?.parentElement;
}

function currentCell() {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return null;
    const el = resolveEl(sel.anchorNode);
    return el?.closest('td, th') || null;
}

function updateContext() {
    const sel = window.getSelection();
    const el = sel && sel.rangeCount > 0 ? resolveEl(sel.anchorNode) : null;
    const insideEditor = !!(el && editor.value?.contains(el));

    activeTable.value = insideEditor ? !!el.closest('table') : false;

    const selImg = insideEditor ? el.closest('img') : null;
    const clickImg = currentImage.value && editor.value?.contains(currentImage.value) ? currentImage.value : null;
    activeImage.value = !!(clickImg || selImg);
}

function onEditorClick(e) {
    const target = e.target;
    const img = target instanceof Element
        ? (target.tagName === 'IMG' ? target : target.closest('img'))
        : null;
    currentImage.value = img;
    updateContext();
}

function onEditorFocus() {
    focused.value = true;
    updatePlaceholder();
}

function onEditorBlur() {
    focused.value = false;
    updatePlaceholder();
}

function placeCaretAtEnd() {
    const el = editor.value;
    if (!el) return;
    el.focus();
    if (isEmptyHtml(el.innerHTML)) {
        el.innerHTML = '<p><br></p>';
    }
    const range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    updatePlaceholder();
}

// Saat area editor diklik, pastikan kursor langsung muncul tanpa perlu ditekan:
// - Klik di konten teks: biarkan browser menempatkan kursor secara normal.
// - Klik di area kosong (div editor itu sendiri): pindahkan kursor ke akhir.
function onEditorMousedown(e) {
    const el = editor.value;
    if (!el) return;
    // Klik pada gambar/tabel: biarkan browser mengelola pemilihan secara normal.
    if (e.target instanceof Element && e.target.closest('img, table')) return;
    if (e.target === el) {
        e.preventDefault();
        placeCaretAtEnd();
        return;
    }
    requestAnimationFrame(() => {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0 || !el.contains(sel.anchorNode)) {
            placeCaretAtEnd();
        }
    });
}

function ensureSelectionInEditor() {
    const el = editor.value;
    if (!el) return;
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0 && el.contains(sel.anchorNode)) return;
    const range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
}

function insertNodeAtSelection(node) {
    const el = editor.value;
    if (!el) return;
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0 || !el.contains(sel.anchorNode)) {
        el.appendChild(node);
        return;
    }
    const range = sel.getRangeAt(0);
    range.deleteContents();
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
}

onMounted(() => {
    if (editor.value) {
        editor.value.innerHTML = props.modelValue || '<p><br></p>';
        updatePlaceholder();
    }
    document.addEventListener('selectionchange', updateContext);
});

onBeforeUnmount(() => {
    document.removeEventListener('selectionchange', updateContext);
});

// Jangan reset innerHTML saat user sedang mengetik (mencegah kursor loncat).
watch(
    () => props.modelValue,
    (val) => {
        const el = editor.value;
        if (!el) return;
        if (document.activeElement === el) return;
        const next = val || '<p><br></p>';
        if (el.innerHTML !== next) el.innerHTML = next;
        updatePlaceholder();
    },
);

function exec(command) {
    editor.value?.focus();
    document.execCommand(command, false, null);
    emitInput();
}

// ---- Tabel ----
function insertTable() {
    const el = editor.value;
    if (!el) return;
    el.focus();
    ensureSelectionInEditor();

    const table = document.createElement('table');
    table.style.cssText = 'border-collapse:collapse;width:100%;margin:12px 0;';

    const rows = 2;
    const cols = 2;
    for (let r = 0; r < rows; r++) {
        const tr = table.insertRow();
        for (let c = 0; c < cols; c++) {
            const td = tr.insertCell();
            td.style.cssText = 'border:1px solid #e4e4e7;padding:8px;min-width:40px;';
            td.innerHTML = '<br>';
        }
    }

    insertNodeAtSelection(table);
    emitInput();
    updateContext();
}

function addRow() {
    const table = resolveEl(window.getSelection()?.anchorNode)?.closest('table');
    const cell = currentCell();
    if (!table || !cell || !table.rows) return;
    const rowIndex = cell.parentElement.rowIndex;
    const colCount = table.rows[0]?.cells.length || 1;
    const tr = table.insertRow(rowIndex + 1);
    for (let i = 0; i < colCount; i++) {
        const td = tr.insertCell();
        td.style.cssText = 'border:1px solid #e4e4e7;padding:8px;min-width:40px;';
        td.innerHTML = '<br>';
    }
    emitInput();
}

function removeRow() {
    const table = resolveEl(window.getSelection()?.anchorNode)?.closest('table');
    const cell = currentCell();
    if (!table || !cell || !table.rows || table.rows.length <= 1) return;
    table.deleteRow(cell.parentElement.rowIndex);
    emitInput();
}

function addColumn() {
    const table = resolveEl(window.getSelection()?.anchorNode)?.closest('table');
    const cell = currentCell();
    if (!table || !cell || !table.rows) return;
    const colIndex = cell.cellIndex;
    for (const row of table.rows) {
        const td = row.insertCell(colIndex + 1);
        td.style.cssText = 'border:1px solid #e4e4e7;padding:8px;min-width:40px;';
        td.innerHTML = '<br>';
    }
    emitInput();
}

function removeColumn() {
    const table = resolveEl(window.getSelection()?.anchorNode)?.closest('table');
    const cell = currentCell();
    if (!table || !cell || !table.rows) return;
    const colIndex = cell.cellIndex;
    if (table.rows[0]?.cells.length <= 1) return;
    for (const row of table.rows) {
        if (row.cells.length > colIndex) row.deleteCell(colIndex);
    }
    emitInput();
}

// ---- Gambar ----
let savedRange = null;

function saveSelection() {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
        savedRange = sel.getRangeAt(0).cloneRange();
    }
}

function openImagePicker() {
    const el = editor.value;
    if (!el) return;
    el.focus();
    ensureSelectionInEditor();
    saveSelection();
    fileInput.value?.click();
}

async function uploadImageFile(file) {
    if (file.size > MAX_IMAGE_SIZE) {
        toast('Ukuran gambar melebihi 2 MB.', 'error');
        return null;
    }

    uploading.value = true;
    try {
        const form = new FormData();
        form.append('file', file);
        const res = await request('/api/admin/blast-images', { method: 'POST', body: form });
        if (!res.ok) {
            toast(res.data?.error || 'Gagal mengunggah gambar.', 'error');
            return null;
        }
        return res.data?.url || null;
    } catch (err) {
        toast(err?.message || 'Gagal mengunggah gambar.', 'error');
        return null;
    } finally {
        uploading.value = false;
    }
}

async function onImageFileChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) {
        replacingImage.value = false;
        return;
    }

    const url = await uploadImageFile(file);
    if (!url) {
        replacingImage.value = false;
        return;
    }

    if (replacingImage.value && currentImage.value) {
        currentImage.value.src = url;
        replacingImage.value = false;
        emitInput();
        updateContext();
    } else {
        insertImage(url);
    }
}

// Saat paste: kalau ada gambar dari clipboard, unggah dulu ke object storage
// (jangan biarkan jadi base64 yang bikin payload email membengkak).
function onPaste(e) {
    const items = e.clipboardData?.items || [];
    for (const item of items) {
        if (item.kind === 'file' && item.type.startsWith('image/')) {
            e.preventDefault();
            const file = item.getAsFile();
            if (file) {
                uploadImageFile(file).then((url) => {
                    if (url) insertImage(url);
                });
            }
            return;
        }
    }

    // Buang gambar base64 yang lolos dari paste HTML agar pesan tetap kecil.
    setTimeout(() => {
        const el = editor.value;
        if (!el) return;
        let removed = false;
        el.querySelectorAll('img[src^="data:"]').forEach((img) => {
            img.remove();
            removed = true;
        });
        if (removed) {
            emitInput();
            updateContext();
        }
    }, 0);
}

function insertImage(url) {
    const el = editor.value;
    if (!el) return;

    el.focus();
    const sel = window.getSelection();
    if (savedRange && sel && el.contains(savedRange.startContainer)) {
        sel.removeAllRanges();
        sel.addRange(savedRange);
    } else {
        ensureSelectionInEditor();
    }

    const img = document.createElement('img');
    img.src = url;
    img.alt = 'gambar';
    img.setAttribute('contenteditable', 'false');
    img.style.cssText = 'max-width:100%;height:auto;border-radius:8px;';

    insertNodeAtSelection(img);
    emitInput();
    updateContext();
}

function alignImage(align) {
    const sel = window.getSelection();
    const img = currentImage.value || resolveEl(sel?.anchorNode)?.closest('img');
    if (!img) return;

    if (align === 'center') {
        img.style.float = 'none';
        img.style.display = 'block';
        img.style.margin = '12px auto';
    } else if (align === 'left') {
        img.style.display = 'inline-block';
        img.style.float = 'left';
        img.style.margin = '4px 12px 12px 0';
    } else if (align === 'right') {
        img.style.display = 'inline-block';
        img.style.float = 'right';
        img.style.margin = '4px 0 12px 12px';
    }
    emitInput();
}

function deleteImage() {
    const img = currentImage.value;
    if (!img) return;
    img.remove();
    currentImage.value = null;
    replacingImage.value = false;
    emitInput();
    updateContext();
}

function replaceImage() {
    if (!currentImage.value) return;
    replacingImage.value = true;
    openImagePicker();
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
        <!-- Toolbar -->
        <div class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex flex-wrap items-center gap-1 px-2 py-1.5">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Tebal"
                    @mousedown.prevent="exec('bold')"
                >
                    <Bold class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Miring"
                    @mousedown.prevent="exec('italic')"
                >
                    <Italic class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Garis bawah"
                    @mousedown.prevent="exec('underline')"
                >
                    <Underline class="h-4 w-4" />
                </button>

                <span class="mx-1 h-5 w-px bg-neutral-300 dark:bg-neutral-700"></span>

                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Sisipkan tabel"
                    @mousedown.prevent="insertTable"
                >
                    <Table2 class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Sisipkan gambar"
                    :disabled="uploading"
                    @mousedown.prevent="openImagePicker"
                >
                    <ImageIcon class="h-4 w-4" />
                </button>

                <span v-if="uploading" class="text-xs text-neutral-400 dark:text-neutral-500">Mengunggah…</span>
            </div>

            <!-- Toolbar kontekstual: tabel -->
            <div
                v-if="activeTable"
                class="flex flex-wrap items-center gap-1 border-t border-neutral-200 px-2 py-1.5 dark:border-neutral-800"
            >
                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Tambah baris"
                    @mousedown.prevent="addRow"
                >
                    <Plus class="h-3.5 w-3.5" /> Baris
                </button>
                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Hapus baris"
                    @mousedown.prevent="removeRow"
                >
                    <Minus class="h-3.5 w-3.5" /> Baris
                </button>
                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Tambah kolom"
                    @mousedown.prevent="addColumn"
                >
                    <Plus class="h-3.5 w-3.5" /> Kolom
                </button>
                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Hapus kolom"
                    @mousedown.prevent="removeColumn"
                >
                    <Minus class="h-3.5 w-3.5" /> Kolom
                </button>
            </div>

            <!-- Toolbar kontekstual: gambar -->
            <div
                v-if="activeImage && !activeTable"
                class="flex flex-wrap items-center gap-1 border-t border-neutral-200 px-2 py-1.5 dark:border-neutral-800"
            >
                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Rata kiri"
                    @mousedown.prevent="alignImage('left')"
                >
                    <AlignLeft class="h-3.5 w-3.5" /> Kiri
                </button>
                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Rata tengah"
                    @mousedown.prevent="alignImage('center')"
                >
                    <AlignCenter class="h-3.5 w-3.5" /> Tengah
                </button>
                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Rata kanan"
                    @mousedown.prevent="alignImage('right')"
                >
                    <AlignRight class="h-3.5 w-3.5" /> Kanan
                </button>

                <span class="mx-1 h-4 w-px bg-neutral-300 dark:bg-neutral-700"></span>

                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-neutral-600 transition-colors hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    title="Ganti gambar"
                    @mousedown.prevent="replaceImage"
                >
                    <RefreshCw class="h-3.5 w-3.5" /> Ganti
                </button>
                <button
                    type="button"
                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md px-2 text-xs text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950"
                    title="Hapus gambar"
                    @mousedown.prevent="deleteImage"
                >
                    <Trash2 class="h-3.5 w-3.5" /> Hapus
                </button>
            </div>
        </div>

        <!-- Area edit -->
        <div class="relative">
            <div
                ref="editor"
                contenteditable="true"
                class="rt-editor min-h-[200px] max-h-[420px] overflow-auto bg-white px-4 py-3 text-sm text-neutral-900 outline-none dark:bg-neutral-950 dark:text-neutral-100"
                @input="emitInput"
                @click="onEditorClick"
                @mousedown="onEditorMousedown"
                @mouseup="updateContext"
                @keyup="updateContext"
                @paste="onPaste"
                @focusin="onEditorFocus"
                @focusout="onEditorBlur"
            ></div>
            <span
                v-if="showPlaceholder"
                class="pointer-events-none absolute left-4 top-3 text-sm text-neutral-400 dark:text-neutral-500"
            >Tulis isi email…</span>
        </div>

        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageFileChange" />
    </div>
</template>

<style scoped>
.rt-editor:empty::before {
    content: attr(data-placeholder);
    color: #a1a1aa;
    pointer-events: none;
}

.rt-editor table {
    border-collapse: collapse;
    width: 100%;
}

.rt-editor td,
.rt-editor th {
    border: 1px solid #e4e4e7;
    padding: 8px;
    min-width: 40px;
}

.rt-editor img {
    max-width: 100%;
    height: auto;
}
</style>
