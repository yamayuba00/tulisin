<script>
// Singleton module: siapkan wasm (compiler + renderer) sekali untuk semua blok rumus.
// Tanpa getModule, wasm-pack-shim akan melempar "Cannot import wasm module without importer".
import { $typst } from '@myriaddreamin/typst.ts';
import compilerWasmUrl from '@myriaddreamin/typst-ts-web-compiler/wasm?url';
import rendererWasmUrl from '@myriaddreamin/typst-ts-renderer/wasm?url';

let wasmConfigured = false;
function ensureTypstWasm() {
    if (wasmConfigured) return;
    wasmConfigured = true;
    try {
        $typst.setCompilerInitOptions({ getModule: () => compilerWasmUrl });
        $typst.setRendererInitOptions({ getModule: () => rendererWasmUrl });
    } catch (e) {
        // $typst mungkin sudah diinisialisasi (mis. saat HMR) — getModule sudah terpasang.
    }
}
</script>

<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { GripVertical } from 'lucide-vue-next';

const props = defineProps({
    block: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    measure: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'update:content', 'update:font-size', 'dragstart', 'dragend']);

const svgHtml = ref('');
const error = ref('');
const loading = ref(false);

let timer = null;
let renderToken = 0;

const source = computed(() => (props.block.content || '').trim());

// Ukuran rumus: ikuti block.fontSize (bisa diubah lewat toolbar), default 14pt agar tidak kecil.
const fontSize = computed(() => {
    const n = Number(props.block.fontSize);
    return n > 0 ? n : 14;
});

const alignClass = computed(() => {
    const align = props.block.align || 'center';
    if (align === 'left') return 'justify-start';
    if (align === 'right') return 'justify-end';
    return 'justify-center';
});

// Bungkus sebagai math block jika belum berupa math, dan buat halaman SVG
// transparan & ketat mengikuti konten (agar tidak ada kotak putih / margin besar).
function buildSource(src) {
    const body = src.includes('$') ? src : `$ ${src} $`;
    return `#set page(width: auto, height: auto, margin: 0pt, fill: none)\n#set text(size: ${fontSize.value}pt)\n` + body;
}

async function render() {
    const src = source.value;
    if (!src) {
        svgHtml.value = '';
        error.value = '';
        loading.value = false;
        return;
    }
    const token = ++renderToken;
    loading.value = true;
    error.value = '';
    try {
        ensureTypstWasm();
        const out = await $typst.svg({ mainContent: buildSource(src) });
        if (token !== renderToken) return;
        svgHtml.value = Array.isArray(out) ? out.join('') : out;
    } catch (e) {
        if (token !== renderToken) return;
        error.value = String((e && e.message) || e);
    } finally {
        if (token === renderToken) loading.value = false;
    }
}

function scheduleRender() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(render, 250);
}

function onSourceInput(e) {
    emit('update:content', e.target.value);
}

function onSizeInput(e) {
    emit('update:font-size', e.target.value ? Number(e.target.value) : 0);
}

watch(() => [props.block.content, props.block.fontSize], scheduleRender);

onMounted(render);
onBeforeUnmount(() => {
    renderToken++;
    if (timer) clearTimeout(timer);
});
</script>

<template>
    <div
        :data-block-uid="block.uid"
        class="group relative rounded-sm py-0.5"
        :class="measure ? '' : 'cursor-pointer'"
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

        <div class="flex" :class="alignClass">
            <div
                v-if="svgHtml"
                class="formula-svg max-w-full overflow-x-auto"
                v-html="svgHtml"
            ></div>
            <div v-else-if="loading" class="py-1 text-sm text-neutral-400 dark:text-neutral-500">
                Menyusun rumus...
            </div>
            <div v-else-if="error" class="w-full">
                <code class="block whitespace-pre-wrap font-mono text-sm text-neutral-600 dark:text-neutral-300">{{ source }}</code>
                <p class="mt-1 text-xs text-red-500">{{ error }}</p>
            </div>
            <div v-else class="py-1 text-sm text-neutral-400 dark:text-neutral-500">
                Ketik rumus Typst, mis. x^2 + y^2 = r^2
            </div>
        </div>

        <!-- Editor sumber Typst (hanya saat blok dipilih) -->
        <div v-if="selected && !measure" class="mt-1">
            <textarea
                :value="source"
                rows="2"
                spellcheck="false"
                placeholder="Ketik rumus Typst, mis. x^2 + y^2 = r^2"
                class="w-full rounded-md border border-neutral-300 bg-transparent px-2 py-1 font-mono text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-700 dark:focus:border-neutral-400"
                @input="onSourceInput"
                @click.stop
                @keydown.stop
            ></textarea>
            <div class="mt-1 flex items-center gap-2">
                <label class="text-xs text-neutral-500 dark:text-neutral-400">Ukuran</label>
                <input
                    :value="block.fontSize || ''"
                    type="number"
                    min="8"
                    max="72"
                    step="1"
                    placeholder="14"
                    class="w-20 rounded-md border border-neutral-300 bg-transparent px-2 py-1 text-sm outline-none transition-colors focus:border-neutral-500 dark:border-neutral-700 dark:focus:border-neutral-400"
                    @input="onSizeInput"
                    @click.stop
                    @keydown.stop
                />
                <span class="text-xs text-neutral-400 dark:text-neutral-500">pt (kosong = 14)</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.formula-svg :deep(svg) {
    max-width: 100%;
    height: auto;
    display: block;
}
</style>
