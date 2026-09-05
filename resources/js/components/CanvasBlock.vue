<script setup>
import { computed, ref, watch, nextTick, onMounted } from 'vue';
import { GripVertical } from 'lucide-vue-next';
import { highlightCode } from '../utils/highlight';

const props = defineProps({
    block: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    measure: { type: Boolean, default: false },
    prefix: { type: String, default: '' },
    tocEntries: { type: Array, default: () => [] },
    tableEntries: { type: Array, default: () => [] },
    figureEntries: { type: Array, default: () => [] },
    referenceEntries: { type: Array, default: () => [] },
    citationStyle: { type: String, default: '' },
    entrySlice: { type: Array, default: null }, // [start, end] untuk blok daftar yang dipecah ke beberapa halaman
    minHeight: { type: Number, default: 0 }, // tinggi minimum (px) untuk editor, mis. blankPage satu halaman penuh
});

const emit = defineEmits(['select', 'update:content', 'update:indent', 'update:page-title', 'dragstart', 'dragend', 'edit-code']);

const contentEl = ref(null);
const titleEl = ref(null);

// Level heading: chapter = 1, h1 = 1, h2 = 2, ..., h10 = 10. Non-heading = 0.
const headingLevel = computed(() => {
    const t = props.block.type;
    if (t === 'chapter') return 1;
    const m = /^h(\d+)$/.exec(t);
    return m ? Math.min(10, Math.max(1, Number(m[1]))) : 0;
});

const tag = computed(() => {
    const lvl = headingLevel.value;
    if (props.block.type === 'chapter') return 'h1';
    if (lvl > 0) return lvl <= 6 ? `h${lvl}` : 'h6';
    if (props.block.type === 'quote') return 'blockquote';
    return 'p';
});

// Kelas baris menentukan ukuran/posisi tiap jenis blok.
const lineClass = computed(() => {
    if (props.block.type === 'chapter') return 'block-chapter-line';
    const lvl = headingLevel.value;
    if (lvl === 1) return 'block-h1-line';
    if (lvl === 2) return 'block-h2-line';
    if (lvl >= 3) return 'block-h3-line';
    if (props.block.type === 'quote') return 'block-quote-line';
    return 'block-paragraph-line';
});

const placeholder = computed(() => {
    const lvl = headingLevel.value;
    if (props.block.type === 'chapter') return 'Judul Bab';
    if (lvl > 0) return `Judul ${lvl}`;
    switch (props.block.type) {
        case 'cover': return 'Judul Dokumen';
        case 'abstract': return 'Tulis abstrak...';
        case 'blankPage': return 'Tulis konten halaman...';
        case 'toc': return 'Isi daftar...';
        case 'references': return 'Daftar pustaka...';
        case 'quote': return 'Tulis kutipan...';
        case 'bullet': return 'Item poin...';
        case 'number': return 'Item nomor...';
        default: return 'Tulis teks...';
    }
});

const sectionType = computed(() =>
    ['cover', 'abstract', 'toc', 'blankPage'].includes(props.block.type) ? props.block.type : null,
);

const sectionTitle = computed(() => {
    switch (sectionType.value) {
        case 'abstract': return 'ABSTRAK';
        case 'toc': return 'DAFTAR ISI';
        default: return '';
    }
});

// Slice entri untuk blok daftar yang dipecah ke beberapa halaman (Daftar Isi/Tabel/Gambar/Pustaka).
const entrySlice = computed(() =>
    Array.isArray(props.entrySlice) && props.entrySlice.length === 2 ? props.entrySlice : null,
);
const isFirstChunk = computed(() => !entrySlice.value || entrySlice.value[0] === 0);

function sliceEntries(arr) {
    if (!entrySlice.value) return arr;
    return arr.slice(entrySlice.value[0], entrySlice.value[1]);
}
const slicedTocEntries = computed(() => sliceEntries(props.tocEntries));
const slicedTableEntries = computed(() => sliceEntries(props.tableEntries));
const slicedFigureEntries = computed(() => sliceEntries(props.figureEntries));
const slicedReferenceEntries = computed(() => sliceEntries(props.referenceEntries));

const rootStyle = computed(() => {
    const style = { marginLeft: `${(props.block.indent || 0) * 1.5}em` };
    // Font kustom diterapkan ke seluruh blok (termasuk angka heading) agar nomor & isi konsisten.
    if (props.block.fontFamily) style.fontFamily = props.block.fontFamily;
    if (props.block.fontSize) style.fontSize = `${props.block.fontSize}pt`;
    if (props.block.color) style.color = props.block.color;
    // Spasi baris per blok: diteruskan via variabel agar dipakai juga oleh daftar
    // isi/tabel/gambar/pustaka yang punya line-height bawaan di CSS.
    if (props.block.lineHeight) style['--block-line-height'] = String(props.block.lineHeight);
    return style;
});

const alignClass = computed(() => {
    if (props.block.type === 'cover') return 'text-center';
    switch (props.block.align) {
        case 'center': return 'text-center';
        case 'right': return 'text-right';
        case 'justify': return 'text-justify';
        default: return 'text-left';
    }
});

const contentClass = computed(() =>
    (props.block.type === 'chapter' || headingLevel.value > 0) ? 'inline' : '',
);

const contentStyle = computed(() => {
    const style = {};
    // Baris pertama menjorok (seperti Tab di awal paragraf, ala Google Docs).
    if (props.block.firstLineIndent) style.textIndent = '1.27cm';
    // Spasi baris khusus untuk blok ini (0 = ikuti dokumen).
    if (props.block.lineHeight) style.lineHeight = props.block.lineHeight;
    // Kolom teks (1/2/3): konten mengalir seperti kolom Google Docs.
    const cols = Number(props.block.columns) || 1;
    if (cols > 1) {
        style.columnCount = cols;
        style.columnGap = '1.5em';
    }
    return style;
});

// Style elemen <ul>/<ol>. Untuk list nomor yang dipecah antar halaman, lanjutkan
// penomoran level-atas agar tidak mulai dari 1 lagi di halaman berikutnya.
const listElementStyle = computed(() => {
    const style = { ...contentStyle.value };
    if (props.block.type === 'number' && entrySlice.value && entrySlice.value[0] > 0) {
        style.counterReset = `lvl1 ${entrySlice.value[0]}`;
    }
    return style;
});

// Style untuk blok section (cover/abstract/toc/blankPage) — tambahkan tinggi minimum bila diperlukan.
const sectionStyle = computed(() => {
    const style = { ...contentStyle.value };
    if (props.minHeight) style.minHeight = `${props.minHeight}px`;
    return style;
});

// Blok kode: teks polos disimpan di block.content, lalu di-highlight saat render.
const hasCode = computed(() => String(props.block.content || '').trim().length > 0);
const highlightedCode = computed(() => highlightCode(props.block.content || ''));

function onInput() {
    // List poin/nomor yang dipecah antar halaman: gabungkan kembali potongan yang
    // diedit ke konten penuh agar blok sumber tidak rusak (terpotong).
    if ((props.block.type === 'bullet' || props.block.type === 'number') && entrySlice.value) {
        const full = parseTopLevelListItems(props.block.content);
        const edited = parseTopLevelListItems(contentEl.value ? contentEl.value.innerHTML : '');
        full.splice(entrySlice.value[0], entrySlice.value[1] - entrySlice.value[0], ...edited);
        emit('update:content', full.join(''));
        return;
    }
    emit('update:content', contentEl.value.innerHTML);
}

function onTitleInput() {
    if (titleEl.value) emit('update:page-title', titleEl.value.textContent);
}

// Tempel teks dari luar sebagai teks biasa (tanpa baris baru bawaan sumber).
function pastePlainText(e) {
    const raw = e.clipboardData?.getData('text/plain') || '';
    if (!raw) return null;
    e.preventDefault();
    return raw.replace(/\r\n?|\n/g, ' ').replace(/[ \t]+/g, ' ').trim();
}

function onPaste(e) {
    const text = pastePlainText(e);
    if (text) {
        document.execCommand('insertText', false, text);
        onInput();
    }
}

function onPasteTitle(e) {
    const text = pastePlainText(e);
    if (text) {
        document.execCommand('insertText', false, text);
        onTitleInput();
    }
}

const BLOCK_TAGS = /^(P|DIV|LI|BLOCKQUOTE|H[1-6])$/;

// Elemen paragraf yang memuat caret (root bila caret ada di teks langsung milik root).
function currentParagraph(root) {
    const sel = window.getSelection();
    if (!root || !sel || !sel.anchorNode) return root;
    let node = sel.anchorNode;
    if (node.nodeType === 3) node = node.parentNode;
    while (node && node !== root) {
        if (node.nodeType === 1 && BLOCK_TAGS.test(node.tagName)) return node;
        node = node.parentNode;
    }
    return root;
}

// Bungkus teks/anak inline langsung milik root menjadi satu <div> agar indent
// tidak ikut mewaris ke paragraf lain di blok yang sama.
function wrapRootInline(root) {
    const inline = [];
    for (const child of [...root.childNodes]) {
        if (child.nodeType === 1 && BLOCK_TAGS.test(child.tagName)) continue;
        inline.push(child);
    }
    if (!inline.length) return null;
    const wrapper = document.createElement('div');
    root.insertBefore(wrapper, inline[0]);
    for (const n of inline) wrapper.appendChild(n);
    return wrapper;
}

// Indent baris pertama hanya pada paragraf yang sedang diedit (bukan seluruh blok).
function toggleParagraphIndent(wantIndent) {
    const root = contentEl.value;
    if (!root) return;
    let para = currentParagraph(root);
    if (!para) return;
    // Paragraf pertama biasanya berupa teks langsung milik root; bungkus dulu
    // supaya indent-nya tersimpan di elemen sendiri dan tidak mewaris ke paragraf lain.
    if (para === root) {
        para = wrapRootInline(root) || para;
    }
    const current = getComputedStyle(para).textIndent;
    const hasIndent = current && current !== '0px';
    if (hasIndent === wantIndent) return;
    para.style.textIndent = wantIndent ? '1.27cm' : '0';
    onInput();
}

function onTab(e) {
    // Untuk paragraf/kutipan/abstrak/blankPage, Tab = indentasi baris pertama (ala Google Docs).
    if (props.block.type === 'paragraph' || props.block.type === 'quote' || props.block.type === 'abstract' || props.block.type === 'blankPage') {
        e.preventDefault();
        toggleParagraphIndent(!e.shiftKey);
        return;
    }
    const delta = e.shiftKey ? -1 : 1;
    const next = Math.min(6, Math.max(0, (props.block.indent || 0) + delta));
    if (next !== (props.block.indent || 0)) emit('update:indent', next);
}

// Untuk list (bullet/number), Tab membuat sub-level seperti Word:
// 1. -> a. -> i. (number) atau bullet bersarang (bullet).
function onListTab(e) {
    e.preventDefault();
    const root = contentEl.value;
    if (!root) return;
    const selection = window.getSelection();
    const li = selection && selection.anchorNode ? findListItem(selection.anchorNode, root) : null;
    if (!li) return;
    if (e.shiftKey) {
        outdentListItem(li, root);
    } else {
        indentListItem(li, root);
    }
    onInput();
}

function findListItem(node, root) {
    let n = node;
    while (n && n !== root) {
        if (n.nodeType === 1 && n.tagName === 'LI') return n;
        n = n.parentNode;
    }
    return null;
}

function indentListItem(li, root) {
    const prev = li.previousElementSibling;
    if (!prev) return;
    let sub = null;
    for (const child of prev.children) {
        if (child.tagName === 'OL' || child.tagName === 'UL') {
            sub = child;
            break;
        }
    }
    if (!sub) {
        sub = document.createElement(root.tagName === 'UL' ? 'ul' : 'ol');
        prev.appendChild(sub);
    }
    sub.appendChild(li);
}

function outdentListItem(li, root) {
    const parentList = li.parentNode;
    if (!parentList || parentList === root) return;
    const parentLi = parentList.parentNode;
    if (!parentLi || parentLi.tagName !== 'LI') return;
    parentLi.parentNode.insertBefore(li, parentLi.nextSibling);
    if (parentList.children.length === 0) parentList.remove();
}

// Ambil <li> level-atas dari HTML list (untuk memecah list poin/nomor antar halaman).
function parseTopLevelListItems(html) {
    if (!html) return [];
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const items = [];
    for (const child of doc.body.children) {
        if (child.tagName === 'LI') items.push(child.outerHTML);
    }
    return items;
}

function syncContent() {
    if (!contentEl.value) return;
    let html = props.block.content || '';
    // List poin/nomor yang dipecah antar halaman hanya menampilkan potongan <li>-nya.
    if (entrySlice.value && (props.block.type === 'bullet' || props.block.type === 'number')) {
        html = parseTopLevelListItems(html).slice(entrySlice.value[0], entrySlice.value[1]).join('');
    }
    if (contentEl.value.innerHTML !== html) {
        contentEl.value.innerHTML = html;
    }
}

function syncTitle() {
    if (titleEl.value && props.block.type === 'blankPage') {
        const text = props.block.pageTitle || '';
        if (titleEl.value.textContent !== text) titleEl.value.textContent = text;
    }
}

watch(
    () => [props.block.type, props.block.content, entrySlice.value],
    () => nextTick(syncContent),
);

watch(
    () => [props.block.type, props.block.pageTitle],
    () => nextTick(syncTitle),
);

onMounted(() => {
    syncContent();
    syncTitle();
});
</script>

<template>
    <div
        :data-block-uid="block.uid"
        class="group relative rounded-sm py-0.5"
        :class="measure ? '' : 'cursor-pointer'"
        :style="rootStyle"
        @click="$emit('select')"
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

        <template v-if="block.type === 'divider'">
            <hr class="border-neutral-200 dark:border-neutral-800" />
        </template>

        <template v-else-if="block.type === 'spacer'">
            <div
                class="spacer-block"
                :class="{ 'spacer-block-selected': selected }"
                :style="{ height: `${block.spacing || 24}px` }"
            ></div>
        </template>

        <template v-else-if="block.type === 'code'">
            <div
                class="code-block"
                :class="{ 'code-block-empty': !hasCode }"
                @click="$emit('edit-code')"
            >
                <pre v-if="hasCode" class="code-block-pre"><code v-html="highlightedCode"></code></pre>
                <span v-else class="code-block-placeholder">Klik untuk menempel kode...</span>
            </div>
        </template>

        <template v-else-if="block.type === 'bullet'">
            <ul
                ref="contentEl"
                class="editor-list list-disc pl-6 outline-none"
                :style="listElementStyle"
                :contenteditable="!measure"
                spellcheck="false"
                @input="onInput"
                @paste="onPaste"
                @keydown.tab.prevent="onListTab"
            ></ul>
        </template>

        <template v-else-if="block.type === 'number'">
            <ol
                ref="contentEl"
                class="editor-list list-numbered outline-none"
                :style="listElementStyle"
                :contenteditable="!measure"
                spellcheck="false"
                @input="onInput"
                @paste="onPaste"
                @keydown.tab.prevent="onListTab"
            ></ol>
        </template>

        <template v-else-if="block.type === 'toc'">
            <div class="section-block">
                <div v-if="isFirstChunk" class="section-title">DAFTAR ISI</div>
                <div class="toc-list">
                    <div
                        v-for="entry in slicedTocEntries"
                        :key="entry.uid"
                        class="toc-entry"
                        :class="`toc-level-${entry.level}`"
                    >
                        <span v-if="entry.number" class="toc-label">{{ entry.number }}</span>
                        <span class="toc-text">{{ entry.text || '(Tanpa judul)' }}</span>
                        <span class="toc-dots"></span>
                        <span class="toc-page">{{ entry.pageLabel }}</span>
                    </div>
                    <p v-if="slicedTocEntries.length === 0" class="text-sm text-neutral-400">Belum ada judul. Tambahkan Bab / Heading.</p>
                </div>
            </div>
        </template>

        <template v-else-if="block.type === 'listTables'">
            <div class="section-block">
                <div v-if="isFirstChunk" class="section-title">DAFTAR TABEL</div>
                <div class="toc-list">
                    <div v-for="entry in slicedTableEntries" :key="entry.uid" class="toc-entry toc-level-0">
                        <span class="toc-label">Tabel {{ entry.number }}</span>
                        <span class="toc-text">{{ entry.text || '(Tanpa deskripsi)' }}</span>
                        <span class="toc-dots"></span>
                        <span class="toc-page">{{ entry.pageLabel }}</span>
                    </div>
                    <p v-if="slicedTableEntries.length === 0" class="text-sm text-neutral-400">Belum ada tabel.</p>
                </div>
            </div>
        </template>

        <template v-else-if="block.type === 'listFigures'">
            <div class="section-block">
                <div v-if="isFirstChunk" class="section-title">DAFTAR GAMBAR</div>
                <div class="toc-list">
                    <div v-for="entry in slicedFigureEntries" :key="entry.uid" class="toc-entry toc-level-0">
                        <span class="toc-label">Gambar {{ entry.number }}</span>
                        <span class="toc-text">{{ entry.text || '(Tanpa deskripsi)' }}</span>
                        <span class="toc-dots"></span>
                        <span class="toc-page">{{ entry.pageLabel }}</span>
                    </div>
                    <p v-if="slicedFigureEntries.length === 0" class="text-sm text-neutral-400">Belum ada gambar.</p>
                </div>
            </div>
        </template>

        <template v-else-if="block.type === 'references'">
            <div class="section-block">
                <div v-if="isFirstChunk" class="section-title">DAFTAR PUSTAKA</div>
                <div class="reference-list">
                    <div v-for="(entry, i) in slicedReferenceEntries" :key="i" class="reference-entry">
                        <span class="reference-number">{{ (entrySlice ? entrySlice[0] : 0) + i + 1 }}.</span>
                        <span class="reference-text" v-html="entry"></span>
                    </div>
                    <p v-if="slicedReferenceEntries.length === 0" class="text-sm text-neutral-400">Belum ada sitasi. Tambahkan sitasi dari Workspace.</p>
                </div>
            </div>
        </template>

        <template v-else-if="sectionType">
            <div class="section-block">
                <div
                    v-if="block.type === 'blankPage'"
                    ref="titleEl"
                    class="section-title editable-title outline-none"
                    :contenteditable="!measure"
                    spellcheck="false"
                    :data-placeholder="measure ? undefined : 'HALAMAN'"
                    @input="onTitleInput"
                    @paste="onPasteTitle"
                ></div>
                <div v-else-if="sectionTitle" class="section-title">{{ sectionTitle }}</div>
                <div
                    ref="contentEl"
                    class="editor section-content outline-none"
                    :class="alignClass"
                    :style="sectionStyle"
                    :contenteditable="!measure"
                    spellcheck="false"
                    :data-placeholder="measure ? undefined : placeholder"
                    @input="onInput"
                    @paste="onPaste"
                    @keydown.tab.prevent="onTab"
                ></div>
            </div>
        </template>

        <template v-else>
            <div class="block-line" :class="[lineClass, alignClass]">
                <span v-if="prefix" class="block-prefix">{{ prefix }}</span>
                <component
                    :is="tag"
                    ref="contentEl"
                    class="editor min-w-0 outline-none"
                    :class="contentClass"
                    :style="contentStyle"
                    :contenteditable="!measure"
                    spellcheck="false"
                    :data-placeholder="measure ? undefined : placeholder"
                    @input="onInput"
                    @paste="onPaste"
                    @keydown.tab.prevent="onTab"
                ></component>
            </div>
        </template>
    </div>
</template>

<style scoped>
.editor:empty::before {
    content: attr(data-placeholder);
    color: #a3a3a3;
}

/* === Multi-level list: number (1. -> a. -> i.) & bullet bersarang === */
.editor-list { margin: 0; }

/* Numbered list */
:deep(.editor-list.list-numbered),
:deep(.editor-list.list-numbered ol),
:deep(.editor-list.list-numbered ul) {
    list-style: none;
    margin: 0;
    padding-left: 0;
}

:deep(.editor-list.list-numbered li) {
    position: relative;
    padding-left: 1.6em;
}

:deep(.editor-list.list-numbered) { counter-reset: lvl1; }
:deep(.editor-list.list-numbered > li) { counter-increment: lvl1; }
:deep(.editor-list.list-numbered > li::before) { content: counter(lvl1) '. '; }

:deep(.editor-list.list-numbered > li > ol) { counter-reset: lvl2; }
:deep(.editor-list.list-numbered > li > ol > li) { counter-increment: lvl2; }
:deep(.editor-list.list-numbered > li > ol > li::before) { content: counter(lvl2, lower-alpha) '. '; }

:deep(.editor-list.list-numbered > li > ol > li > ol) { counter-reset: lvl3; }
:deep(.editor-list.list-numbered > li > ol > li > ol > li) { counter-increment: lvl3; }
:deep(.editor-list.list-numbered > li > ol > li > ol > li::before) { content: counter(lvl3, lower-roman) '. '; }

:deep(.editor-list.list-numbered li::before) {
    position: absolute;
    left: 0;
    top: 0;
}

/* Bullet list bersarang: disc -> circle -> square */
:deep(.editor-list ul ul) { list-style-type: circle; }
:deep(.editor-list ul ul ul) { list-style-type: square; }


.block-line {
    display: block;
}

.block-prefix {
    font-weight: 700;
    white-space: nowrap;
    margin-right: 0.5rem;
}

.block-chapter-line { font-weight: 700; line-height: 1.4; text-transform: uppercase; margin: 1em 0; }
.block-h1-line { font-weight: 700; line-height: 1.25; margin: 0.75em 0 0.25em; }
.block-h2-line { font-weight: 700; line-height: 1.3; margin: 0.75em 0 0.25em; }
.block-h3-line { font-weight: 600; line-height: 1.3; margin: 0.5em 0 0.25em; }
.block-paragraph-line { margin: 0; }
.block-quote-line { border-left: 2px solid #d4d4d4; padding-left: 1rem; margin: 0.5em 0; color: #525252; }

.section-title { text-align: center; font-size: 1.25em; font-weight: 700; letter-spacing: 0.05em; margin: 0 0 1rem; }
.section-title.editable-title:empty::before { content: attr(data-placeholder); color: #a3a3a3; }
.section-content { min-height: 1em; }

/* List yang dibuat lewat toolbar di dalam blok teks/section (execCommand). */
.section-content :deep(ul), .section-content :deep(ol),
.editor :deep(ul), .editor :deep(ol) { margin: 0.25rem 0; padding-left: 1.5rem; }
.section-content :deep(ul), .editor :deep(ul) { list-style: disc; }
.section-content :deep(ol), .editor :deep(ol) { list-style: decimal; }

.spacer-block { width: 100%; }
.spacer-block-selected { outline: 1px dashed #d4d4d4; outline-offset: 2px; }

/* === Blok kode (syntax highlighting umum) === */
.code-block {
    border: 1px solid #e5e5e7;
    border-radius: 0.5rem;
    background: #f7f7f8;
    padding: 0.75rem 1rem;
    font-family: 'JetBrains Mono', 'Fira Code', Consolas, 'Courier New', monospace;
    font-size: 0.85em;
    line-height: 1.6;
    color: #1f2937;
    white-space: pre-wrap;
    word-break: break-word;
    overflow-x: auto;
    cursor: text;
}
.code-block-empty { padding: 1rem; }
.code-block-pre { margin: 0; font-family: inherit; }
.code-block-placeholder { color: #9ca3af; font-style: italic; }

.code-block :deep(.tok-keyword) { color: #7c3aed; font-weight: 500; }
.code-block :deep(.tok-string) { color: #15803d; }
.code-block :deep(.tok-comment) { color: #9ca3af; font-style: italic; }
.code-block :deep(.tok-number) { color: #b45309; }

.toc-list { margin-top: 0.5rem; }
.toc-entry { display: flex; align-items: baseline; margin: 0.25rem 0; line-height: var(--block-line-height, 1.5); }
.toc-level-1 { padding-left: 1rem; }
.toc-level-2 { padding-left: 2rem; }
.toc-level-3 { padding-left: 3rem; }
.toc-level-4 { padding-left: 4rem; }
.toc-level-5 { padding-left: 5rem; }
.toc-level-6 { padding-left: 6rem; }
.toc-level-7 { padding-left: 7rem; }
.toc-level-8 { padding-left: 8rem; }
.toc-level-9 { padding-left: 9rem; }
.toc-level-10 { padding-left: 10rem; }
.toc-label { white-space: nowrap; margin-right: 0.5rem; }
.toc-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.toc-dots { flex: 1; border-bottom: 1px dotted #a3a3a3; margin: 0 0.5rem; }
.toc-page { white-space: nowrap; }

.reference-list { margin-top: 0.5rem; }
.reference-entry { display: flex; gap: 0.5rem; margin: 0.35rem 0; line-height: var(--block-line-height, 1.6); }
.reference-number { flex-shrink: 0; min-width: 1.5rem; font-weight: 600; }
.reference-text { flex: 1; }
.reference-entry i { font-style: italic; }
</style>
