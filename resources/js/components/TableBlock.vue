<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { GripVertical } from 'lucide-vue-next';

const props = defineProps({
    block: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    measure: { type: Boolean, default: false },
    captionPrefix: { type: String, default: '' },
});

const emit = defineEmits(['select', 'update:content', 'update:indent', 'delete', 'dragstart', 'dragend']);

const tableEl = ref(null);
const menuEl = ref(null);
const mergeMode = ref(false);
const anchor = ref(null);
const target = ref(null);
const ctx = ref(null); // { x, y, row, col, nRows, nCols }

const CELL_STYLE = 'border: 1px solid #d4d4d4; padding: 6px 8px; vertical-align: top;';

function onInput() {
    emit('update:content', tableEl.value.innerHTML);
}

function onTab(e) {
    e.preventDefault();
    const delta = e.shiftKey ? -1 : 1;
    const next = Math.min(6, Math.max(0, (props.block.indent || 0) + delta));
    if (next !== (props.block.indent || 0)) emit('update:indent', next);
}

const rootStyle = computed(() => ({
    marginLeft: `${(props.block.indent || 0) * 1.5}em`,
}));

const captionText = computed(() =>
    (props.block.caption || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(),
);

const captionLabel = computed(() => {
    const pieces = ['Tabel'];
    if (props.captionPrefix) pieces.push(props.captionPrefix);
    if (captionText.value) pieces.push(captionText.value);
    return pieces.length > 1 ? pieces.join(' ') : '';
});

const captionPosition = computed(() => props.block.captionPosition || 'above');

const showCaption = computed(() => props.block.showCaption !== false);

const canMerge = computed(() => !!(anchor.value && target.value));

watch(
    () => props.block.content,
    (val) => {
        if (tableEl.value && tableEl.value.innerHTML !== val) {
            tableEl.value.innerHTML = val || '';
        }
    },
);

onMounted(() => {
    if (tableEl.value) tableEl.value.innerHTML = props.block.content || '';
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onDocKeydown);
    window.addEventListener('resize', closeContextMenu);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onDocKeydown);
    window.removeEventListener('resize', closeContextMenu);
});

// ---- Model grid (memahami rowspan/colspan) ----
function readGrid() {
    const table = tableEl.value;
    if (!table) return { grid: [], nRows: 0, nCols: 0 };
    const rows = Array.from(table.rows);
    const nRows = rows.length;
    let nCols = 0;
    for (const row of rows) {
        let s = 0;
        for (const c of Array.from(row.cells)) s += c.colSpan || 1;
        nCols = Math.max(nCols, s);
    }
    const grid = Array.from({ length: nRows }, () => new Array(nCols).fill(null));
    for (let r = 0; r < nRows; r++) {
        let c = 0;
        for (const cell of Array.from(rows[r].cells)) {
            while (c < nCols && grid[r][c] !== null) c++;
            if (c >= nCols) break;
            const rowspan = Math.max(1, cell.rowSpan || 1);
            const colspan = Math.max(1, cell.colSpan || 1);
            const entry = { el: cell, row: r, col: c, rowspan, colspan, html: cell.innerHTML };
            for (let dr = 0; dr < rowspan; dr++) {
                for (let dc = 0; dc < colspan; dc++) {
                    const rr = r + dr, cc = c + dc;
                    if (rr < nRows && cc < nCols) grid[rr][cc] = entry;
                }
            }
            c += colspan;
        }
    }
    return { grid, nRows, nCols };
}

function collectCells(grid, nRows, nCols) {
    const seen = new Set();
    const cells = [];
    for (let r = 0; r < nRows; r++) {
        for (let c = 0; c < nCols; c++) {
            const e = grid[r][c];
            if (e && !seen.has(e.el)) {
                seen.add(e.el);
                cells.push({ row: e.row, col: e.col, rowspan: e.rowspan, colspan: e.colspan, html: e.html });
            }
        }
    }
    return cells;
}

function serialize(nRows, nCols, cells) {
    const sorted = [...cells].sort((a, b) => a.row - b.row || a.col - b.col);
    const grid = Array.from({ length: nRows }, () => new Array(nCols).fill(null));
    for (const cell of sorted) {
        let r = cell.row, c = cell.col;
        while (c < nCols && grid[r][c]) c++;
        if (r >= nRows || c >= nCols) continue;
        const entry = { ...cell, row: r, col: c };
        for (let dr = 0; dr < cell.rowspan; dr++) {
            for (let dc = 0; dc < cell.colspan; dc++) {
                const rr = r + dr, cc = c + dc;
                if (rr < nRows && cc < nCols) grid[rr][cc] = entry;
            }
        }
    }
    let html = '<table style="border-collapse: collapse; width: 100%; table-layout: fixed;">';
    const seen = new Set();
    for (let r = 0; r < nRows; r++) {
        html += '<tr>';
        for (let c = 0; c < nCols; c++) {
            const e = grid[r][c];
            if (!e || seen.has(e)) continue;
            seen.add(e);
            const attrs = [];
            if (e.rowspan > 1) attrs.push(`rowspan="${e.rowspan}"`);
            if (e.colspan > 1) attrs.push(`colspan="${e.colspan}"`);
            html += `<td style="${CELL_STYLE}"${attrs.length ? ' ' + attrs.join(' ') : ''}>${e.html || '<br>'}</td>`;
        }
        html += '</tr>';
    }
    html += '</table>';
    return html;
}

function writeHtml(html) {
    if (tableEl.value) tableEl.value.innerHTML = html;
    syncFromDom();
}

function syncFromDom() {
    if (tableEl.value) emit('update:content', tableEl.value.innerHTML);
}

// ---- Operasi baris/kolom ----
function insertRowAt(R) {
    const { grid, nRows, nCols } = readGrid();
    if (nRows === 0 || nCols === 0) {
        writeHtml(serialize(1, 1, [{ row: 0, col: 0, rowspan: 1, colspan: 1, html: '' }]));
        return;
    }
    R = Math.max(0, Math.min(R, nRows));
    const cells = collectCells(grid, nRows, nCols);
    const newCells = [];
    for (const c of cells) {
        const row = c.row >= R ? c.row + 1 : c.row;
        let rowspan = c.rowspan;
        if (c.row < R && R < c.row + c.rowspan) rowspan += 1;
        newCells.push({ row, col: c.col, rowspan, colspan: c.colspan, html: c.html });
    }
    for (let c = 0; c < nCols; c++) newCells.push({ row: R, col: c, rowspan: 1, colspan: 1, html: '' });
    writeHtml(serialize(nRows + 1, nCols, newCells));
}

function insertColumnAt(C) {
    const { grid, nRows, nCols } = readGrid();
    if (nRows === 0 || nCols === 0) {
        writeHtml(serialize(1, 1, [{ row: 0, col: 0, rowspan: 1, colspan: 1, html: '' }]));
        return;
    }
    C = Math.max(0, Math.min(C, nCols));
    const cells = collectCells(grid, nRows, nCols);
    const newCells = [];
    for (const c of cells) {
        const col = c.col >= C ? c.col + 1 : c.col;
        let colspan = c.colspan;
        if (c.col < C && C < c.col + c.colspan) colspan += 1;
        newCells.push({ row: c.row, col, rowspan: c.rowspan, colspan, html: c.html });
    }
    for (let r = 0; r < nRows; r++) newCells.push({ row: r, col: C, rowspan: 1, colspan: 1, html: '' });
    writeHtml(serialize(nRows, nCols + 1, newCells));
}

function deleteRowAt(R) {
    const { grid, nRows, nCols } = readGrid();
    if (nRows <= 1) return;
    const cells = collectCells(grid, nRows, nCols);
    const newCells = [];
    for (const c of cells) {
        if (c.row === R) continue;
        const row = c.row > R ? c.row - 1 : c.row;
        let rowspan = c.rowspan;
        if (c.row < R && R < c.row + c.rowspan) rowspan -= 1;
        if (rowspan < 1) continue;
        newCells.push({ row, col: c.col, rowspan, colspan: c.colspan, html: c.html });
    }
    writeHtml(serialize(nRows - 1, nCols, newCells));
}

function deleteColumnAt(C) {
    const { grid, nRows, nCols } = readGrid();
    if (nCols <= 1) return;
    const cells = collectCells(grid, nRows, nCols);
    const newCells = [];
    for (const c of cells) {
        if (c.col === C) continue;
        const col = c.col > C ? c.col - 1 : c.col;
        let colspan = c.colspan;
        if (c.col < C && C < c.col + c.colspan) colspan -= 1;
        if (colspan < 1) continue;
        newCells.push({ row: c.row, col, rowspan: c.rowspan, colspan, html: c.html });
    }
    writeHtml(serialize(nRows, nCols - 1, newCells));
}

// ---- Gabung sel ----
function cellFromEvent(e) {
    return e.target && e.target.closest ? e.target.closest('td,th') : null;
}

function cellOrigin(cell) {
    const { grid, nRows, nCols } = readGrid();
    for (let r = 0; r < nRows; r++) {
        for (let c = 0; c < nCols; c++) {
            if (grid[r][c] && grid[r][c].el === cell) return { row: grid[r][c].row, col: grid[r][c].col };
        }
    }
    return null;
}

function cellAtPos(row, col) {
    const { grid, nRows, nCols } = readGrid();
    if (row >= 0 && row < nRows && col >= 0 && col < nCols) {
        const e = grid[row][col];
        return e ? e.el : null;
    }
    return null;
}

function onCellClick(e) {
    if (!mergeMode.value) return;
    const cell = cellFromEvent(e);
    if (!cell) return;
    e.preventDefault();
    e.stopPropagation();
    const pos = cellOrigin(cell);
    if (!pos) return;
    if (!anchor.value) {
        anchor.value = pos;
        target.value = null;
    } else if (!target.value) {
        target.value = pos;
    } else {
        anchor.value = pos;
        target.value = null;
    }
    refreshHighlight();
}

function refreshHighlight() {
    const table = tableEl.value;
    if (!table) return;
    for (const c of Array.from(table.querySelectorAll('td,th'))) {
        c.classList.remove('merge-selected', 'merge-anchor');
    }
    if (!anchor.value) return;
    if (target.value) {
        const minRow = Math.min(anchor.value.row, target.value.row);
        const maxRow = Math.max(anchor.value.row, target.value.row);
        const minCol = Math.min(anchor.value.col, target.value.col);
        const maxCol = Math.max(anchor.value.col, target.value.col);
        for (const c of Array.from(table.querySelectorAll('td,th'))) {
            const p = cellOrigin(c);
            if (p && p.row >= minRow && p.row <= maxRow && p.col >= minCol && p.col <= maxCol) {
                c.classList.add('merge-selected');
            }
        }
    } else {
        const c = cellAtPos(anchor.value.row, anchor.value.col);
        if (c) c.classList.add('merge-anchor');
    }
}

function toggleMergeMode() {
    mergeMode.value = !mergeMode.value;
    anchor.value = null;
    target.value = null;
    refreshHighlight();
}

function applyMerge() {
    const { grid, nRows, nCols } = readGrid();
    if (!anchor.value || !target.value) return;
    const minRow = Math.min(anchor.value.row, target.value.row);
    const maxRow = Math.max(anchor.value.row, target.value.row);
    const minCol = Math.min(anchor.value.col, target.value.col);
    const maxCol = Math.max(anchor.value.col, target.value.col);
    const rectCells = collectCells(grid, nRows, nCols)
        .filter((c) => c.row >= minRow && c.row <= maxRow && c.col >= minCol && c.col <= maxCol)
        .sort((a, b) => a.row - b.row || a.col - b.col);
    const mergedHtml = rectCells.map((c) => c.html || '<br>').join('<br>');
    const newCells = collectCells(grid, nRows, nCols).filter(
        (c) => !(c.row >= minRow && c.row <= maxRow && c.col >= minCol && c.col <= maxCol),
    );
    newCells.push({ row: minRow, col: minCol, rowspan: maxRow - minRow + 1, colspan: maxCol - minCol + 1, html: mergedHtml });
    mergeMode.value = false;
    anchor.value = null;
    target.value = null;
    writeHtml(serialize(nRows, nCols, newCells));
}

// ---- Menu konteks (klik kanan) ----
function onContextMenu(e) {
    if (props.measure) return;
    e.preventDefault();
    const cell = cellFromEvent(e);
    const { nRows, nCols } = readGrid();
    let row = 0, col = 0;
    if (cell) {
        const p = cellOrigin(cell);
        if (p) { row = p.row; col = p.col; }
    }
    ctx.value = { x: e.clientX, y: e.clientY, row, col, nRows, nCols };
    emit('select');
}

function closeContextMenu() {
    ctx.value = null;
}

function menuAction(fn) {
    fn();
    closeContextMenu();
}

function onDocClick(e) {
    if (!ctx.value) return;
    if (menuEl.value && menuEl.value.contains(e.target)) return;
    closeContextMenu();
}

function onDocKeydown(e) {
    if (e.key === 'Escape') closeContextMenu();
}
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

        <div v-if="mergeMode && !measure" class="mb-1 rounded-md border border-dashed border-neutral-300 px-2 py-1 text-xs text-neutral-500 dark:border-neutral-600 dark:text-neutral-400">
            Klik sel awal, lalu sel akhir untuk digabung.
            <button v-if="canMerge" type="button" class="tbtn ml-2" @click="applyMerge">Gabungkan</button>
            <button type="button" class="tbtn ml-2" @click="toggleMergeMode">Batal</button>
        </div>

        <div v-if="showCaption && captionLabel && captionPosition === 'above'" class="mb-1 text-center text-sm">{{ captionLabel }}</div>

        <table
            ref="tableEl"
            :contenteditable="!measure && !mergeMode"
            spellcheck="false"
            class="outline-none"
            :class="{ 'merge-mode': mergeMode }"
            @input="onInput"
            @click="onCellClick"
            @contextmenu.prevent.stop="onContextMenu"
        ></table>

        <div v-if="showCaption && captionLabel && captionPosition === 'below'" class="mt-1 text-center text-sm">{{ captionLabel }}</div>
    </div>

    <Teleport to="body">
        <div
            v-if="ctx"
            ref="menuEl"
            class="fixed z-[80] min-w-[200px] overflow-hidden rounded-lg border border-neutral-200 bg-white py-1 text-sm shadow-xl dark:border-neutral-800 dark:bg-neutral-950"
            :style="{ left: `${ctx.x}px`, top: `${ctx.y}px` }"
        >
            <button type="button" class="tmenu" @click="menuAction(() => insertRowAt(ctx.row))">Sisipkan baris di atas</button>
            <button type="button" class="tmenu" @click="menuAction(() => insertRowAt(ctx.row + 1))">Sisipkan baris di bawah</button>
            <button type="button" class="tmenu" @click="menuAction(() => insertColumnAt(ctx.col))">Sisipkan kolom di kiri</button>
            <button type="button" class="tmenu" @click="menuAction(() => insertColumnAt(ctx.col + 1))">Sisipkan kolom di kanan</button>
            <div class="my-1 border-t border-neutral-200 dark:border-neutral-800"></div>
            <button type="button" class="tmenu" :class="{ 'opacity-40': ctx.nRows <= 1 }" :disabled="ctx.nRows <= 1" @click="menuAction(() => deleteRowAt(ctx.row))">Hapus baris</button>
            <button type="button" class="tmenu" :class="{ 'opacity-40': ctx.nCols <= 1 }" :disabled="ctx.nCols <= 1" @click="menuAction(() => deleteColumnAt(ctx.col))">Hapus kolom</button>
            <button type="button" class="tmenu tmenu-danger" @click="menuAction(() => emit('delete'))">Hapus tabel</button>
            <div class="my-1 border-t border-neutral-200 dark:border-neutral-800"></div>
            <button type="button" class="tmenu" @click="menuAction(toggleMergeMode)">Gabung sel</button>
        </div>
    </Teleport>
</template>

<style scoped>
table {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
}

table.merge-mode {
    user-select: none;
}

:deep(td),
:deep(th) {
    border: 1px solid #d4d4d4;
    padding: 6px 8px;
    vertical-align: top;
    word-break: break-word;
}

:deep(td.merge-anchor),
:deep(th.merge-anchor) {
    background-color: rgba(0, 0, 0, 0.1);
}

:deep(td.merge-selected),
:deep(th.merge-selected) {
    background-color: rgba(0, 0, 0, 0.06);
}

:global(.dark) :deep(td.merge-anchor),
:global(.dark) :deep(th.merge-anchor) {
    background-color: rgba(255, 255, 255, 0.18);
}

:global(.dark) :deep(td.merge-selected),
:global(.dark) :deep(th.merge-selected) {
    background-color: rgba(255, 255, 255, 0.1);
}

.tbtn {
    cursor: pointer;
    border: 1px solid #d4d4d4;
    background: transparent;
    border-radius: 0.375rem;
    padding: 2px 8px;
    font-size: 0.75rem;
    color: #525252;
    transition: background-color 0.15s ease;
}

.tbtn:hover {
    background-color: rgba(0, 0, 0, 0.04);
}

.tmenu {
    display: block;
    width: 100%;
    cursor: pointer;
    text-align: left;
    padding: 6px 12px;
    font-size: 0.8125rem;
    color: #404040;
    transition: background-color 0.12s ease;
}

.tmenu:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.tmenu:disabled {
    cursor: not-allowed;
}

.tmenu-danger {
    color: #dc2626;
}

:global(.dark) .tmenu {
    color: #d4d4d4;
}

:global(.dark) .tmenu:hover {
    background-color: rgba(255, 255, 255, 0.06);
}

:global(.dark) .tmenu-danger {
    color: #f87171;
}
</style>
